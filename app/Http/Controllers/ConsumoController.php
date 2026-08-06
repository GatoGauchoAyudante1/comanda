<?php

namespace App\Http\Controllers;

use App\Actions\CargarConsumos;
use App\Models\Category;
use App\Models\TableSession;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConsumoController extends Controller
{
    /** Grilla táctil de la carta. Ver mockups-html/03-consumo.html */
    public function mostrar(TableSession $sesion): View
    {
        return view('consumo', [
            'sesion'     => $sesion->load('table', 'order'),
            'categorias' => Category::query()
                ->where('active', true)
                ->with(['products' => fn ($q) => $q->where('active', true)->with('variants')])
                ->orderBy('sort_order')
                ->get()
                ->filter(fn ($c) => $c->products->isNotEmpty()),
        ]);
    }

    public function guardar(Request $request, TableSession $sesion, CargarConsumos $cargar): RedirectResponse
    {
        $datos = $request->validate([
            'lineas'                => ['required', 'array', 'min:1'],
            'lineas.*.product_id'   => ['required', 'integer', 'exists:products,id'],
            'lineas.*.variant_id'   => ['nullable', 'integer', 'exists:product_variants,id'],
            'lineas.*.qty'          => ['required', 'integer', 'min:1', 'max:99'],
            'lineas.*.notes'        => ['nullable', 'string', 'max:120'],
        ]);

        $cargar($sesion->order, $datos['lineas']);

        return redirect()
            ->route('mesa', $sesion)
            ->with('ok', 'Consumos cargados.');
    }

    public function quitar(TableSession $sesion, int $item): RedirectResponse
    {
        $linea = $sesion->order->items()->findOrFail($item);

        // Si ya está en cocina probablemente lo estén preparando (R-09).
        $aviso = $linea->status === 'kitchen'
            ? 'Ítem quitado. Avisale a cocina, ya lo tenían en pantalla.'
            : 'Ítem quitado.';

        $linea->delete();
        $sesion->order->refresh()->recalcular();

        return back()->with('ok', $aviso);
    }
}
