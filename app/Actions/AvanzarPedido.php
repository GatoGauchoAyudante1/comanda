<?php

namespace App\Actions;

use App\Events\PedidoListo;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Mueve un pedido por sus estados. Ver docs/06-reglas-negocio.md · R-16.
 *
 *   open -> kitchen -> ready -> on_route -> delivered -> paid
 *
 * Los pedidos de retiro saltan `on_route`. Se puede retroceder un paso,
 * porque cocina marca "listo" por error más seguido de lo que uno cree.
 */
class AvanzarPedido
{
    public function __construct(private DescontarStock $descontarStock) {}

    /** El orden real del circuito, para saber qué sigue y qué es "atrás". */
    private const SECUENCIA = ['open', 'kitchen', 'ready', 'on_route', 'delivered'];

    public function siguiente(Order $orden): ?string
    {
        $pos = array_search($orden->status, self::SECUENCIA, true);

        if ($pos === false || $pos === count(self::SECUENCIA) - 1) {
            return null;
        }

        $proximo = self::SECUENCIA[$pos + 1];

        // El retiro y el mostrador no salen a la calle.
        if ($proximo === 'on_route' && $orden->type !== 'delivery') {
            return 'delivered';
        }

        return $proximo;
    }

    public function anterior(Order $orden): ?string
    {
        $pos = array_search($orden->status, self::SECUENCIA, true);

        return $pos > 0 ? self::SECUENCIA[$pos - 1] : null;
    }

    /**
     * @return array<int, string>  advertencias de stock
     */
    public function __invoke(Order $orden, string $nuevoEstado, User $usuario, ?int $repartidorId = null): array
    {
        if (in_array($orden->status, ['paid', 'cancelled'], true)) {
            throw new RuntimeException('Este pedido ya está cerrado.');
        }

        // Sólo se avanza o se retrocede de a un paso. Sin esto, un pedido
        // podría saltar de cocina a la calle sin que nadie lo haya preparado.
        $permitidos = array_filter([$this->siguiente($orden), $this->anterior($orden)]);

        if (! in_array($nuevoEstado, $permitidos, true)) {
            throw new RuntimeException(
                "No se puede pasar de «{$orden->status}» a «{$nuevoEstado}» directamente."
            );
        }

        if ($nuevoEstado === 'on_route' && ! $repartidorId && ! $orden->delivery?->driver_id) {
            throw new RuntimeException('Asigná un repartidor antes de mandarlo a la calle.');
        }

        return DB::transaction(function () use ($orden, $nuevoEstado, $usuario, $repartidorId) {
            $avisos = [];

            if ($repartidorId && $orden->delivery) {
                $orden->delivery->update(['driver_id' => $repartidorId]);
            }

            match ($nuevoEstado) {
                'ready'    => PedidoListo::dispatch($orden),
                'on_route' => $orden->delivery?->update(['dispatched_at' => Carbon::now()]),
                default    => null,
            };

            $orden->update(['status' => $nuevoEstado]);

            if ($nuevoEstado === 'delivered') {
                $avisos = $this->entregar($orden, $usuario);
            }

            return $avisos;
        });
    }

    /**
     * Entregar cierra el circuito: sale la mercadería, se descuenta el stock
     * y se registra el cobro en efectivo que hizo el repartidor.
     */
    private function entregar(Order $orden, User $usuario): array
    {
        $orden->delivery?->update(['delivered_at' => Carbon::now()]);

        // El stock se descuenta al cerrar, no al cargar (R-24). Para un
        // delivery, "cerrar" es el momento en que la comida sale y llega.
        $avisos = ($this->descontarStock)($orden);

        // Si todavía no se registró la plata, se registra ahora con el medio
        // que se acordó al tomar el pedido.
        if ($orden->saldo() > 0 && $orden->cash_session_id) {
            Payment::create([
                'order_id'        => $orden->id,
                'cash_session_id' => $orden->cash_session_id,
                'user_id'         => $usuario->id,
                'method'          => $orden->delivery?->payment_method ?? 'cash',
                'amount'          => $orden->saldo(),
                'received'        => $orden->delivery?->pays_with,
            ]);
        }

        $orden->refresh();

        if ($orden->estaSaldado()) {
            $orden->update(['status' => 'paid', 'closed_at' => Carbon::now()]);
        }

        return $avisos;
    }
}
