<?php

namespace App\Actions;

use App\Models\Address;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Alta de un pedido de delivery o retiro.
 *
 * Reglas (docs/06-reglas-negocio.md):
 *   R-13  sin caja abierta no se opera
 *   R-14  el teléfono es la identidad del cliente
 *   R-15  el costo de envío se copia de la zona y queda congelado
 */
class TomarPedido
{
    public function __construct(private CargarConsumos $cargarConsumos) {}

    /**
     * @param  array<int, array{product_id:int, variant_id?:int|null, qty:int, notes?:string|null}>  $lineas
     */
    public function __invoke(
        User $usuario,
        string $tipo,
        array $lineas,
        string $telefono,
        ?string $nombre = null,
        ?string $calle = null,
        ?string $detalle = null,
        ?int $zonaId = null,
        string $metodoPago = 'cash',
        ?int $pagaCon = null,
        ?string $notas = null,
    ): Order {
        $caja = CashSession::actual();

        if (! $caja) {
            throw new RuntimeException('No hay una caja abierta. Abrí el turno antes de tomar pedidos.');
        }

        if ($lineas === []) {
            throw new RuntimeException('El pedido no tiene productos.');
        }

        if ($tipo === 'delivery' && ! $calle) {
            throw new RuntimeException('Falta la dirección de entrega.');
        }

        return DB::transaction(function () use (
            $caja, $usuario, $tipo, $lineas, $telefono, $nombre,
            $calle, $detalle, $zonaId, $metodoPago, $pagaCon, $notas
        ) {
            $cliente = $this->cliente($telefono, $nombre);
            $zona    = $zonaId ? Zone::find($zonaId) : null;

            $direccion = $tipo === 'delivery'
                ? $this->direccion($cliente, $calle, $detalle, $zona?->id)
                : null;

            $envio = $tipo === 'delivery' ? ($zona?->delivery_fee ?? 0) : 0;

            $orden = Order::create([
                'type'            => $tipo,
                'status'          => 'open',
                'number'          => Order::siguienteNumero($caja),
                'business_date'   => $caja->opened_at->toDateString(),
                'cash_session_id' => $caja->id,
                'user_id'         => $usuario->id,
                'delivery_fee'    => $envio,      // copiado de la zona (R-15)
                'notes'           => $notas,
            ]);

            Delivery::create([
                'order_id'       => $orden->id,
                'customer_id'    => $cliente->id,
                'address_id'     => $direccion?->id,
                'zone_id'        => $zona?->id,
                'fee'            => $envio,
                'payment_method' => $metodoPago,
                'pays_with'      => $metodoPago === 'cash' ? $pagaCon : null,
            ]);

            ($this->cargarConsumos)($orden, $lineas);

            // Si algo va a cocina, el pedido arranca ahí; si no, ya está listo.
            $orden->refresh();
            $orden->update([
                'status' => $orden->items()->where('status', 'kitchen')->exists() ? 'kitchen' : 'ready',
            ]);

            return $orden->refresh();
        });
    }

    /** El teléfono identifica al cliente: si existe se actualiza, si no se crea (R-14). */
    private function cliente(string $telefono, ?string $nombre): Customer
    {
        $limpio = preg_replace('/\D/', '', $telefono);

        $cliente = Customer::firstOrNew(['phone' => $limpio]);

        if ($nombre && $nombre !== $cliente->name) {
            $cliente->name = $nombre;
        }

        $cliente->save();

        return $cliente;
    }

    /** Reutiliza la dirección si ya la tenía cargada; si no, la agrega. */
    private function direccion(Customer $cliente, string $calle, ?string $detalle, ?int $zonaId): Address
    {
        $existente = $cliente->addresses()->where('street', $calle)->first();

        if ($existente) {
            $existente->update(['detail' => $detalle, 'zone_id' => $zonaId]);

            return $existente;
        }

        $cliente->addresses()->update(['is_default' => false]);

        return $cliente->addresses()->create([
            'street'     => $calle,
            'detail'     => $detalle,
            'zone_id'    => $zonaId,
            'is_default' => true,
        ]);
    }
}
