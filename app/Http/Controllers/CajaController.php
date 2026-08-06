<?php

namespace App\Http\Controllers;

use App\Actions\CerrarCaja;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Support\Plata;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use RuntimeException;

class CajaController extends Controller
{
    /** Si no hay turno abierto muestra la apertura; si hay, el cierre. */
    public function mostrar(): View
    {
        $caja = CashSession::actual();

        if (! $caja) {
            return view('caja.abrir');
        }

        $caja->load(['openedBy', 'movements.user']);

        return view('caja.cerrar', [
            'caja'       => $caja,
            'pendientes' => $caja->pendientes(),
        ]);
    }

    public function abrirTurno(Request $request): RedirectResponse
    {
        if (CashSession::actual()) {
            return back()->with('error', 'Ya hay un turno de caja abierto.');
        }

        $datos = $request->validate([
            'opening_float' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ]);

        CashSession::create([
            'opened_by'     => $request->user()->id,
            'opened_at'     => Carbon::now(),
            // El formulario recibe pesos; en la base van centavos (R-31).
            'opening_float' => (int) round($datos['opening_float'] * 100),
        ]);

        return redirect()->route('panel')->with('ok', 'Turno abierto.');
    }

    public function cerrarTurno(Request $request, CerrarCaja $cerrarCaja): RedirectResponse
    {
        $caja = CashSession::actual();

        if (! $caja) {
            return back()->with('error', 'No hay ningún turno abierto.');
        }

        $datos = $request->validate([
            'conteo'           => ['required', 'array'],
            'conteo.*'         => ['nullable', 'numeric', 'min:0'],
            'difference_note'  => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $caja = $cerrarCaja($caja, $datos['conteo'], $request->user(), $datos['difference_note'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $mensaje = $caja->difference === 0
            ? 'Turno cerrado. La caja cuadró exacta.'
            : 'Turno cerrado con una diferencia de ' . Plata::format($caja->difference) . '.';

        return redirect()->route('caja.cierre', $caja)->with('ok', $mensaje);
    }

    /** El cierre Z de un turno ya cerrado. Sólo lectura (R-22). */
    public function verCierre(CashSession $caja): View
    {
        abort_if($caja->abierta(), 404);

        $caja->load(['openedBy', 'closedBy', 'movements.user', 'settlements.driver']);

        return view('caja.cierre', ['caja' => $caja]);
    }

    public function registrarMovimiento(Request $request): RedirectResponse
    {
        $caja = CashSession::actual();

        if (! $caja) {
            return back()->with('error', 'No hay una caja abierta.');
        }

        $datos = $request->validate([
            'type'    => ['required', 'in:expense,withdrawal,deposit'],
            'amount'  => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'concept' => ['required', 'string', 'max:120'],
        ]);

        CashMovement::create([
            'cash_session_id' => $caja->id,
            'user_id'         => $request->user()->id,
            'type'            => $datos['type'],
            'amount'          => (int) round($datos['amount'] * 100),
            'concept'         => $datos['concept'],
        ]);

        return back()->with('ok', 'Movimiento registrado.');
    }
}
