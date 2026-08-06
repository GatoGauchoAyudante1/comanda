<?php

namespace App\Events;

use App\Models\TableSession;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se abrio una mesa y arranco el reloj.
 *
 * Nadie lo escucha todavia: las pantallas se enteran por polling.
 * Ver docs/02-decisiones.md D-18.
 */
class MesaAbierta
{
    use Dispatchable;

    public function __construct(public TableSession $sesion) {}
}