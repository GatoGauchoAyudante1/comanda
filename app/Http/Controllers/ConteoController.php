<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Models\StockMovement;
use App\Support\Plata;
use App\Support\Unidades;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Conteo físico de inventario. Ver mockups-html/10-conteo.html
 *
 * Es la pantalla que justifica el módulo: la diferencia valorizada en pesos
 * es lo que detecta robo, desperdicio y recetas mal cargadas.
 */
class ConteoController extends Controller
{
    public function mostrar(Request $request): View
    {
        $conteo = StockCount::where('status', 'open')->latest('id')->first();

        if ($conteo) {
            $conteo->load(['items.ingredient']);
        }

        return view('stock.conteo', [
            'conteo'  => $conteo,
            'areas'   => Ingredient::AREAS,
            'ultimos' => StockCount::where('status', 'closed')
                ->with('user')->latest('closed_at')->take(5)->get(),
        ]);
    }

    /** Arranca un conteo con una foto del stock teórico de ese momento. */
    public function abrir(Request $request): RedirectResponse
    {
        if (StockCount::where('status', 'open')->exists()) {
            return back()->with('error', 'Ya hay un conteo abierto.');
        }

        $datos = $request->validate([
            'area' => ['nullable', 'in:cocina,barra,descartables'],
        ]);

        $insumos = Ingredient::where('active', true)
            ->when($datos['area'] ?? null, fn ($q, $a) => $q->where('area', $a))
            ->orderBy('name')
            ->get();

        if ($insumos->isEmpty()) {
            return back()->with('error', 'No hay insumos para contar en esa área.');
        }

        DB::transaction(function () use ($request, $datos, $insumos) {
            $conteo = StockCount::create([
                'user_id' => $request->user()->id,
                'area'    => $datos['area'] ?? null,
                'status'  => 'open',
            ]);

            foreach ($insumos as $insumo) {
                // Se congela el stock teórico al abrir: si alguien vende
                // mientras se cuenta, la diferencia no se ensucia.
                StockCountItem::create([
                    'stock_count_id' => $conteo->id,
                    'ingredient_id'  => $insumo->id,
                    'expected_qty'   => $insumo->stock,
                ]);
            }
        });

        return redirect()->route('conteo')->with('ok', 'Conteo abierto. Recorré y cargá lo que hay.');
    }

    public function guardarItem(Request $request, StockCountItem $item): RedirectResponse
    {
        $datos = $request->validate([
            'contado' => ['nullable', 'numeric', 'min:0'],
            'unidad'  => ['required', 'string', 'max:5'],
        ]);

        $insumo = $item->ingredient;

        if ($datos['contado'] === null || $datos['contado'] === '') {
            $item->update(['counted_qty' => null, 'difference_value' => 0]);

            return back();
        }

        $contado    = Unidades::aBase((float) $datos['contado'], $datos['unidad'], $insumo->base_unit);
        $diferencia = $contado - (float) $item->expected_qty;

        $item->update([
            'counted_qty'      => $contado,
            'difference_value' => (int) round($diferencia * $insumo->cost),
        ]);

        return back();
    }

    /** Cierra el conteo y genera los ajustes. Ver R-26. */
    public function cerrar(Request $request, StockCount $conteo): RedirectResponse
    {
        if ($conteo->status !== 'open') {
            return back()->with('error', 'Este conteo ya está cerrado.');
        }

        $contados = $conteo->items()->whereNotNull('counted_qty')->with('ingredient')->get();

        if ($contados->isEmpty()) {
            return back()->with('error', 'Todavía no cargaste ningún insumo.');
        }

        DB::transaction(function () use ($conteo, $contados, $request) {
            foreach ($contados as $item) {
                $insumo     = $item->ingredient;
                $diferencia = (float) $item->counted_qty - (float) $item->expected_qty;

                if (abs($diferencia) < 0.0001) {
                    continue;
                }

                // El conteo no sobreescribe: genera un ajuste y queda el rastro (R-26).
                StockMovement::create([
                    'ingredient_id' => $insumo->id,
                    'type'          => 'adjustment',
                    'qty'           => $diferencia,
                    'cost'          => $insumo->cost,
                    'user_id'       => $request->user()->id,
                    'reason'        => "Conteo de inventario #{$conteo->id}",
                    'source_type'   => StockCount::class,
                    'source_id'     => $conteo->id,
                ]);

                $insumo->update(['stock' => $item->counted_qty]);
            }

            $conteo->update([
                'status'           => 'closed',
                'difference_value' => (int) $contados->sum('difference_value'),
                'closed_at'        => Carbon::now(),
            ]);
        });

        $conteo->refresh();

        return redirect()->route('stock')->with('ok',
            'Conteo cerrado. Diferencia de ' . Plata::format($conteo->difference_value) .
            ' sobre ' . $contados->count() . ' insumos.');
    }
}
