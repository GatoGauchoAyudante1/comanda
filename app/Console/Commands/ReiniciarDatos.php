<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Deja la instalación en cero para que el cliente cargue sus propios datos.
 *
 * El caso de uso es uno solo: le mostraste la app al cliente, la probó, le
 * gustó y quiere arrancar. Todo lo que hay adentro es de la demo —la carta,
 * los insumos, las mesas, las ventas de prueba, los usuarios inventados— y
 * nada de eso puede quedar el día que el local abre.
 *
 * Lo único que sobrevive son los `settings`: el punto de venta y los módulos
 * del negocio son decisiones de la instalación, no datos de prueba (D-04).
 *
 * Los usuarios se reemplazan por uno por rol con una clave conocida, para que
 * el dueño pueda entrar y empezar a cargar.
 *
 *   php artisan negocio:reiniciar
 *   php artisan negocio:reiniciar --password=LaRomana2026 --force
 *
 * Antes de correrlo en producción, respaldá: deploy/04-bases.sh --bajar
 */
class ReiniciarDatos extends Command
{
    protected $signature = 'negocio:reiniciar
                            {--password= : Clave de los usuarios que se crean}
                            {--dominio= : Dominio de los correos (por defecto, el de APP_URL)}
                            {--force : No preguntar nada}';

    protected $description = 'Borra los datos de prueba y deja un usuario por rol';

    /** Larga a propósito de tipear y obvia de leer: está para cambiarse. */
    public const CLAVE_POR_DEFECTO = 'cambiar1234';

    /**
     * Orden de borrado: los hijos antes que los padres.
     *
     * Las claves foráneas quedan activas a propósito. Si mañana alguien agrega
     * una tabla y se olvida de listarla acá, tiene que explotar el borrado —
     * y no quedar una fila huérfana apuntando a un usuario que ya no existe.
     */
    private const TABLAS = [
        // --- lo que se generó operando ---
        'payments',            // -> orders, cash_sessions, driver_settlements
        'order_items',         // -> orders, products, product_variants
        'deliveries',          // -> orders, addresses, customers, zones
        'table_sessions',      // -> orders, tables
        'orders',              // -> cash_sessions, users
        'driver_settlements',  // -> cash_sessions, users
        'cash_movements',      // -> cash_sessions
        'cash_sessions',
        'stock_count_items',   // -> stock_counts, ingredients
        'stock_counts',
        'stock_movements',     // -> ingredients
        'purchase_items',      // -> purchases, ingredients
        'purchases',           // -> suppliers
        'addresses',           // -> customers, zones
        'customers',
        'audit_events',

        // --- la configuración de la demo ---
        'recipe_items',        // -> products, ingredients: antes que los dos
        'product_variants',    // -> products
        'products',            // -> categories
        'categories',
        'ingredients',
        'suppliers',
        'tables',
        'table_rates',         // no lo referencia nadie, pero es de mesas
        'zones',

        'users',               // último: media base lo referencia
    ];

    /** No son datos del negocio, pero quedan colgadas de la instalación vieja. */
    private const TECNICAS = [
        'sessions',               // deja a todo el mundo afuera
        'password_reset_tokens',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
    ];

    private const NOMBRES = [
        'dueno'      => 'Dueño',
        'cajero'     => 'Cajero',
        'mozo'       => 'Mozo',
        'cocina'     => 'Cocina',
        'repartidor' => 'Repartidor',
    ];

    public function handle(): int
    {
        $clave = $this->option('password') ?: self::CLAVE_POR_DEFECTO;

        if (strlen($clave) < 8) {
            $this->error('La clave necesita al menos 8 caracteres.');

            return self::FAILURE;
        }

        $dominio = $this->option('dominio')
            ?: parse_url((string) config('app.url'), PHP_URL_HOST)
            ?: 'local.test';

        if (! $this->confirmar($dominio)) {
            $this->line('Cancelado. No se tocó nada.');

            return self::SUCCESS;
        }

        $borradas = DB::transaction(function () {
            $borradas = [];

            foreach (self::TABLAS as $tabla) {
                $borradas[$tabla] = DB::table($tabla)->delete();
            }

            foreach (self::TECNICAS as $tabla) {
                DB::table($tabla)->delete();
            }

            return $borradas;
        });

        $usuarios = $this->crearUsuarios($clave, $dominio);

        $this->call('optimize:clear');

        $this->informar($borradas, $usuarios, $clave);

        return self::SUCCESS;
    }

    /** Muestra lo que se va a perder ANTES de perderlo. */
    private function confirmar(string $dominio): bool
    {
        $filas = [];
        $total = 0;

        foreach (self::TABLAS as $tabla) {
            $n = DB::table($tabla)->count();
            $total += $n;

            if ($n > 0) {
                $filas[] = [$tabla, number_format($n, 0, ',', '.')];
            }
        }

        $this->newLine();
        $this->line('Se van a BORRAR estas filas:');
        $this->table(['Tabla', 'Filas'], $filas ?: [['(no hay nada que borrar)', '0']]);

        $this->line('Queda SÓLO la tabla `settings`: punto de venta y módulos del');
        $this->line('negocio, que son de la instalación y no datos de prueba.');
        $this->line('Todo lo demás —carta, insumos, recetas, mesas, zonas— se va.');
        $this->newLine();
        $this->line("Los usuarios se reemplazan por uno por rol, en @{$dominio}.");
        $this->newLine();

        if ($this->option('force')) {
            return true;
        }

        // En producción no alcanza con un sí: hay que escribir el nombre de la base.
        if (app()->isProduction()) {
            $base = (string) DB::connection()->getDatabaseName();

            $this->warn('Estás en PRODUCCIÓN. Esto no se puede deshacer sin un respaldo.');
            $this->line('Respaldá primero:  deploy/04-bases.sh --bajar');
            $this->newLine();

            return $this->ask("Escribí el nombre de la base para confirmar ({$base})") === $base;
        }

        return $this->confirm("¿Borrar {$total} filas y recrear los usuarios?", false);
    }

    /**
     * Un usuario por rol. No se reutilizan los viejos: se borraron con el
     * resto, porque sus nombres estaban pegados a pedidos y cobros de prueba.
     *
     * @return array<int, array{0:string, 1:string}>
     */
    private function crearUsuarios(string $clave, string $dominio): array
    {
        $creados = [];

        foreach (User::ROLES as $rol) {
            $usuario = User::create([
                'name'     => self::NOMBRES[$rol] ?? ucfirst($rol),
                'email'    => "{$rol}@{$dominio}",
                'role'     => $rol,
                'password' => Hash::make($clave),
                'active'   => true,
            ]);

            $creados[] = [$usuario->role, $usuario->email];
        }

        return $creados;
    }

    /**
     * @param  array<string, int>  $borradas
     * @param  array<int, array{0:string, 1:string}>  $usuarios
     */
    private function informar(array $borradas, array $usuarios, string $clave): void
    {
        $this->newLine();
        $this->info('Listo. Se borraron ' . number_format(array_sum($borradas), 0, ',', '.') . ' filas.');
        $this->newLine();

        $this->line('Entrá con cualquiera de estos, todos con la misma clave:');
        $this->table(
            ['Rol', 'Correo', 'Clave'],
            array_map(fn (array $u) => [...$u, $clave], $usuarios),
        );

        $this->warn('Cambiá estas claves antes de que el local abra.');
        $this->line('El dueño las cambia desde Ajustes → Usuarios. Es el único rol');
        $this->line('que entra ahí, así que tiene que hacerlo él para todos.');
    }
}
