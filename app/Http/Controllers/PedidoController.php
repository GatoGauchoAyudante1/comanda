<?php

namespace App\Http\Controllers;

use App\Actions\AvanzarPedido;
use App\Actions\CambiarMetodoPago;
use App\Actions\TomarPedido;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Models\Zone;
use App\Support\Bitacora;
use App\Support\Negocio;
use App\Support\Plata;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PedidoController extends Controller
{
    /**
     * Tablero de todo lo que está en curso. Ver mockups-html/06-delivery-tablero.html
     *
     * Incluye pedidos de mesa además de delivery y retiro: el cajero quiere un
     * solo lugar donde ver qué se está cocinando, venga de donde venga.
     *
     * La diferencia es cómo se mueven. Delivery y retiro tienen estados propios
     * (R-16) y avanzan con botones. Las mesas no: su `status` queda en `open`
     * mientras el cliente consume, y lo que avanza es el estado de cada ítem.
     * Por eso a las mesas se las ubica por sus ítems.
     */
    public function tablero(Request $request, AvanzarPedido $avanzar): View
    {
        $relaciones = [
            'items.product', 'items.variant',
            'delivery.customer', 'delivery.zone', 'delivery.driver',
            'tableSession.table', 'tableSession.user',
        ];

        $paraLlevar = Order::query()
            ->whereIn('type', ['delivery', 'retiro'])
            ->whereIn('status', ['open', 'kitchen', 'ready', 'on_route'])
            ->with($relaciones)
            ->orderBy('created_at')
            ->get();

        // Mesas y mostrador con algo pasando por cocina.
        $enSalon = Order::query()
            ->whereIn('type', ['mesa_pool', 'mesa_salon', 'mostrador'])
            ->where('status', 'open')
            ->whereHas('items', fn ($q) => $q->whereIn('status', ['kitchen', 'ready']))
            ->with($relaciones)
            ->orderBy('created_at')
            ->get();

        $enCocina = $enSalon->filter(fn (Order $o) => $o->items->contains('status', 'kitchen'));
        $servir   = $enSalon->reject(fn (Order $o) => $o->items->contains('status', 'kitchen'));

        return view('pedidos.tablero', [
            'columnas' => [
                'kitchen' => [
                    'titulo'  => 'En cocina',
                    'pedidos' => $paraLlevar->whereIn('status', ['open', 'kitchen'])->concat($enCocina),
                ],
                'ready' => [
                    'titulo'  => 'Listos',
                    'pedidos' => $paraLlevar->where('status', 'ready')->concat($servir),
                ],
                'on_route' => [
                    'titulo'  => 'En viaje',
                    'pedidos' => $paraLlevar->where('status', 'on_route'),
                ],
            ],
            'repartidores' => User::where('role', 'repartidor')->where('active', true)->get(),
            'avanzar'      => $avanzar,
            // Mismo permiso que en la pantalla de cocina (R-36): el tablero no
            // ofrece «Listo» a quien no puede marcarlo. La acción igual lo
            // frena, pero un botón que siempre falla no debería estar ahí.
            'puedeMarcarListo' => Negocio::puedeMarcarListo($request->user()),
            'delDia'       => Order::whereIn('type', ['delivery', 'retiro'])
                ->where('status', 'paid')
                ->whereDate('created_at', today()),
        ]);
    }

    /**
     * La comida de una mesa salió a la mesa.
     *
     * Saca la comanda del tablero sin cerrar la cuenta: el cliente sigue
     * sentado y puede pedir más.
     */
    public function servido(Order $orden): RedirectResponse
    {
        abort_unless($orden->esMesa() || $orden->type === 'mostrador', 404);

        $servidos = $orden->items()->where('status', 'ready')->with('product')->get();

        $orden->items()->where('status', 'ready')->update(['status' => 'delivered']);

        Bitacora::registrar(
            'pedido.servido',
            'Sirvió a la mesa: ' . $servidos->map(fn ($i) => "{$i->qty}x {$i->product->name}")->join(', '),
            $orden,
            ['items' => $servidos->pluck('id')],
        );

        return back()->with('ok', "Comanda #{$orden->number} servida.");
    }

    /** Alta de pedido. Ver mockups-html/05-delivery-nuevo.html */
    public function nuevo(): View
    {
        return view('pedidos.nuevo', [
            'zonas'      => Zone::where('active', true)->orderBy('name')->get(),
            'categorias' => Category::query()
                ->where('active', true)
                ->with(['products' => fn ($q) => $q->where('active', true)->with('variants')])
                ->orderBy('sort_order')
                ->get()
                ->filter(fn ($c) => $c->products->isNotEmpty()),
        ]);
    }

    /** Autocompletado por teléfono. Ver docs/06-reglas-negocio.md · R-14. */
    public function buscarCliente(Request $request): JsonResponse
    {
        $cliente = Customer::porTelefono($request->string('telefono')->toString());

        // Se responde con una bandera explícita: `response()->json(null)`
        // devuelve `{}`, que en JavaScript es truthy y haría aparecer el
        // cartel de "cliente conocido" para un teléfono que no existe.
        if (! $cliente) {
            return response()->json(['encontrado' => false]);
        }

        $cliente->load(['direccionPrincipal.zone']);

        return response()->json([
            'encontrado' => true,
            'nombre'   => $cliente->name,
            'pedidos'  => $cliente->deliveries()->count(),
            'calle'    => $cliente->direccionPrincipal?->street,
            'detalle'  => $cliente->direccionPrincipal?->detail,
            'zona_id'  => $cliente->direccionPrincipal?->zone_id,
            'zona'     => $cliente->direccionPrincipal?->zone?->name,
        ]);
    }

    public function guardar(Request $request, TomarPedido $tomar): RedirectResponse
    {
        $datos = $request->validate([
            'type'                => ['required', 'in:delivery,retiro'],
            'telefono'            => ['required', 'string', 'max:40'],
            'nombre'              => ['nullable', 'string', 'max:120'],
            'calle'               => ['nullable', 'string', 'max:160'],
            'detalle'             => ['nullable', 'string', 'max:120'],
            'zone_id'             => ['nullable', 'exists:zones,id'],
            'metodo_pago'         => ['nullable', 'in:cash,qr,transfer,debit,credit'],
            'paga_con'            => ['nullable', 'numeric', 'min:0'],
            'notas'               => ['nullable', 'string', 'max:300'],
            'lineas'              => ['required', 'array', 'min:1'],
            'lineas.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lineas.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'lineas.*.qty'        => ['required', 'integer', 'min:1', 'max:99'],
            'lineas.*.notes'      => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $orden = $tomar(
                usuario: $request->user(),
                tipo: $datos['type'],
                lineas: $datos['lineas'],
                telefono: $datos['telefono'],
                nombre: $datos['nombre'] ?? null,
                calle: $datos['calle'] ?? null,
                detalle: $datos['detalle'] ?? null,
                zonaId: $datos['zone_id'] ?? null,
                metodoPago: $datos['metodo_pago'] ?? null,
                pagaCon: isset($datos['paga_con']) ? Plata::aCentavos($datos['paga_con']) : null,
                notas: $datos['notas'] ?? null,
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('pedidos')
            ->with('ok', "Pedido #{$orden->number} tomado por " . Plata::format($orden->total) . '.');
    }

    /**
     * Mover el pedido al estado siguiente (o al anterior).
     *
     * Al entregar puede venir el medio de pago: si el pedido llegó hasta acá
     * sin definirlo, el tablero lo pregunta en ese momento y lo manda junto
     * con el estado, porque sin eso no se puede registrar el cobro.
     */
    public function avanzar(Request $request, Order $orden, AvanzarPedido $avanzar, CambiarMetodoPago $cambiar): RedirectResponse
    {
        $datos = $request->validate([
            'estado'      => ['required', 'string'],
            'driver_id'   => ['nullable', 'exists:users,id'],
            'metodo_pago' => ['nullable', 'in:cash,qr,transfer,debit,credit'],
            'paga_con'    => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            if (! empty($datos['metodo_pago'])) {
                $cambiar(
                    $orden,
                    $datos['metodo_pago'],
                    $request->user(),
                    isset($datos['paga_con']) ? Plata::aCentavos($datos['paga_con']) : null,
                );
            }

            $avisos = $avanzar($orden, $datos['estado'], $request->user(), $datos['driver_id'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $textos = [
            'kitchen'   => "Pedido #{$orden->number} vuelve a cocina.",
            'ready'     => "Pedido #{$orden->number} listo.",
            'on_route'  => "Pedido #{$orden->number} salió a la calle.",
            'delivered' => "Pedido #{$orden->number} entregado y cobrado.",
        ];

        return back()->with('ok', trim(($textos[$datos['estado']] ?? 'Actualizado.') . ' ' . implode(' ', $avisos)));
    }

    /** Definir o corregir el medio de pago después de tomado el pedido. */
    public function metodoPago(Request $request, Order $orden, CambiarMetodoPago $cambiar): RedirectResponse
    {
        $datos = $request->validate([
            'metodo_pago' => ['required', 'in:cash,qr,transfer,debit,credit'],
            'paga_con'    => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $cambiar(
                $orden,
                $datos['metodo_pago'],
                $request->user(),
                isset($datos['paga_con']) ? Plata::aCentavos($datos['paga_con']) : null,
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Medio de pago actualizado.');
    }
}
