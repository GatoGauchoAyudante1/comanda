<?php

namespace App\Actions;

use App\Events\MesaCobrada;
use App\Models\CashSession;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * El cobro, en tres pasos separados a propósito.
 *
 * Reglas (docs/06-reglas-negocio.md):
 *   R-10  un pedido admite muchos pagos; no se cierra hasta saldar
 *   R-11  el vuelto se muestra pero no genera movimiento
 *   R-12  no se puede imputar más que el total
 *   R-13  todo cobro pertenece a un turno de caja
 *   R-24  el stock se descuenta recién al cerrar
 */
class CobrarPedido
{
    public function __construct(private DescontarStock $descontarStock) {}

    /**
     * Paso 1: frenar el reloj de la mesa.
     *
     * Si el reloj siguiera corriendo mientras el cliente paga, el total
     * cambiaría entre que se lo decís y que te da la plata.
     */
    public function iniciar(Order $orden): void
    {
        if ($orden->status !== 'open') {
            throw new RuntimeException('Esta cuenta ya no está abierta.');
        }

        DB::transaction(function () use ($orden) {
            $sesion = $orden->tableSession;

            if ($sesion && $sesion->abierta()) {
                $sesion->reanudar();                          // cierra la pausa si estaba pausada
                $sesion->update(['ended_at' => Carbon::now()]);
            }

            $orden->refresh()->recalcular();                  // congela time_amount
        });
    }

    /** Volver atrás: sólo si todavía no se cargó ningún pago. */
    public function cancelar(Order $orden): void
    {
        if ($orden->payments()->exists()) {
            throw new RuntimeException('Ya hay pagos cargados. Quitalos antes de cancelar el cobro.');
        }

        $sesion = $orden->tableSession;

        if ($sesion && ! $sesion->abierta()) {
            $sesion->update(['ended_at' => null]);
            $orden->refresh()->recalcular();
        }
    }

    /** Paso 2: imputar un pago. Se pueden combinar varios medios. */
    public function agregarPago(
        Order $orden,
        string $metodo,
        int $importe,
        User $usuario,
        ?int $recibido = null,
        ?string $referencia = null,
    ): Payment {
        $caja = CashSession::actual();

        if (! $caja) {
            throw new RuntimeException('No hay una caja abierta.');   // R-13
        }

        if ($importe < 1) {
            throw new RuntimeException('El importe tiene que ser mayor a cero.');
        }

        if ($importe > $orden->saldo()) {
            throw new RuntimeException('No se puede imputar más que el saldo pendiente.'); // R-12
        }

        return Payment::create([
            'order_id'        => $orden->id,
            'cash_session_id' => $caja->id,
            'user_id'         => $usuario->id,
            'method'          => $metodo,
            'amount'          => $importe,
            'received'        => $metodo === 'cash' ? $recibido : null,   // R-11
            'reference'       => $referencia,
        ]);
    }

    /**
     * Paso 3: cerrar. Recién acá se descuenta el stock.
     *
     * @return array<int, string>  advertencias de stock
     */
    public function confirmar(Order $orden): array
    {
        $orden->refresh();

        if (! $orden->estaSaldado()) {
            throw new RuntimeException('Todavía queda saldo pendiente.');
        }

        return DB::transaction(function () use ($orden) {
            $sesion = $orden->tableSession;

            if ($sesion && $sesion->abierta()) {
                $sesion->update(['ended_at' => Carbon::now()]);
                $orden->refresh()->recalcular();
            }

            $avisos = ($this->descontarStock)($orden);           // R-24

            $orden->update(['status' => 'paid', 'closed_at' => Carbon::now()]);

            MesaCobrada::dispatch($orden);

            return $avisos;
        });
    }
}
