<?php

namespace App\Http\Controllers;

use App\Actions\AvanzarPedido;
use App\Actions\TomarPedido;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Models\Zone;
use App\Support\Plata;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PedidoController extends Controller
{
    /** Tablero por estados. Ver mockups-html/06-delivery-tablero.html */
    public function tablero(AvanzarPedido $avanzar): View
    {
        $pedidos = Order::query()
            ->whereIn('type', ['delivery', 'retiro'])
            ->whereIn('status', ['open', 'kitchen', 'ready', 'on_route'])
            ->with(['delivery.customer', 'delivery.zone', 'delivery.driver', 'items'])
            ->orderBy('created_at')
            ->get();

        return view('pedidos.tablero', [
            'columnas' => [
                'kitchen'  => ['titulo' => 'En cocina', 'pedidos' => $pedidos->whereIn('status', ['open', 'kitchen'])],
                'ready'    => ['titulo' => 'Listos',    'pedidos' => $pedidos->where('status', 'ready')],
                'on_route' => ['titulo' => 'En viaje',  'pedidos' => $pedidos->where('status', 'on_route')],
            ],
            'repartidores' => User::where('role', 'repartidor')->where('active', true)->get(),
            'avanzar'      => $avanzar,
            'delDia'       => Order::whereIn('type', ['delivery', 'retiro'])
                ->where('status', 'paid')
                ->whereDate('created_at', today()),
        ]);
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
            'metodo_pago'         => ['required', 'in:cash,qr,transfer,debit,credit'],
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
                metodoPago: $datos['metodo_pago'],
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

    public function avanzar(Request $request, Order $orden, AvanzarPedido $avanzar): RedirectResponse
    {
        $datos = $request->validate([
            'estado'    => ['required', 'string'],
            'driver_id' => ['nullable', 'exists:users,id'],
        ]);

        try {
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
}
