<?php

/*
|--------------------------------------------------------------------------
| Configuración del negocio
|--------------------------------------------------------------------------
|
| Nada específico de un cliente se escribe en el código. Todo lo que cambia
| de una instalación a otra vive acá y se alimenta del .env.
|
| Está PROHIBIDO escribir condicionales por nombre de cliente en el código.
| Ver docs/02-decisiones.md · D-02 y docs/06-reglas-negocio.md · R-30.
|
*/

return [

    'nombre' => env('NEGOCIO_NOMBRE', 'Mi negocio'),

    /*
    | Módulos activos. La barra lateral, el panel de atención y los reportes
    | se arman según esto. Ver docs/02-decisiones.md · D-04.
    */
    'modulos' => [
        'salon'    => env('NEGOCIO_MODULO_SALON', true),
        'pool'     => env('NEGOCIO_MODULO_POOL', false),
        'delivery' => env('NEGOCIO_MODULO_DELIVERY', false),
        'stock'    => env('NEGOCIO_MODULO_STOCK', true),
    ],

    /*
    | Valores por defecto. Una vez que exista la tabla `settings`, estos
    | pasan a ser sólo el respaldo inicial de los seeders.
    */
    'pool' => [
        'tarifa_por_hora'    => env('NEGOCIO_POOL_TARIFA', 400000), // centavos
        'fraccion_minutos'   => env('NEGOCIO_POOL_FRACCION', 30),
    ],

    'comprobante' => [
        'punto_venta' => env('NEGOCIO_PUNTO_VENTA', '0001'),
    ],

    /*
    | Denominaciones para el arqueo, en pesos y de mayor a menor.
    | Van acá porque cambian con el tiempo: si mañana sale un billete nuevo,
    | se agrega una línea y no se toca ni el código ni la base.
    */
    'billetes' => [20000, 10000, 2000, 1000, 500, 200, 100],

];
