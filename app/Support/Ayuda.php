<?php

namespace App\Support;

use App\Models\User;

/**
 * El manual del sistema, en un solo archivo.
 *
 * Responde la pregunta que hace todo el mundo la primera semana: «¿dónde se
 * carga esto?». Está acá y no en un PDF ni en un video porque un PDF envejece
 * sin que nadie se entere: si mañana los insumos se cargan en otro lado, la
 * pantalla y esta lista se tocan en el mismo commit o el problema se nota
 * enseguida.
 *
 * Dos reglas para editarlo:
 *
 *   1. Sólo se nombra lo que existe. Prometer una pantalla que no está es
 *      peor que no decir nada: el que la busca cree que es tonto.
 *   2. Cada tema se filtra por rol y por módulo, igual que la barra lateral.
 *      Explicarle a un mozo cómo cerrar la caja es mandarlo a un 403.
 *
 * Ver docs/06-reglas-negocio.md (R-27 a R-29, R-36, R-39) y
 * App\Support\Negocio para los módulos.
 */
class Ayuda
{
    /**
     * Subpantallas que, a los efectos de la ayuda, son la misma pantalla.
     * Estando en la ficha de una mesa lo que hace falta es la ayuda de mesas.
     */
    private const PADRES = [
        'mesa'          => 'panel',
        'consumo'       => 'panel',
        'cobro'         => 'panel',
        'ticket'        => 'panel',
        'pedidos.nuevo' => 'pedidos',
        'receta'        => 'recetas',
        'conteo'        => 'stock',
        'caja.cierre'   => 'caja',
    ];

    /**
     * La ayuda que le sirve a este usuario, con lo de la pantalla actual
     * separado adelante.
     *
     * @return array{aqui: array<int, array>, grupos: array<int, array{titulo: string, temas: array<int, array>}>}
     */
    public static function para(?User $usuario, ?string $rutaActual = null): array
    {
        $pantalla = self::PADRES[$rutaActual] ?? $rutaActual;

        $aqui   = [];
        $grupos = [];

        foreach (self::temas() as $grupo => $temas) {
            $visibles = [];

            foreach ($temas as $tema) {
                if (! self::corresponde($usuario, $tema)) {
                    continue;
                }

                $tema['grupo'] = $grupo;

                // Un tema aparece una sola vez: o arriba, en «acá estás», o
                // en su grupo. Repetido, el buscador lo muestra dos veces.
                if ($pantalla && in_array($pantalla, $tema['aqui'] ?? [$tema['ruta'] ?? null], true)) {
                    $aqui[] = $tema;
                } else {
                    $visibles[] = $tema;
                }
            }

            if ($visibles) {
                $grupos[] = ['titulo' => $grupo, 'temas' => $visibles];
            }
        }

        return ['aqui' => $aqui, 'grupos' => $grupos];
    }

    /** Mismo criterio que la barra lateral: el dueño ve todo. */
    private static function corresponde(?User $usuario, array $tema): bool
    {
        if (! $usuario) {
            return false;
        }

        if (($modulo = $tema['modulo'] ?? null) && ! Negocio::modulo($modulo)) {
            return false;
        }

        if ($tema['modulos'] ?? null) {
            $alguno = false;

            foreach ($tema['modulos'] as $m) {
                $alguno = $alguno || Negocio::modulo($m);
            }

            if (! $alguno) {
                return false;
            }
        }

        // Pantalla personal: al dueño explicarle cómo entregar «sus» envíos es
        // mandarlo a una lista que siempre va a estar vacía. Igual que el rail.
        if ($tema['exclusivo'] ?? false) {
            return in_array($usuario->role, $tema['roles'], true);
        }

        if ($usuario->role === 'dueno') {
            return true;
        }

        // El permiso de precios se delega usuario por usuario, no por rol, así
        // que manda él y no la lista de roles (R-39).
        if ($tema['precios'] ?? false) {
            return $usuario->puedeEditarPrecios();
        }

        $roles = $tema['roles'] ?? [];

        return $roles === [] || in_array($usuario->role, $roles, true);
    }

    /**
     * Los temas, agrupados como se trabaja: primero lo que se prepara una vez,
     * después lo del turno, al final lo que se mira.
     *
     * Claves de cada tema:
     *   titulo   qué quiere hacer la persona, en sus palabras
     *   donde    el camino en el menú, tal cual se lee en pantalla
     *   ruta     nombre de ruta para el botón «Ir»  (opcional)
     *   ancla    ancla dentro de esa pantalla       (opcional)
     *   aqui     rutas donde el tema es «lo de acá» (por defecto, `ruta`)
     *   pasos    el paso a paso, corto y en imperativo
     *   nota     la advertencia que se aprende a los golpes (opcional)
     *   roles    quiénes pueden; vacío = cualquiera que vea la pantalla
     *   modulo   módulo que tiene que estar activo  (opcional)
     *   alias    sinónimos para el buscador
     *
     * @return array<string, array<int, array>>
     */
    private static function temas(): array
    {
        return [

            'Preparar el local' => [
                [
                    'titulo' => 'Dar de alta mozos, cajeros y repartidores',
                    'donde'  => 'Ajustes → Usuarios',
                    'ruta'   => 'configuracion',
                    'ancla'  => '#usuarios',
                    'roles'  => ['dueno'],
                    'alias'  => 'cargar cargan alta dar de alta mozo mozos camarero cajero repartidor cadete usuario usuarios empleado personal clave contraseña',
                    'pasos'  => [
                        'Entrá a Ajustes y bajá hasta Usuarios.',
                        'Tocá «+ Nuevo usuario». Viene con el rol Mozo puesto: cambialo si es otro.',
                        'Cargá nombre, correo y una clave de 8 caracteres o más.',
                        'Para editar a alguien, tocá su nombre en la lista.',
                    ],
                    'nota' => 'No hay una lista de mozos aparte: un mozo es un usuario con rol Mozo. '
                        . 'El switch de cada fila le da o le saca el acceso sin borrar su historial; '
                        . 'borrar del todo sólo se puede si nunca operó.',
                ],
                [
                    'titulo'  => 'Cargar las mesas',
                    'donde'   => 'Ajustes → Mesas',
                    'ruta'    => 'configuracion',
                    'ancla'   => '#mesas',
                    'roles'   => ['dueno'],
                    'modulos' => ['salon', 'pool'],
                    'alias'   => 'cargar cargan alta crear agregar mesa mesas pool salon salón billar',
                    'pasos'   => [
                        'Ajustes → Mesas → «+ Agregar mesas».',
                        'Elegí el tipo (pool o salón), cómo se llaman y desde qué número hasta cuál.',
                        'Se crean todas juntas: «Pool 1» a «Pool 8». Las que ya existan se saltean.',
                        'Para dar de baja una, tocá su chip en la lista.',
                    ],
                    'nota' => 'Una mesa ocupada no se puede desactivar: cerrala primero. '
                        . 'No se puede renombrar ni borrar una mesa, sólo desactivarla.',
                ],
                [
                    'titulo' => 'Poner las tarifas de pool',
                    'donde'  => 'Ajustes → Tarifas de pool',
                    'ruta'   => 'configuracion',
                    'ancla'  => '#tarifas',
                    'roles'  => ['dueno'],
                    'modulo' => 'pool',
                    'alias'  => 'cargar cargan tarifa tarifas pool hora precio por hora fraccion fracción',
                    'pasos'  => [
                        'Ajustes → Tarifas de pool → «+ Nueva tarifa».',
                        'Cargá nombre, precio por hora y cada cuánto se cobra la fracción.',
                        'Marcá «Usar por defecto» la que se aplique casi siempre.',
                        'Para cambiar una, tocá su nombre.',
                    ],
                    'nota' => 'Cambiar una tarifa no toca las mesas ya abiertas: cada una se queda con la que tenía al abrirse (R-03).',
                ],
                [
                    'titulo' => 'Cargar las zonas de envío',
                    'donde'  => 'Ajustes → Zonas de envío',
                    'ruta'   => 'configuracion',
                    'ancla'  => '#zonas',
                    'roles'  => ['dueno'],
                    'modulo' => 'delivery',
                    'alias'  => 'cargar cargan zona zonas envio envío barrio costo delivery flete',
                    'pasos'  => [
                        'Ajustes → Zonas de envío → «+ Nueva zona».',
                        'Nombre del barrio y cuánto sale el envío ahí.',
                        'Para cambiarla, tocá su nombre.',
                    ],
                    'nota' => 'Los pedidos ya tomados mantienen el costo que tenían (R-15).',
                ],
                [
                    'titulo' => 'Prender o apagar partes del sistema',
                    'donde'  => 'Ajustes → Módulos',
                    'ruta'   => 'configuracion',
                    'ancla'  => '#modulos',
                    'roles'  => ['dueno'],
                    'alias'  => 'modulo módulos salon pool delivery stock prender apagar activar',
                    'pasos'  => [
                        'Ajustes → Módulos.',
                        'El switch de cada uno lo muestra o lo esconde en todo el sistema.',
                    ],
                    'nota' => 'Apagar un módulo no borra nada: mesas, pedidos y datos siguen guardados y vuelven a aparecer si lo prendés.',
                ],
            ],

            'La carta' => [
                [
                    'titulo' => 'Agregar un producto o una categoría',
                    'donde'  => 'Carta',
                    'ruta'   => 'carta',
                    'roles'  => ['dueno'],
                    'alias'  => 'cargar cargan alta agregar producto productos categoria categoría carta plato bebida',
                    'pasos'  => [
                        'Entrá a Carta.',
                        'Para un producto: «+ Nuevo producto». Nombre, categoría y precio.',
                        '«Va a cocina» lo hace aparecer en la pantalla de cocina. «Descuenta stock» lo hace consumir insumos.',
                        'Para una categoría: «+ Categoría», abajo de la lista de la izquierda.',
                        'Para editar un producto, tocá su nombre en la tabla.',
                    ],
                    'nota' => 'Una categoría no se puede renombrar ni borrar. Un producto que ya no vendés, apagalo con el switch «Activo»: sale de la carta pero su historial de ventas queda.',
                ],
                [
                    'titulo'  => 'Cambiar precios',
                    'donde'   => 'Carta',
                    'ruta'    => 'carta',
                    'precios' => true,
                    'roles'   => [],
                    'alias'   => 'precio precios aumento aumentar lote porcentaje ajustar',
                    'pasos'   => [
                        'Uno solo: escribí el número en la columna Precio y tocá Enter (o salí del campo). Se guarda solo.',
                        'Escape cancela antes de salir del campo.',
                        'Varios de una: «Actualizar precios en lote», poné el porcentaje (negativo para bajar), a cuánto redondear y si es toda la carta o una categoría.',
                        'Antes de aplicar se ve cómo quedarían tres productos de ejemplo.',
                    ],
                    'nota' => 'Cada cambio de precio queda registrado con quién y cuándo, y se ve en Historial.',
                ],
                [
                    'titulo' => 'Ponerle foto a un producto',
                    'donde'  => 'Carta',
                    'ruta'   => 'carta',
                    'roles'  => ['dueno'],
                    'alias'  => 'foto fotos imagen imágenes producto sacar foto',
                    'pasos'  => [
                        'Carta → tocá el nombre del producto.',
                        'En «Foto», elegí el archivo. Se ve la vista previa antes de guardar.',
                        'Guardá. La foto se achica sola, no hace falta prepararla.',
                        'Para sacarla, tocá «Quitar» y guardá.',
                    ],
                    'nota' => 'La foto se usa sólo en la carta pública. En la tabla, el recuadro punteado marca a qué producto todavía le falta.',
                ],
                [
                    'titulo' => 'Publicar la carta para los clientes y armar el QR',
                    'donde'  => 'Ajustes → Carta',
                    'ruta'   => 'configuracion',
                    'ancla'  => '#carta',
                    // Se configura en Ajustes, pero la pregunta nace mirando la Carta.
                    'aqui'   => ['configuracion', 'carta'],
                    'roles'  => ['dueno'],
                    'alias'  => 'carta publica pública qr codigo código cliente link menu menú imprimir mesa',
                    'pasos'  => [
                        'Ajustes → Carta → prendé el switch «Carta pública».',
                        'Aparece el link para mandar por WhatsApp y el código QR.',
                        '«Imprimir para la mesa» abre una hoja lista para imprimir y pegar.',
                        'El mensaje bajo el título es opcional: horarios, teléfono, lo que quieras.',
                    ],
                    'nota' => 'Muestra sólo productos activos con su precio y su foto. Nunca costos, márgenes, recetas ni stock. Los cambios de precio se ven al instante: no hay que volver a publicar nada.',
                ],
            ],

            'Insumos y recetas' => [
                [
                    'titulo' => 'Cargar un insumo',
                    'donde'  => 'Stock',
                    'ruta'   => 'stock',
                    'roles'  => ['cajero'],
                    'modulo' => 'stock',
                    'alias'  => 'cargar cargan alta insumo insumos materia prima mercaderia mercadería harina carne stock',
                    'pasos'  => [
                        'Entrá a Stock → «+ Nuevo insumo».',
                        'Nombre, en qué se mide (gramos, mililitros o unidades) y de qué área es (cocina, barra o descartables).',
                        'El costo se carga como lo comprás: «$8.900 el kg». El sistema hace la cuenta por gramo.',
                        'Poné un stock mínimo para que avise cuando esté por faltar.',
                    ],
                    'nota' => 'El insumo nace en cero. El stock entra por una compra o por un conteo, no en el alta.',
                ],
                [
                    'titulo' => 'Cargar la receta de un producto',
                    'donde'  => 'Recetas',
                    'ruta'   => 'recetas',
                    'roles'  => ['dueno'],
                    'modulo' => 'stock',
                    'alias'  => 'cargar cargan alta receta recetas ingredientes costo margen rendimiento',
                    'pasos'  => [
                        'Entrá a Recetas. La pestaña «Por producto» lista lo que falta cargar.',
                        'Tocá «Cargar receta» y elegí cómo te sale más fácil decirlo:',
                        '«Por rendimiento»: de un envase de 1 kg me salen 40 unidades.',
                        '«Por cantidad»: cada unidad lleva 200 g.',
                        'Marcá «Sólo en delivery y retiro» lo que sólo se usa para llevar (cajas, bolsas).',
                        'La pestaña «Por insumo» sirve para lo contrario: cargar de una todos los productos que llevan el mismo insumo.',
                    ],
                    'nota' => 'Sin receta el producto se vende igual, pero no descuenta stock ni calcula margen. La Carta marca en ámbar los que controlan stock y no tienen receta.',
                ],
                [
                    'titulo' => 'Registrar una compra',
                    'donde'  => 'Stock',
                    'ruta'   => 'stock',
                    'roles'  => ['cajero'],
                    'modulo' => 'stock',
                    'alias'  => 'cargar cargan registrar compra compras proveedor proveedores factura remito entrada mercaderia',
                    'pasos'  => [
                        'Stock → «Registrar compra».',
                        'Elegí el insumo, cuánto entró y cuánto pagaste en total.',
                        'Suma el stock y actualiza el costo del insumo con ese último precio.',
                    ],
                    'nota' => 'El sistema no maneja proveedores: la compra se carga contra el insumo, sin registrar a quién se le compró.',
                ],
                [
                    'titulo' => 'Registrar una merma',
                    'donde'  => 'Stock',
                    'ruta'   => 'stock',
                    'roles'  => ['cajero'],
                    'modulo' => 'stock',
                    'alias'  => 'cargar cargan registrar merma rotura vencido perdida pérdida descarte tirar',
                    'pasos'  => [
                        'Stock → «Registrar merma».',
                        'Insumo, cuánto se perdió y el motivo (es obligatorio).',
                    ],
                    'nota' => 'El motivo es lo único que después explica un faltante en el conteo. «Varios» no sirve.',
                ],
                [
                    'titulo' => 'Hacer un conteo de stock',
                    'donde'  => 'Stock → Hacer conteo',
                    'ruta'   => 'conteo',
                    // Estando en el conteo, la ruta ya se resolvió a `stock`.
                    'aqui'   => ['stock'],
                    'roles'  => ['cajero'],
                    'modulo' => 'stock',
                    'alias'  => 'hacer conteo contar inventario ajuste faltante sobrante stock',
                    'pasos'  => [
                        'Stock → «Hacer conteo».',
                        'Elegí qué vas a contar: todo, o sólo cocina, barra o descartables.',
                        'Abrí el conteo: eso congela lo que el sistema cree que hay.',
                        'Andá cargando lo que contaste, insumo por insumo. Cada fila muestra si falta o sobra, y cuánta plata es.',
                        '«Cerrar y ajustar» deja el stock real igual a lo contado.',
                    ],
                    'nota' => 'Sólo puede haber un conteo abierto a la vez, y una vez cerrado no se modifica.',
                ],
            ],

            'El turno' => [
                [
                    'titulo' => 'Abrir la caja',
                    'donde'  => 'Caja',
                    'ruta'   => 'caja',
                    'roles'  => ['cajero'],
                    'alias'  => 'abrir caja turno fondo apertura empezar arrancar',
                    'pasos'  => [
                        'Entrá a Caja y cargá el fondo inicial: la plata con la que arrancás.',
                        'Tocá «Abrir turno».',
                    ],
                    'nota' => 'Es lo primero del día. Sin caja abierta no se puede abrir ninguna mesa.',
                ],
                [
                    'titulo'  => 'Abrir una mesa',
                    'donde'   => 'Mesas',
                    'ruta'    => 'panel',
                    'roles'   => ['cajero', 'mozo'],
                    'modulos' => ['salon', 'pool'],
                    'alias'   => 'mesa abrir ocupar reloj jugadores atiende pool',
                    'pasos'   => [
                        'En Mesas, tocá una mesa libre.',
                        'En pool: elegí la tarifa y desde cuándo corre el reloj (podés arrancarlo «hace 10 minutos» si te lo pidieron antes).',
                        'Cargá cuántos son, una referencia si hace falta y quién atiende.',
                        'Tocá «Abrir mesa».',
                    ],
                    'nota' => 'Si dice que no hay caja abierta, abrí el turno primero en Caja.',
                ],
                [
                    'titulo'  => 'Cargar consumos a una mesa',
                    'donde'   => 'Mesas → la mesa → + Agregar consumo',
                    'ruta'    => 'panel',
                    'roles'   => ['cajero', 'mozo'],
                    'modulos' => ['salon', 'pool'],
                    'alias'   => 'consumo consumos cargar cargan pedido mesa comanda agregar productos nota cocina',
                    'pasos'   => [
                        'Tocá la mesa abierta y después «+ Agregar consumo».',
                        'Filtrá por categoría y tocá el producto para sumar uno. Si tiene tamaños, elegilo.',
                        'A la derecha se va armando lo que estás cargando; la × saca una línea.',
                        'En lo que va a cocina podés escribir una nota («sin sal», «bien cocida»).',
                        'Tocá «Confirmar y cargar a la mesa»: recién ahí sale la comanda a cocina.',
                    ],
                    'nota' => 'Una línea ya confirmada no se saca de la mesa desde la pantalla. Si te equivocaste, avisale al cajero: se resuelve al cobrar o anulando.',
                ],
                [
                    'titulo'  => 'Sacar la precuenta',
                    'donde'   => 'Mesas → la mesa → Precuenta',
                    'ruta'    => 'panel',
                    'roles'   => ['cajero', 'mozo'],
                    'modulos' => ['salon', 'pool'],
                    'alias'   => 'precuenta ticket cuenta imprimir comprobante papel',
                    'pasos'   => [
                        'Entrá a la mesa y tocá «Precuenta».',
                        'Se abre el ticket en una pestaña nueva, listo para imprimir.',
                    ],
                    'nota' => 'La precuenta no cobra ni cierra nada: es papel para la mesa.',
                ],
                [
                    'titulo'  => 'Cobrar y cerrar la mesa',
                    'donde'   => 'Mesas → la mesa → Cerrar mesa y cobrar',
                    'ruta'    => 'panel',
                    'roles'   => ['cajero'],
                    'modulos' => ['salon', 'pool'],
                    'alias'   => 'cobrar cobro pagar pago efectivo tarjeta credito crédito debito débito qr transferencia cerrar mesa vuelto',
                    'pasos'   => [
                        'En la mesa, tocá «Cerrar mesa y cobrar». Eso frena el reloj.',
                        'Elegí con qué paga y cuánto imputás. Con efectivo te calcula el vuelto.',
                        'Se pueden combinar medios: cargá un pago, después otro, hasta que el saldo quede en cero.',
                        'Tocá «Confirmar cobro y liberar la mesa».',
                    ],
                    'nota' => 'Al confirmar se descuenta el stock de los insumos. El mozo no cobra (R-28). '
                        . 'Un cobro confirmado no se puede revertir: si te equivocaste, queda registrado y se arregla con un movimiento de caja.',
                ],
                [
                    'titulo'  => 'Anular una mesa',
                    'donde'   => 'Mesas → la mesa → Anular mesa',
                    'ruta'    => 'panel',
                    'roles'   => ['cajero'],
                    'modulos' => ['salon', 'pool'],
                    'alias'   => 'anular anulacion anulación cancelar error borrar mesa cuenta',
                    'pasos'   => [
                        'Entrá a la mesa y tocá «Anular mesa», abajo de todo.',
                        'Escribí el motivo: es obligatorio y queda en el historial.',
                        'Si había consumos, decí si se sirvieron o no.',
                    ],
                    'nota' => '«Sí, se sirvió y no se cobró» descuenta el stock como merma. '
                        . '«No, fue un error de carga» lo deja como está. Elegir mal ensucia el stock.',
                ],
                [
                    'titulo' => 'Cargar un gasto o un retiro',
                    'donde'  => 'Caja → Gastos y retiros',
                    'ruta'   => 'caja',
                    'roles'  => ['cajero'],
                    'alias'  => 'gasto gastos retiro retiros movimiento plata sacar poner caja fuerte',
                    'pasos'  => [
                        'Entrá a Caja, card «Gastos y retiros».',
                        'Concepto, importe y tipo: gasto, retiro a caja fuerte o ingreso de plata.',
                        'Tocá «+ Registrar movimiento».',
                    ],
                    'nota' => 'Todo lo que sale o entra de la caja se carga acá. Lo que no se carga aparece como diferencia al cerrar.',
                ],
                [
                    'titulo' => 'Cerrar la caja',
                    'donde'  => 'Caja',
                    'ruta'   => 'caja',
                    'roles'  => ['cajero'],
                    'alias'  => 'cerrar cierre caja arqueo z billetes diferencia turno terminar',
                    'pasos'   => [
                        'Entrá a Caja al final del turno.',
                        'Mirá «Antes de cerrar»: ahí aparece lo que quedó a medias (mesas abiertas, cobros sin confirmar).',
                        'Contá la plata y cargá cuántos billetes de cada uno hay. La diferencia se calcula sola.',
                        'Si hay diferencia, explicá por qué.',
                        'Tocá «Confirmar cierre de caja».',
                    ],
                    'nota' => 'Un turno cerrado no se modifica nunca más. Después del cierre queda el comprobante Z, que se puede volver a ver desde Historial.',
                ],
            ],

            'Delivery y retiro' => [
                [
                    'titulo' => 'Cargar un pedido de delivery o retiro',
                    'donde'  => 'Pedidos → + Nuevo pedido',
                    'ruta'   => 'pedidos',
                    'roles'  => ['cajero', 'mozo'],
                    'modulo' => 'delivery',
                    'alias'  => 'cargar cargan delivery pedido pedidos retiro takeaway envio envío telefono teléfono cliente domicilio',
                    'pasos'  => [
                        'Pedidos → «+ Nuevo pedido».',
                        'Empezá por el teléfono: si el cliente ya compró, aparece «Cliente conocido» y podés reusar la dirección con un toque.',
                        'Elegí Delivery o Retira en el local. En delivery cargá dirección y zona.',
                        '«+ Agregar producto» abre la grilla: tocá cada producto para sumarlo.',
                        'Elegí cómo paga. Con efectivo, cargá con cuánto paga y te calcula el vuelto.',
                        'Tocá «Confirmar y enviar a cocina».',
                    ],
                    'nota' => 'El pedido de mostrador todavía no tiene pantalla propia.',
                ],
                [
                    'titulo' => 'Asignar el repartidor',
                    'donde'  => 'Pedidos',
                    'ruta'   => 'pedidos',
                    'roles'  => ['cajero', 'mozo'],
                    'modulo' => 'delivery',
                    'alias'  => 'repartidor cadete asignar quien lleva moto envio',
                    'pasos'  => [
                        'En el tablero de Pedidos, tocá «Asignar» en la tarjeta del pedido.',
                        'Elegí quién lo lleva.',
                    ],
                    'nota' => 'Si no aparece nadie, cargá al repartidor en Ajustes → Usuarios con rol Repartidor.',
                ],
                [
                    'titulo'    => 'Entregar tus envíos y rendir la plata',
                    'donde'     => 'Envíos',
                    'ruta'      => 'envios',
                    'roles'     => ['repartidor'],
                    'exclusivo' => true,
                    'modulo'    => 'delivery',
                    'alias'  => 'envio envíos entregar rendir rendicion rendición repartidor plata efectivo',
                    'pasos'  => [
                        'En Envíos tenés tus pedidos. Cada uno dice la dirección y si hay que cobrar o ya está pago.',
                        '«Llamar» marca el teléfono del cliente.',
                        'Cuando dejaste el pedido, tocá «Entregado».',
                        'Al volver, tocá «Rendir caja» y cargá cuánto entregás.',
                    ],
                    'nota' => 'Sólo ves y entregás tus propios envíos.',
                ],
            ],

            'Cocina' => [
                [
                    'titulo' => 'Marcar una comanda lista',
                    'donde'  => 'Cocina',
                    'ruta'   => 'cocina',
                    'roles'  => ['cocina', 'cajero', 'mozo'],
                    'alias'  => 'cocina comanda listo marcar plato salida kds',
                    'pasos'  => [
                        'Cada ticket muestra de dónde viene, hace cuántos minutos entró y las notas en amarillo.',
                        'El color del tiempo avisa: verde va bien, amarillo se está haciendo largo, rojo hace más de media hora.',
                        'Cuando salió todo el ticket, tocá «LISTO».',
                        'La pantalla se actualiza sola cada 10 segundos: no hace falta recargar.',
                    ],
                    'nota' => 'Se marca la comanda entera, no plato por plato. Si el botón no aparece y dice «En preparación», tenés permiso para mirar pero no para marcar.',
                ],
                [
                    'titulo' => 'Dejar que otro rol marque comandas listas',
                    'donde'  => 'Ajustes → Cocina',
                    'ruta'   => 'configuracion',
                    'ancla'  => '#cocina',
                    'roles'  => ['dueno'],
                    'alias'  => 'cocina permiso listo rol cajero mozo marcar',
                    'pasos'  => [
                        'Ajustes → Cocina.',
                        'Tildá los roles que también van a poder marcar listo.',
                    ],
                    'nota' => 'Cocina siempre puede y no se le puede sacar. Ojo con habilitar al mozo: un apurado marca listo algo que todavía no salió y el pedido se despacha vacío (R-36).',
                ],
            ],

            'Mirar el negocio' => [
                [
                    'titulo' => 'Buscar qué pasó con un pedido o una mesa',
                    'donde'  => 'Historial',
                    'ruta'   => 'historial',
                    'roles'  => ['cajero'],
                    'alias'  => 'historial buscar reclamo auditoria auditoría quien cuando anulado bitacora bitácora',
                    'pasos'  => [
                        'Entrá a Historial.',
                        'Buscá por número de pedido, mesa, cliente o producto.',
                        'Filtrá por quién lo hizo o por tipo de evento, y elegí el día en la columna de la izquierda.',
                    ],
                    'nota' => 'Es el registro de lo que pasó: no se edita ni se borra. Sirve para los reclamos del mostrador.',
                ],
                [
                    'titulo' => 'Ver cómo viene el negocio',
                    'donde'  => 'Reportes',
                    'ruta'   => 'reportes',
                    'roles'  => ['dueno'],
                    'alias'  => 'reporte reportes ventas estadisticas estadísticas mas vendidos medios de pago',
                    'pasos'  => [
                        'Entrá a Reportes y elegí Hoy, Semana o Mes.',
                        'Vas a ver ventas por hora, medios de pago, lo más vendido y cómo se reparte entre salón, pool, delivery y retiro.',
                        '«Imprimir» saca el informe en papel.',
                    ],
                    'nota' => 'Cada número se compara contra el período anterior, para que se note si subió o bajó.',
                ],
            ],

        ];
    }
}
