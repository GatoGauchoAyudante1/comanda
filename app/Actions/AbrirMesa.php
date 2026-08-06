<?php

namespace App\Actions;

use App\Models\CashSession;
use App\Models\Order;
use App\Models\Table;
use App\Models\TableRate;
use App\Models\TableSession;
use App\Models\User;
use App\Support\Bitacora;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Abre una mesa: crea la cuenta y arranca el reloj si es de pool.
 *
 * Reglas implementadas (docs/06-reglas-negocio.md):
 *   R-03  la tarifa se COPIA a la sesión, no se referencia
 *   R-04  `started_at` puede retrasarse, y queda auditado quién lo hizo
 *   R-13  sin caja abierta no se opera
 */
class AbrirMesa
{
    /**
     * @param  int  $minutosAtras  cuánto antes arrancó realmente el reloj
     */
    public function __invoke(
        Table $mesa,
        User $mozo,
        ?TableRate $tarifa = null,
        int $minutosAtras = 0,
        ?int $comensales = null,
        ?string $referencia = null,
        ?User $registradoPor = null,
    ): TableSession {
        $caja = CashSession::actual();

        if (! $caja) {
            throw new RuntimeException('No hay una caja abierta. Abrí el turno antes de operar.');
        }

        if ($mesa->sesionAbierta()->exists()) {
            throw new RuntimeException("{$mesa->name} ya está ocupada.");
        }

        if ($mesa->esPool() && ! $tarifa) {
            $tarifa = TableRate::where('active', true)->orderByDesc('is_default')->first();

            if (! $tarifa) {
                throw new RuntimeException('No hay ninguna tarifa de pool cargada.');
            }
        }

        $minutosAtras = max(0, min($minutosAtras, 240)); // tope sano: 4 horas
        $inicio       = Carbon::now()->subMinutes($minutosAtras);

        return DB::transaction(function () use (
            $mesa, $mozo, $tarifa, $inicio, $minutosAtras, $comensales, $referencia, $registradoPor, $caja
        ) {
            $orden = Order::create([
                'type'            => $mesa->esPool() ? 'mesa_pool' : 'mesa_salon',
                'status'          => 'open',
                'number'          => Order::siguienteNumero($caja),
                'business_date'   => $caja->opened_at->toDateString(),
                'cash_session_id' => $caja->id,
                'user_id'         => $mozo->id,
            ]);

            $sesion = TableSession::create([
                'table_id'   => $mesa->id,
                'order_id'   => $orden->id,
                'user_id'    => $mozo->id,
                'started_at' => $inicio,

                // Copia congelada de la tarifa (R-03).
                'rate_name'             => $tarifa?->name,
                'rate_price_per_hour'   => $tarifa?->price_per_hour ?? 0,
                'rate_rounding_minutes' => $tarifa?->rounding_minutes ?? 30,

                'guests'    => $comensales,
                'reference' => $referencia,

                // Sólo se audita si de verdad se retrasó el arranque (R-04).
                'start_adjusted_by' => $minutosAtras > 0
                    ? ($registradoPor?->id ?? $mozo->id)
                    : null,
            ]);

            Bitacora::registrar(
                'mesa.abierta',
                "Abrió {$mesa->name} · atiende {$mozo->name}"
                    . ($comensales ? " · {$comensales} personas" : '')
                    . ($minutosAtras > 0 ? " · reloj retrasado {$minutosAtras} min" : '')
                    . ($referencia ? " · «{$referencia}»" : ''),
                $orden,
                [
                    'mesa'           => $mesa->name,
                    'mozo'           => $mozo->name,
                    'tarifa'         => $tarifa?->name,
                    'precio_hora'    => $tarifa?->price_per_hour,
                    'minutos_atras'  => $minutosAtras,
                ],
                $registradoPor,
            );

            return $sesion;
        });
    }
}
