<?php

namespace App\Http\Controllers;

use App\Actions\TomarPedido;
use App\Models\Category;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Zone;
use App\Support\Negocio;
use App\Support\Plata;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PedidoOnlineController extends Controller
{
    public function carta(): View
    {
        abort_unless(Negocio::cartaPublica() && Negocio::modulo('delivery'), 404);

        return view('pedidos-online.carta', [
            'negocio' => Negocio::nombre(),
            'mensaje' => Negocio::cartaMensaje(),
            'categorias' => $this->categorias(),
        ]);
    }

    public function checkout(Request $request): View
    {
        abort_unless(Negocio::cartaPublica() && Negocio::modulo('delivery'), 404);

        $lineas = $this->validarLineas($request);

        return view('pedidos-online.checkout', [
            'negocio' => Negocio::nombre(),
            'lineas' => $lineas,
            'subtotal' => collect($lineas)->sum(fn ($linea) => $linea['unit_price'] * $linea['qty']),
            'zonas' => Zone::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        abort_unless(Negocio::cartaPublica() && Negocio::modulo('delivery'), 404);

        $datos = $request->validate([
            'type' => ['required', 'in:delivery,retiro'],
            'telefono' => ['required', 'string', 'max:40'],
            'nombre' => ['required', 'string', 'max:120'],
            'calle' => ['nullable', 'required_if:type,delivery', 'string', 'max:160'],
            'detalle' => ['nullable', 'string', 'max:120'],
            'zone_id' => ['nullable', 'exists:zones,id'],
            'metodo_pago' => ['nullable', 'in:cash,qr,transfer,debit,credit'],
            'paga_con' => ['nullable', 'numeric', 'min:0'],
            'notas' => ['nullable', 'string', 'max:300'],
        ]);
        $lineas = $this->validarLineas($request);
        $zona = $datos['type'] === 'delivery' && ! empty($datos['zone_id'])
            ? Zone::where('active', true)->find($datos['zone_id'])
            : null;
        $itemsTotal = collect($lineas)->sum(fn ($linea) => $linea['unit_price'] * $linea['qty']);
        $envio = $datos['type'] === 'delivery' ? ($zona?->delivery_fee ?? 0) : 0;

        $pedido = DB::transaction(function () use ($datos, $lineas, $itemsTotal, $envio) {
            $pedido = OnlineOrder::create([
                'uuid' => (string) Str::uuid(),
                'status' => 'pending',
                'fulfillment_type' => $datos['type'],
                'phone' => $datos['telefono'],
                'customer_name' => $datos['nombre'],
                'street' => $datos['type'] === 'delivery' ? ($datos['calle'] ?? null) : null,
                'address_detail' => $datos['type'] === 'delivery' ? ($datos['detalle'] ?? null) : null,
                'zone_id' => $datos['type'] === 'delivery' ? ($datos['zone_id'] ?? null) : null,
                'payment_method' => $datos['metodo_pago'] ?? null,
                'pays_with' => ($datos['metodo_pago'] ?? null) === 'cash' && isset($datos['paga_con'])
                    ? Plata::aCentavos($datos['paga_con']) : null,
                'notes' => $datos['notas'] ?? null,
                'items_total' => $itemsTotal,
                'delivery_fee' => $envio,
                'total' => $itemsTotal + $envio,
            ]);

            foreach ($lineas as $linea) {
                $pedido->items()->create($linea);
            }

            return $pedido;
        });

        return redirect()->route('pedido-online.recibido', $pedido->uuid);
    }

    public function recibido(string $uuid): View
    {
        $pedido = OnlineOrder::where('uuid', $uuid)->firstOrFail();

        return view('pedidos-online.recibido', compact('pedido'));
    }

    public function index(): View
    {
        return view('pedidos-online.index', [
            'pedidos' => OnlineOrder::where('status', 'pending')
                ->with(['items', 'zone'])->oldest()->get(),
        ]);
    }

    public function mostrar(OnlineOrder $pedidoOnline): View
    {
        $pedidoOnline->load(['items', 'zone', 'order', 'responder']);

        return view('pedidos-online.mostrar', [
            'pedido' => $pedidoOnline,
            'mensajeConfirmacion' => "Hola {$pedidoOnline->customer_name}, confirmamos tu pedido. Estará listo en {minutos} minutos. Total: ".Plata::format($pedidoOnline->total).'. ¡Gracias!',
            'mensajeRechazo' => "Hola {$pedidoOnline->customer_name}, lamentablemente no podemos tomar tu pedido en este momento. Motivo: {motivo}",
        ]);
    }

    public function confirmar(Request $request, OnlineOrder $pedidoOnline, TomarPedido $tomar): RedirectResponse
    {
        $datos = $request->validate([
            'estimated_minutes' => ['required', 'integer', 'min:1', 'max:480'],
            'mensaje' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $pedido = DB::transaction(function () use ($pedidoOnline, $datos, $request, $tomar) {
                $pendiente = OnlineOrder::whereKey($pedidoOnline->id)->lockForUpdate()->firstOrFail();
                if ($pendiente->status !== 'pending') {
                    throw new RuntimeException('Este pedido online ya fue respondido.');
                }
                $pendiente->load('items');

                $orden = $tomar(
                    usuario: $request->user(),
                    tipo: $pendiente->fulfillment_type,
                    lineas: $pendiente->lineasParaPedido(),
                    telefono: $pendiente->phone,
                    nombre: $pendiente->customer_name,
                    calle: $pendiente->street,
                    detalle: $pendiente->address_detail,
                    zonaId: $pendiente->zone_id,
                    metodoPago: $pendiente->payment_method,
                    pagaCon: $pendiente->pays_with,
                    notas: $pendiente->notes,
                );

                $mensaje = str_replace('{minutos}', (string) $datos['estimated_minutes'], $datos['mensaje']);
                $pendiente->update([
                    'status' => 'accepted',
                    'estimated_minutes' => $datos['estimated_minutes'],
                    'response_message' => $mensaje,
                    'order_id' => $orden->id,
                    'responded_by' => $request->user()->id,
                    'responded_at' => now(),
                ]);

                return [$pendiente, $mensaje];
            });
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->away($pedido[0]->whatsappUrl($pedido[1]));
    }

    public function rechazar(Request $request, OnlineOrder $pedidoOnline): RedirectResponse
    {
        $datos = $request->validate([
            'motivo' => ['required', 'string', 'max:300'],
            'mensaje' => ['required', 'string', 'max:1000'],
        ]);

        try {
            [$pedido, $mensaje] = DB::transaction(function () use ($pedidoOnline, $datos, $request) {
                $pedido = OnlineOrder::whereKey($pedidoOnline->id)->lockForUpdate()->firstOrFail();
                if ($pedido->status !== 'pending') {
                    throw new RuntimeException('Este pedido online ya fue respondido.');
                }
                $mensaje = str_replace('{motivo}', $datos['motivo'], $datos['mensaje']);
                $pedido->update([
                    'status' => 'rejected',
                    'rejection_reason' => $datos['motivo'],
                    'response_message' => $mensaje,
                    'responded_by' => $request->user()->id,
                    'responded_at' => now(),
                ]);

                return [$pedido, $mensaje];
            });
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->away($pedido->whatsappUrl($mensaje));
    }

    private function categorias()
    {
        return Category::query()->where('active', true)
            ->whereHas('products', fn ($q) => $q->where('active', true))
            ->with(['products' => fn ($q) => $q->where('active', true)->with('variants')])
            ->orderBy('sort_order')->get();
    }

    private function validarLineas(Request $request): array
    {
        $datos = $request->validate([
            'lineas' => ['required', 'array', 'min:1', 'max:100'],
            'lineas.*.product_id' => ['required', 'integer'],
            'lineas.*.variant_id' => ['nullable', 'integer'],
            'lineas.*.qty' => ['required', 'integer', 'min:1', 'max:99'],
            'lineas.*.notes' => ['nullable', 'string', 'max:120'],
        ]);

        $normalizadas = [];
        foreach ($datos['lineas'] as $i => $linea) {
            $producto = Product::where('active', true)->find($linea['product_id']);
            $variante = ! empty($linea['variant_id']) && $producto
                ? ProductVariant::where('product_id', $producto->id)->find($linea['variant_id']) : null;

            if (! $producto || (! empty($linea['variant_id']) && ! $variante)) {
                throw ValidationException::withMessages(["lineas.{$i}" => 'Uno de los productos ya no está disponible.']);
            }

            $normalizadas[] = [
                'product_id' => $producto->id,
                'product_variant_id' => $variante?->id,
                'product_name' => $producto->name,
                'variant_name' => $variante?->name,
                'qty' => (int) $linea['qty'],
                'unit_price' => $producto->price + ($variante?->price_delta ?? 0),
                'notes' => $linea['notes'] ?? null,
            ];
        }

        return $normalizadas;
    }
}
