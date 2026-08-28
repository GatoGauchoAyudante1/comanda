<?php

namespace App\Actions;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use App\Support\Bitacora;
use RuntimeException;

/**
 * Define o corrige cómo va a pagar un delivery/retiro después de tomado
 * el pedido.
 *
 * Mucha gente no sabe con qué medio va a pagar hasta que el pedido llega
 * (R-15 lo deja opcional al tomar el pedido); esto permite definirlo o
 * corregirlo hasta el momento en que efectivamente se cobra.
 */
class CambiarMetodoPago
{
    public function __invoke(Order $orden, string $metodo, User $usuario, ?int $pagaCon = null): Delivery
    {
        $entrega = $orden->delivery;

        if (! $entrega) {
            throw new RuntimeException('Este pedido no tiene envío asociado.');
        }

        if (in_array($orden->status, ['delivered', 'paid', 'cancelled'], true)) {
            throw new RuntimeException('El pedido ya está cerrado, no se puede cambiar el medio de pago.');
        }

        $anterior = $entrega->payment_method;

        $entrega->update([
            'payment_method' => $metodo,
            'pays_with'      => $metodo === 'cash' ? $pagaCon : null,
        ]);

        Bitacora::registrar(
            'pedido.metodo_pago',
            $anterior
                ? "Cambió el medio de pago de {$anterior} a {$metodo}"
                : "Definió el medio de pago: {$metodo}",
            $orden,
            ['anterior' => $anterior, 'nuevo' => $metodo],
            $usuario,
        );

        return $entrega->refresh();
    }
}
