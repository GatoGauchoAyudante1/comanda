<?php

namespace App\Http\Controllers;

use App\Actions\CobrarPedido;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Plata;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class CobroController extends Controller
{
    public function __construct(private CobrarPedido $cobrar) {}

    /** Frena el reloj y abre la pantalla de cobro. */
    public function iniciar(Order $orden): RedirectResponse
    {
        try {
            $this->cobrar->iniciar($orden);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('cobro', $orden);
    }

    /** Ver mockups-html/04-cobro.html */
    public function mostrar(Order $orden): View
    {
        $orden->load(['items.product', 'payments.user', 'tableSession.table']);

        return view('cobro', ['orden' => $orden]);
    }

    public function agregarPago(Request $request, Order $orden): RedirectResponse
    {
        $datos = $request->validate([
            'method'    => ['required', 'in:cash,qr,transfer,debit,credit,other'],
            'amount'    => ['required', 'numeric', 'min:0.01'],
            'received'  => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:60'],
        ]);

        try {
            $this->cobrar->agregarPago(
                orden: $orden,
                metodo: $datos['method'],
                importe: Plata::aCentavos($datos['amount']),
                usuario: $request->user(),
                recibido: isset($datos['received']) ? Plata::aCentavos($datos['received']) : null,
                referencia: $datos['reference'] ?? null,
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Pago cargado.');
    }

    public function quitarPago(Order $orden, Payment $pago): RedirectResponse
    {
        abort_unless($pago->order_id === $orden->id, 404);

        $pago->delete();

        return back()->with('ok', 'Pago quitado.');
    }

    public function confirmar(Order $orden): RedirectResponse
    {
        try {
            $avisos = $this->cobrar->confirmar($orden);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        // Se va derecho al ticket con el diálogo de impresión abierto:
        // es lo que hace un POS de verdad al confirmar un cobro.
        return redirect()
            ->route('ticket', ['orden' => $orden, 'imprimir' => 1])
            ->with('ok', $avisos !== [] ? implode(' ', $avisos) : null);
    }

    public function cancelar(Order $orden): RedirectResponse
    {
        try {
            $this->cobrar->cancelar($orden);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $orden->tableSession
            ? redirect()->route('mesa', $orden->tableSession)
            : redirect()->route('panel');
    }
}
