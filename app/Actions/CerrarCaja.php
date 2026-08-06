<?php

namespace App\Actions;

use App\Models\CashSession;
use App\Models\User;
use App\Support\Bitacora;
use App\Support\Plata;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cierra el turno de caja con el arqueo del efectivo.
 *
 * Reglas (docs/06-reglas-negocio.md):
 *   R-18  sólo se arquea el efectivo; QR y tarjeta se concilian con el banco
 *   R-19  fórmula del efectivo esperado
 *   R-20  una diferencia distinta de cero pide explicación, pero no bloquea
 *   R-21  se avisa de pendientes, tampoco bloquea
 *   R-22  una vez cerrado, el turno no se modifica
 */
class CerrarCaja
{
    /**
     * @param  array<string, int>  $conteo  denominación en pesos => cantidad, más 'monedas' => importe en pesos
     */
    public function __invoke(
        CashSession $caja,
        array $conteo,
        User $usuario,
        ?string $explicacion = null,
    ): CashSession {
        if (! $caja->abierta()) {
            throw new RuntimeException('Este turno ya está cerrado.');
        }

        $contado  = $this->contar($conteo);
        $esperado = $caja->efectivoEsperado();

        return DB::transaction(function () use ($caja, $conteo, $contado, $esperado, $usuario, $explicacion) {
            $caja->update([
                'closed_by'        => $usuario->id,
                'closed_at'        => Carbon::now(),
                'expected_cash'    => $esperado,
                'counted_cash'     => $contado,
                'difference'       => $contado - $esperado,
                'difference_note'  => $explicacion,
                'bill_breakdown'   => $conteo,
            ]);

            $diferencia = $contado - $esperado;

            Bitacora::registrar(
                'caja.cerrada',
                'Cerró el turno · esperaba ' . Plata::format($esperado)
                    . ' · contó ' . Plata::format($contado)
                    . ($diferencia === 0
                        ? ' · cuadró exacta'
                        : ' · diferencia ' . Plata::format($diferencia))
                    . ($explicacion ? " · «{$explicacion}»" : ''),
                $caja,
                ['esperado' => $esperado, 'contado' => $contado, 'diferencia' => $diferencia],
                $usuario,
            );

            return $caja->refresh();
        });
    }

    /** Suma el conteo de billetes, en centavos. */
    public function contar(array $conteo): int
    {
        $total = 0;

        foreach (config('negocio.billetes') as $denominacion) {
            $cantidad = max(0, (int) ($conteo[(string) $denominacion] ?? 0));
            $total   += $denominacion * $cantidad * 100;
        }

        // Las monedas se cargan como importe, no como cantidad: nadie las cuenta de a una.
        return $total + Plata::aCentavos($conteo['monedas'] ?? 0);
    }
}
