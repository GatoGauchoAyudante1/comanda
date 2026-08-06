<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Hoy nadie lo escucha: el KDS se entera por polling (docs/02-decisiones.md · D-18).
 *
 * El día que se justifique Laravel Reverb, esta clase implementa ShouldBroadcast
 * y no hay que tocar ni un controlador.
 */
class PedidoEnviadoACocina
{
    use Dispatchable;

    /** @param  array<int>  $itemIds  los ítems que se acaban de mandar */
    public function __construct(
        public Order $orden,
        public array $itemIds = [],
    ) {}
}
