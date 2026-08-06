<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Cocina marco el pedido como listo.
 *
 * Nadie lo escucha todavia: las pantallas se enteran por polling.
 * Ver docs/02-decisiones.md D-18.
 */
class PedidoListo
{
    use Dispatchable;

    public function __construct(public Order $orden) {}
}