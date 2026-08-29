<?php

namespace App\Actions;

use App\Events\PedidoListo;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\Bitacora;
use App\Support\Negocio;
use App\Support\Plata;
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

        // Marcar listo es el mismo permiso acá que en la pantalla de cocina
        // (R-36). El tablero de pedidos es otra puerta a la misma decisión:
        // sin este chequeo, cualquiera que entre al tablero despacha un plato
        // que nadie cocinó. Va en la acción y no en el controlador porque es
        // el único lugar por el que pasan todos los cambios de estado.
        if ($nuevoEstado === 'ready' && ! Negocio::puedeMarcarListo($usuario)) {
            throw new RuntimeException('No tenés permiso para marcar pedidos como listos.');
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

        // Entregar es lo que genera el cobro (ver entregar()). Sin medio de pago
        // ese cobro se registraría a ciegas y el arqueo cerraría mal: mejor
        // frenar acá y que alguien pregunte cómo abonó el cliente.
        if ($nuevoEstado === 'delivered' && $orden->delivery && ! $orden->delivery->payment_method) {
            throw new RuntimeException('Antes de entregar hay que indicar cómo pagó el cliente.');
        }

        return DB::transaction(function () use ($orden, $nuevoEstado, $usuario, $repartidorId) {
            $avisos = [];

            $anterior = $orden->status;

            if ($repartidorId && $orden->delivery) {
                $previo = $orden->delivery->driver?->name;
                $nuevo  = User::find($repartidorId)?->name;

                $orden->delivery->update(['driver_id' => $repartidorId]);

                Bitacora::registrar(
                    'pedido.asignado',
                    $previo && $previo !== $nuevo
                        ? "Reasignó el pedido de {$previo} a {$nuevo}"
                        : "Asignó el pedido a {$nuevo}",
                    $orden,
                    ['repartidor' => $nuevo, 'anterior' => $previo],
                    $usuario,
                );
            }

            match ($nuevoEstado) {
                'ready'    => PedidoListo::dispatch($orden),
                'on_route' => $orden->delivery?->update([
                    'dispatched_at' => Carbon::now(),
                    'dispatched_by' => $usuario->id,
                ]),
                default    => null,
            };

            $orden->update(['status' => $nuevoEstado]);

            Bitacora::registrar(
                "pedido.{$nuevoEstado}",
                match ($nuevoEstado) {
                    'kitchen'   => 'Devolvió el pedido a cocina',
                    'ready'     => 'Marcó el pedido como listo',
                    'on_route'  => 'Despachó el pedido a la calle'
                        . ($orden->delivery?->driver ? " con {$orden->delivery->driver->name}" : ''),
                    'delivered' => 'Confirmó la entrega del pedido',
                    default     => "Pasó el pedido a {$nuevoEstado}",
                },
                $orden,
                ['de' => $anterior, 'a' => $nuevoEstado],
                $usuario,
            );

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
        $orden->delivery?->update([
            'delivered_at' => Carbon::now(),
            'delivered_by' => $usuario->id,
        ]);

        // El stock se descuenta al cerrar, no al cargar (R-24). Para un
        // delivery, "cerrar" es el momento en que la comida sale y llega.
        $avisos = ($this->descontarStock)($orden);

        // Si todavía no se registró la plata, se registra ahora con el medio
        // que se acordó al tomar el pedido.
        if ($orden->saldo() > 0 && $orden->cash_session_id) {
            // El pedido con envío llega acá siempre con medio definido (se
            // valida arriba); el `?? 'cash'` cubre al mostrador, que no lo tiene.
            $metodo  = $orden->delivery?->payment_method ?? 'cash';
            $importe = $orden->saldo();

            Payment::create([
                'order_id'        => $orden->id,
                'cash_session_id' => $orden->cash_session_id,
                'user_id'         => $usuario->id,
                'method'          => $metodo,
                'amount'          => $importe,
                'received'        => $orden->delivery?->pays_with,
            ]);

            Bitacora::registrar(
                'cobro.registrado',
                'Registró el cobro de ' . Plata::format($importe) . " en {$metodo}",
                $orden,
                ['metodo' => $metodo, 'importe' => $importe],
                $usuario,
            );
        }

        $orden->refresh();

        if ($orden->estaSaldado()) {
            $orden->update(['status' => 'paid', 'closed_at' => Carbon::now()]);

            Bitacora::registrar(
                'pedido.cobrado',
                'Pedido cerrado y cobrado por ' . Plata::format($orden->total),
                $orden,
                ['total' => $orden->total],
                $usuario,
            );
        }

        return $avisos;
    }
}
