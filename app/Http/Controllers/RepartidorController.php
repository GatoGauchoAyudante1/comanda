<?php

namespace App\Http\Controllers;

use App\Actions\AvanzarPedido;
use App\Actions\CambiarMetodoPago;
use App\Actions\RendirCaja;
use App\Models\CashSession;
use App\Models\DriverSettlement;
use App\Models\Order;
use App\Support\Plata;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * La pantalla del cadete en su celular. Ver mockups-html/14-repartidor.html
 */
class RepartidorController extends Controller
{
    public function index(Request $request, RendirCaja $rendir): View
    {
        $yo   = $request->user();
        $caja = CashSession::actual();

        $envios = Order::query()
            ->where('type', 'delivery')
            ->whereIn('status', ['on_route'])
            ->whereHas('delivery', fn ($q) => $q->where('driver_id', $yo->id))
            ->with(['delivery.customer', 'delivery.address.zone', 'items'])
            ->orderBy('created_at')
            ->get();

        $entregadosHoy = Order::query()
            ->where('type', 'delivery')
            ->whereIn('status', ['delivered', 'paid'])
            ->whereHas('delivery', fn ($q) => $q->where('driver_id', $yo->id)
                ->whereDate('delivered_at', today()))
            ->count();

        return view('repartidor', [
            'envios'        => $envios,
            'entregados'    => $entregadosHoy,
            'aRendir'       => $caja ? $rendir->aRendir($yo, $caja) : 0,
            'rendiciones'   => $caja
                ? DriverSettlement::where('driver_id', $yo->id)
                    ->where('cash_session_id', $caja->id)->latest('id')->get()
                : collect(),
        ]);
    }

    public function entregar(Request $request, Order $orden, AvanzarPedido $avanzar): RedirectResponse
    {
        // Sólo sus propios envíos. Ver docs/06-reglas-negocio.md · R-29.
        abort_unless($orden->delivery?->driver_id === $request->user()->id, 403);

        try {
            $avanzar($orden, 'delivered', $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', "Pedido #{$orden->number} entregado.");
    }

    /** El cadete confirma o corrige cómo paga el cliente, hasta que lo entrega. */
    public function metodoPago(Request $request, Order $orden, CambiarMetodoPago $cambiar): RedirectResponse
    {
        // Sólo sus propios envíos. Ver docs/06-reglas-negocio.md · R-29.
        abort_unless($orden->delivery?->driver_id === $request->user()->id, 403);

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

    public function rendir(Request $request, RendirCaja $rendirCaja): RedirectResponse
    {
        $datos = $request->validate([
            'entregado' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $rendicion = $rendirCaja(
                repartidor: $request->user(),
                entregado: Plata::aCentavos($datos['entregado']),
                recibe: $request->user(),
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $mensaje = 'Rendiste ' . Plata::format($rendicion->cash_received) . '.';

        if ($rendicion->difference !== 0) {
            $mensaje .= ' Diferencia de ' . Plata::format($rendicion->difference)
                      . ', avisale al cajero.';
        }

        return back()->with('ok', $mensaje);
    }
}
