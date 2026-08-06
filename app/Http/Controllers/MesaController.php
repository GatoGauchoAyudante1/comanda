<?php

namespace App\Http\Controllers;

use App\Actions\AbrirMesa;
use App\Actions\AnularMesa;
use App\Models\Table;
use App\Models\TableRate;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class MesaController extends Controller
{
    /** Detalle de la mesa. Ver mockups-html/02-mesa.html */
    public function mostrar(TableSession $sesion): View
    {
        $sesion->load(['table', 'user', 'order.items.product', 'order.payments']);

        return view('mesa', ['sesion' => $sesion]);
    }

    /** Abre la mesa desde el diálogo del panel. */
    public function abrir(Request $request, Table $mesa, AbrirMesa $abrirMesa): RedirectResponse
    {
        $datos = $request->validate([
            'table_rate_id'  => ['nullable', 'exists:table_rates,id'],
            'minutos_atras'  => ['nullable', 'integer', 'min:0', 'max:240'],
            'guests'         => ['nullable', 'integer', 'min:1', 'max:50'],
            'reference'      => ['nullable', 'string', 'max:120'],
            'mozo_id'        => ['required', 'exists:users,id'],
            'cargar_consumo' => ['nullable', 'boolean'],
        ]);

        try {
            $sesion = $abrirMesa(
                mesa: $mesa,
                mozo: User::findOrFail($datos['mozo_id']),
                tarifa: isset($datos['table_rate_id']) ? TableRate::find($datos['table_rate_id']) : null,
                minutosAtras: (int) ($datos['minutos_atras'] ?? 0),
                comensales: $datos['guests'] ?? null,
                referencia: $datos['reference'] ?? null,
                registradoPor: $request->user(),
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('mesa', $sesion)
            ->with('ok', "{$mesa->name} abierta.");
    }

    public function pausar(TableSession $sesion): RedirectResponse
    {
        $sesion->pausada() ? $sesion->reanudar() : $sesion->pausar();

        return back()->with('ok', $sesion->pausada() ? 'Mesa pausada.' : 'Mesa reanudada.');
    }

    /** Anular la cuenta. Ver docs/06-reglas-negocio.md · R-06. */
    public function anular(Request $request, TableSession $sesion, AnularMesa $anular): RedirectResponse
    {
        $datos = $request->validate([
            'motivo'      => ['required', 'string', 'min:5', 'max:200'],
            'se_consumio' => ['required', 'boolean'],
        ]);

        try {
            $avisos = $anular(
                orden: $sesion->order,
                usuario: $request->user(),
                motivo: $datos['motivo'],
                seConsumio: (bool) $datos['se_consumio'],
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $mensaje = "{$sesion->table->name} anulada.";

        if ($datos['se_consumio']) {
            $mensaje .= ' Los consumos quedaron registrados como merma.';
        }

        return redirect()
            ->route('panel')
            ->with('ok', trim($mensaje . ' ' . implode(' ', $avisos)));
    }
}
