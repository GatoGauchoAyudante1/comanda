<?php

namespace App\Actions;

use App\Models\CashSession;
use App\Models\DriverSettlement;
use App\Models\Payment;
use App\Models\User;
use App\Support\Bitacora;
use App\Support\Plata;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * El repartidor entrega la plata que cobró en la calle.
 *
 * Cierra el circuito del efectivo: hasta que rinde, lo que cobró no está en
 * la caja aunque el pedido figure cobrado. Ver docs/06-reglas-negocio.md · R-17.
 */
class RendirCaja
{
    /** Pagos en efectivo que el repartidor todavía no entregó. */
    public function pendientes(User $repartidor, CashSession $caja)
    {
        return Payment::query()
            ->where('cash_session_id', $caja->id)
            ->where('method', 'cash')
            ->whereNull('settlement_id')
            ->whereHas('order.delivery', fn ($q) => $q->where('driver_id', $repartidor->id))
            ->get();
    }

    public function aRendir(User $repartidor, CashSession $caja): int
    {
        return (int) $this->pendientes($repartidor, $caja)->sum('amount');
    }

    /**
     * @param  int  $entregado  lo que el repartidor pone sobre el mostrador
     */
    public function __invoke(User $repartidor, int $entregado, User $recibe): DriverSettlement
    {
        $caja = CashSession::actual();

        if (! $caja) {
            throw new RuntimeException('No hay una caja abierta.');
        }

        $pagos = $this->pendientes($repartidor, $caja);

        if ($pagos->isEmpty()) {
            throw new RuntimeException('No tenés nada para rendir.');
        }

        $esperado = (int) $pagos->sum('amount');

        return DB::transaction(function () use ($repartidor, $caja, $pagos, $esperado, $entregado, $recibe) {
            $rendicion = DriverSettlement::create([
                'driver_id'        => $repartidor->id,
                'cash_session_id'  => $caja->id,
                'deliveries_count' => $pagos->count(),
                'cash_expected'    => $esperado,
                'cash_received'    => $entregado,
                'difference'       => $entregado - $esperado,
                'settled_at'       => Carbon::now(),
                'received_by'      => $recibe->id,
            ]);

            // Al quedar ligados a la rendición, estos pagos dejan de restarse
            // del arqueo: la plata ya está en el cajón.
            Payment::whereIn('id', $pagos->pluck('id'))
                ->update(['settlement_id' => $rendicion->id]);

            $diferencia = $entregado - $esperado;

            Bitacora::registrar(
                'cadete.rindio',
                "{$repartidor->name} rindió " . Plata::format($entregado)
                    . " de {$pagos->count()} envíos ante {$recibe->name}"
                    . ($diferencia !== 0
                        ? ' · diferencia ' . Plata::format($diferencia)
                        : ' · sin diferencia'),
                $caja,
                [
                    'repartidor' => $repartidor->name,
                    'recibio'    => $recibe->name,
                    'esperado'   => $esperado,
                    'entregado'  => $entregado,
                    'diferencia' => $diferencia,
                ],
                $repartidor,
            );

            return $rendicion;
        });
    }
}
