<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se cerro y cobro una cuenta de mesa.
 *
 * Nadie lo escucha todavia: las pantallas se enteran por polling.
 * Ver docs/02-decisiones.md D-18.
 */
class MesaCobrada
{
    use Dispatchable;

    public function __construct(public Order $orden) {}
}