<?php

namespace App\Actions;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Anula una cuenta de mesa. Ver docs/06-reglas-negocio.md · R-06.
 *
 * No borra nada: la cuenta queda en estado `cancelled` con el motivo, el
 * usuario y la hora. Anular destruye facturación, así que tiene que poder
 * revisarse después (R-32).
 */
class AnularMesa
{
    public function __construct(private DescontarStock $descontarStock) {}

    /**
     * @param  bool  $seConsumio  si lo cargado se sirvió igual, se registra como merma
     * @return array<int, string>  advertencias de stock
     */
    public function __invoke(Order $orden, User $usuario, string $motivo, bool $seConsumio): array
    {
        if ($orden->status === 'cancelled') {
            throw new RuntimeException('Esta cuenta ya está anulada.');
        }

        if ($orden->status === 'paid') {
            throw new RuntimeException('Esta cuenta ya se cobró. No se puede anular.');
        }

        // Si hay plata imputada, el arqueo ya cuenta con ella.
        if ($orden->payments()->exists()) {
            throw new RuntimeException('Hay pagos cargados. Quitalos antes de anular.');
        }

        return DB::transaction(function () use ($orden, $usuario, $motivo, $seConsumio) {
            $sesion = $orden->tableSession;

            if ($sesion && $sesion->abierta()) {
                $sesion->update(['ended_at' => Carbon::now()]);
            }

            $orden->update([
                'status'        => 'cancelled',
                'cancel_reason' => $motivo,
                'cancelled_by'  => $usuario->id,
                'cancelled_at'  => Carbon::now(),
                'closed_at'     => Carbon::now(),
            ]);

            // Lo que se sirvió y no se cobró es merma, no una venta.
            // Si la mesa se abrió por error y no se consumió nada, no toca el stock.
            if (! $seConsumio || $orden->items()->doesntExist()) {
                return [];
            }

            return ($this->descontarStock)(
                $orden->refresh(),
                tipo: 'waste',
                motivo: "Mesa anulada · pedido #{$orden->number} · {$motivo}",
            );
        });
    }
}
