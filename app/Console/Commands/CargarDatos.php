<?php

namespace App\Console\Commands;

use App\Actions\MarcarReventa;
use App\Models\Category;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use App\Support\Plata;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Carga la carta, las mesas y los mozos de un cliente desde archivos JSON.
 *
 * Es el paso siguiente a `negocio:reiniciar`: uno deja la instalación vacía y
 * éste la llena con los datos reales, sin tipear 37 productos a mano.
 *
 *   php artisan negocio:cargar ../cartas/laromanda
 *
 * La carpeta tiene que tener `carta.json`; `mesas.json` y `mozos.json` son
 * opcionales. Los precios del JSON van en PESOS y se guardan en centavos (R-31).
 *
 * Se corre en tu máquina, se revisa el resultado en el navegador, y recién
 * después se sube la base con deploy/04-bases.sh --subir. La carpeta de cartas
 * vive fuera del repo, así que en el VPS no existe.
 */
class CargarDatos extends Command
{
    protected $signature = 'negocio:cargar
                            {ruta : Carpeta con carta.json, mesas.json y mozos.json}
                            {--password= : Clave de los mozos que se creen}
                            {--dominio= : Dominio de los correos (por defecto, el de APP_URL)}
                            {--force : No preguntar nada}';

    protected $description = 'Carga carta, mesas y mozos de un cliente desde JSON';

    /**
     * Las bebidas no pasan por la cocina, y eso alcanza para deducir su receta:
     * lo que no se prepara se vende tal cual se compra. Por eso se les arma la
     * reventa 1:1 sola y el dueño no tiene que declarar nada.
     *
     * La excepción son los tragos, que salen de la barra pero sí se preparan.
     * Ésos quedan con la línea automática puesta y se corrigen desde Recetas
     * con «Lleva varios» — es el único caso que pide una mano humana, porque
     * los 70 ml de fernet no los adivina nadie.
     */
    private function esBebida(string $categoria): bool
    {
        return str_contains(Str::lower(Str::ascii($categoria)), 'bebida');
    }

    /**
     * Una carta de verdad repite nombres entre categorías: «Napolitana» es
     * pizza y es milanesa, «Completa» es las tres cosas. Pero la app pide
     * nombres de producto únicos (CartaController), y aunque no los pidiera,
     * dos «Napolitana» sueltas en la comanda no le sirven a nadie en la cocina.
     *
     * Por eso los que chocan se prefijan con la categoría en singular:
     * «Pizza Napolitana» y «Milanesa Napolitana». Los que no chocan quedan
     * como están, para no ensuciar la carta con prefijos inútiles.
     *
     * @param  array<int, mixed>  $categorias
     * @return array<string, int> nombre repetido => en cuántas categorías está
     */
    private function nombresRepetidos(array $categorias): array
    {
        $vistos = [];

        foreach ($categorias as $cat) {
            foreach ($cat['productos'] ?? [] as $p) {
                $nombre = $p['nombre'] ?? '';
                $vistos[$nombre] = ($vistos[$nombre] ?? 0) + 1;
            }
        }

        return array_filter($vistos, fn (int $n) => $n > 1);
    }

    /** «Pizzas» -> «Pizza». Con «Sandwich» o «Guarnición» no hace nada. */
    private function singular(string $categoria): string
    {
        return str_ends_with($categoria, 's') && ! str_ends_with($categoria, 'ss')
            ? substr($categoria, 0, -1)
            : $categoria;
    }

    /** El nombre final del producto, ya desambiguado si hacía falta. */
    private function nombreFinal(array $producto, string $categoria, array $repetidos): string
    {
        $nombre = $producto['nombre'];

        return isset($repetidos[$nombre])
            ? $this->singular($categoria) . ' ' . Str::lower(Str::substr($nombre, 0, 1)) . Str::substr($nombre, 1)
            : $nombre;
    }

    public function handle(): int
    {
        $ruta = rtrim($this->argument('ruta'), '/\\');

        if (! is_dir($ruta)) {
            $this->error("No existe la carpeta «{$ruta}».");

            return self::FAILURE;
        }

        $carta = $this->leerJson("{$ruta}/carta.json", obligatorio: true);
        $mesas = $this->leerJson("{$ruta}/mesas.json");
        $mozos = $this->leerJson("{$ruta}/mozos.json");

        if ($carta === null) {
            return self::FAILURE;
        }

        if (empty($carta['categorias']) || ! is_array($carta['categorias'])) {
            $this->error('carta.json no tiene un arreglo «categorias».');

            return self::FAILURE;
        }

        $sinPrecio = $this->resumir($carta, $mesas, $mozos);

        if (! $this->option('force') && ! $this->confirm('¿Cargar todo esto?', true)) {
            $this->line('Cancelado. No se tocó nada.');

            return self::SUCCESS;
        }

        $hechos = DB::transaction(function () use ($carta, $mesas, $mozos) {
            return [
                ...$this->cargarCarta($carta),
                ...$this->cargarMesas($mesas),
                ...$this->cargarMozos($mozos),
            ];
        });

        $this->informar($hechos, $sinPrecio);

        return self::SUCCESS;
    }

    /** @return array<string, mixed>|null */
    private function leerJson(string $archivo, bool $obligatorio = false): ?array
    {
        if (! is_file($archivo)) {
            if ($obligatorio) {
                $this->error("Falta el archivo «{$archivo}».");
            }

            return null;
        }

        $contenido = trim((string) file_get_contents($archivo));

        if ($contenido === '') {
            $this->warn('  ' . basename($archivo) . ' está vacío: se saltea.');

            return null;
        }

        $datos = json_decode($contenido, true);

        if (! is_array($datos)) {
            $this->error(basename($archivo) . ' no es JSON válido: ' . json_last_error_msg());

            return null;
        }

        return $datos;
    }

    /**
     * Muestra lo que se va a crear ANTES de crearlo.
     *
     * @return array<int, string> productos sin precio, que quedan desactivados
     */
    private function resumir(array $carta, ?array $mesas, ?array $mozos): array
    {
        $filas     = [];
        $sinPrecio = [];

        foreach ($carta['categorias'] as $cat) {
            $bebida = $this->esBebida($cat['nombre'] ?? '');

            foreach ($cat['productos'] ?? [] as $p) {
                if (! $this->tienePrecio($p)) {
                    $sinPrecio[] = $p['nombre'] ?? '?';
                }
            }

            $filas[] = [
                $cat['nombre'] ?? '?',
                count($cat['productos'] ?? []),
                $bebida ? 'no' : 'sí',
                $bebida ? 'automática (reventa 1:1)' : 'la carga el dueño',
            ];
        }

        $this->newLine();
        $this->line("Carta de «" . ($carta['negocio'] ?? 'sin nombre') . '»:');
        $this->table(['Categoría', 'Productos', 'A cocina', 'Receta'], $filas);

        if ($mesas !== null) {
            $tipo = $mesas['tipo'] ?? 'salon';
            $this->line('Mesas: ' . ($mesas['cantidad'] ?? 0) . " de tipo «{$tipo}».");
        }

        if ($mozos !== null) {
            $nombres = array_column($mozos['mozos'] ?? [], 'nombre');
            $this->line('Mozos: ' . (implode(', ', $nombres) ?: 'ninguno') . '.');
        }

        $repetidos = $this->nombresRepetidos($carta['categorias']);

        if ($repetidos !== []) {
            $renombres = [];

            foreach ($carta['categorias'] as $cat) {
                foreach ($cat['productos'] ?? [] as $p) {
                    if (isset($repetidos[$p['nombre']])) {
                        $renombres[] = [$p['nombre'], $cat['nombre'], $this->nombreFinal($p, $cat['nombre'], $repetidos)];
                    }
                }
            }

            $this->newLine();
            $this->warn(count($repetidos) . ' nombre(s) se repiten entre categorías y hay que desambiguarlos.');
            $this->line('La app pide nombres de producto únicos, y en la comanda dos');
            $this->line('«Napolitana» sueltas no le dicen nada a la cocina.');
            $this->table(['En el JSON', 'Categoría', 'Se va a llamar'], $renombres);
            $this->line('Si no te gustan estos nombres, cambialos en carta.json y volvé a correr.');
        }

        if ($sinPrecio !== []) {
            $this->newLine();
            $this->warn(count($sinPrecio) . ' producto(s) SIN PRECIO en el JSON.');
            $this->line('  ' . implode(', ', $sinPrecio));
            $this->line('  Se crean en $0 y DESACTIVADOS, así nadie los vende por error.');
            $this->line('  El dueño les pone precio y los activa desde la Carta.');
        }

        $this->newLine();

        return $sinPrecio;
    }

    private function tienePrecio(array $producto): bool
    {
        return isset($producto['precio']) && (float) $producto['precio'] > 0;
    }

    /** @return array<string, int> */
    private function cargarCarta(array $carta): array
    {
        $categorias = 0;
        $productos  = 0;
        $reventas   = 0;

        $marcar    = app(MarcarReventa::class);
        $repetidos = $this->nombresRepetidos($carta['categorias']);

        foreach (array_values($carta['categorias']) as $i => $cat) {
            $bebida = $this->esBebida($cat['nombre']);

            $categoria = Category::updateOrCreate(
                ['name' => $cat['nombre']],
                [
                    'goes_to_kitchen' => ! $bebida,
                    'sort_order'      => $i + 1,
                    'active'          => true,
                ],
            );
            $categorias++;

            foreach (array_values($cat['productos'] ?? []) as $j => $p) {
                $conPrecio = $this->tienePrecio($p);

                $producto = Product::updateOrCreate(
                    // Por nombre Y categoría: dos «Napolitana» de categorías
                    // distintas son dos productos, no uno que se pisa.
                    ['name' => $this->nombreFinal($p, $cat['nombre'], $repetidos), 'category_id' => $categoria->id],
                    [
                        // El JSON viene en pesos; la base guarda centavos (R-31).
                        'price'           => $conPrecio ? Plata::aCentavos($p['precio']) : 0,
                        'goes_to_kitchen' => ! $bebida,
                        // Todo descuenta salvo que el dueño lo apague desde la
                        // Carta (el café, el tiempo de pool). Antes esto venía
                        // apagado en la comida, y una receta cargada sobre el
                        // flag en cero no mueve un gramo: DescontarStock corta
                        // antes de mirarla, y ninguna pantalla lo avisa.
                        'tracks_stock'    => true,
                        'sort_order'      => $j + 1,
                        // Sin precio no se puede vender: entra apagado.
                        'active'          => $conPrecio,
                    ],
                );
                $productos++;

                // Lo que no pasa por la cocina se vende tal cual se compra, así
                // que su receta la puede escribir el sistema. El dueño abre la
                // app con las bebidas ya descontando; sólo le quedan los tragos,
                // que son los únicos que un humano tiene que dictar.
                if (! $producto->goes_to_kitchen && $marcar($producto) !== null) {
                    $reventas++;
                }
            }
        }

        return array_filter([
            'categorías' => $categorias,
            'productos'  => $productos,
            'reventas'   => $reventas,
        ]);
    }

    /** @return array<string, int> */
    private function cargarMesas(?array $mesas): array
    {
        $cantidad = (int) ($mesas['cantidad'] ?? 0);

        if ($cantidad < 1) {
            return [];
        }

        $tipo    = $mesas['tipo'] ?? 'salon';
        $prefijo = $mesas['prefijo'] ?? ($tipo === 'pool' ? 'Pool' : 'Mesa');

        for ($i = 1; $i <= $cantidad; $i++) {
            Table::updateOrCreate(
                ['name' => "{$prefijo} {$i}"],
                ['type' => $tipo, 'sort_order' => $i, 'active' => true],
            );
        }

        return ['mesas' => $cantidad];
    }

    /**
     * Los mozos reales, con nombre y todo.
     *
     * @return array<string, int>
     */
    private function cargarMozos(?array $mozos): array
    {
        $lista = $mozos['mozos'] ?? [];

        if ($lista === []) {
            return [];
        }

        $clave = $this->option('password') ?: ReiniciarDatos::CLAVE_POR_DEFECTO;

        /*
         * Mismo criterio que ReiniciarDatos: el host de APP_URL.
         *
         * Ojo si cargás en tu máquina y después subís la base con
         * 04-bases.sh: acá APP_URL es localhost, así que los mozos quedarían
         * con mail @localhost EN PRODUCCIÓN. Para eso está --dominio.
         */
        $dominio = $this->option('dominio')
            ?: parse_url((string) config('app.url'), PHP_URL_HOST)
            ?: 'local.test';
        $creados = 0;

        foreach ($lista as $i => $mozo) {
            $nombre = trim((string) ($mozo['nombre'] ?? ''));

            if ($nombre === '') {
                continue;
            }

            $usuario = Str::slug($nombre) ?: "mozo{$i}";

            User::updateOrCreate(
                ['email' => "{$usuario}@{$dominio}"],
                [
                    'name'     => $nombre,
                    'role'     => 'mozo',
                    'password' => Hash::make($clave),
                    'active'   => true,
                ],
            );
            $creados++;
        }

        /*
         * El mozo genérico que dejó `negocio:reiniciar` ya no hace falta: es
         * una cuenta con clave conocida que nadie va a usar. Se borra sólo si
         * nunca operó — si tocó un pedido, se queda por trazabilidad (R-32).
         */
        $generico = User::where('email', "mozo@{$dominio}")->first();

        if ($creados > 0 && $generico && ! $generico->tieneHistorial()) {
            $generico->delete();
            $this->line("  Se borró el usuario genérico mozo@{$dominio}, que quedó de más.");
        }

        return ['mozos' => $creados];
    }

    /**
     * @param  array<string, int>  $hechos
     * @param  array<int, string>  $sinPrecio
     */
    private function informar(array $hechos, array $sinPrecio): void
    {
        $this->newLine();

        foreach ($hechos as $que => $cuantos) {
            $this->info("  {$cuantos} {$que}");
        }

        if ($sinPrecio !== []) {
            $this->newLine();
            $this->warn('Pendiente: ' . count($sinPrecio) . ' productos quedaron en $0 y apagados.');
            $this->line('Están en la Carta, en gris. Poneles precio y activalos antes de abrir.');
        }

        $this->newLine();
        $this->line('Revisá el resultado en el navegador antes de subirlo:');
        $this->line('  deploy/04-bases.sh --subir');
    }
}
