<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\StockMovement;
use App\Support\Plata;
use App\Support\Unidades;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StockController extends Controller
{
    /** Ver mockups-html/08-stock.html */
    public function index(Request $request): View
    {
        $area = $request->string('area')->toString() ?: null;

        $insumos = Ingredient::query()
            ->where('active', true)
            ->when($area === 'minimo', fn ($q) => $q->whereColumn('stock', '<', 'min_stock'))
            ->when($area && $area !== 'minimo', fn ($q) => $q->where('area', $area))
            ->with(['recipeItems.product'])
            ->orderBy('name')
            ->get();

        $todos = Ingredient::where('active', true)->get();

        // Cuántas unidades de cada producto se pueden hacer hoy, y qué lo frena.
        $produccion = Product::query()
            ->where('active', true)
            ->has('recipe')
            ->with('recipe.ingredient')
            ->get()
            ->map(fn (Product $p) => ['producto' => $p] + $p->produccionPosible())
            ->sortBy('unidades')
            ->values();

        return view('stock.index', [
            'insumos'    => $insumos,
            'area'       => $area,
            'produccion' => $produccion,
            'valorTotal' => (int) $todos->sum->valor(),
            'bajoMinimo' => $todos->filter->bajoMinimo(),
            'mermaMes'   => (int) round(
                StockMovement::where('type', 'waste')
                    ->whereMonth('created_at', now()->month)
                    ->get()
                    ->sum(fn ($m) => abs($m->qty) * $m->cost)
            ),
        ]);
    }

    public function guardarInsumo(Request $request, ?Ingredient $insumo = null): RedirectResponse
    {
        $datos = $request->validate([
            'name'      => ['required', 'string', 'max:120', Rule::unique('ingredients', 'name')->ignore($insumo)],
            'base_unit' => ['required', Rule::in(Ingredient::UNIDADES)],
            'area'      => ['required', Rule::in(Ingredient::AREAS)],
            'min_stock' => ['required', 'numeric', 'min:0'],
            'cost'      => ['required', 'numeric', 'min:0'],
            'unidad'    => ['required', 'string', 'max:5'],
        ]);

        // El costo se escribe por la unidad que el usuario eligió
        // (ej. $8.900 el kilo) y se guarda por unidad base (por gramo).
        $porBase = Unidades::aBase(1, $datos['unidad'], $datos['base_unit']) ?: 1;

        $valores = [
            'name'      => $datos['name'],
            'base_unit' => $datos['base_unit'],
            'area'      => $datos['area'],
            'min_stock' => Unidades::aBase((float) $datos['min_stock'], $datos['unidad'], $datos['base_unit']),
            'cost'      => (int) round(Plata::aCentavos($datos['cost']) / $porBase),
        ];

        if ($insumo?->exists) {
            $insumo->update($valores);
            $mensaje = "«{$insumo->name}» actualizado.";
        } else {
            $insumo  = Ingredient::create([...$valores, 'stock' => 0, 'active' => true]);
            $mensaje = "«{$insumo->name}» agregado. Cargá el stock con una compra o un conteo.";
        }

        return back()->with('ok', $mensaje);
    }

    /** Merma o rotura. Exige motivo (R-25). */
    public function registrarMerma(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'ingredient_id' => ['required', 'exists:ingredients,id'],
            'cantidad'      => ['required', 'numeric', 'min:0.001'],
            'unidad'        => ['required', 'string', 'max:5'],
            'reason'        => ['required', 'string', 'min:4', 'max:200'],
        ]);

        $insumo   = Ingredient::findOrFail($datos['ingredient_id']);
        $cantidad = Unidades::aBase((float) $datos['cantidad'], $datos['unidad'], $insumo->base_unit);

        StockMovement::create([
            'ingredient_id' => $insumo->id,
            'type'          => 'waste',
            'qty'           => -$cantidad,
            'cost'          => $insumo->cost,
            'user_id'       => $request->user()->id,
            'reason'        => $datos['reason'],
        ]);

        $insumo->decrement('stock', $cantidad);

        return back()->with('ok', 'Merma registrada por ' .
            Plata::format((int) round($cantidad * $insumo->cost)) . '.');
    }

    /** Compra: suma stock y actualiza el costo al último precio (P-05). */
    public function registrarCompra(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'ingredient_id' => ['required', 'exists:ingredients,id'],
            'cantidad'      => ['required', 'numeric', 'min:0.001'],
            'unidad'        => ['required', 'string', 'max:5'],
            'total'         => ['required', 'numeric', 'min:0'],
        ]);

        $insumo   = Ingredient::findOrFail($datos['ingredient_id']);
        $cantidad = Unidades::aBase((float) $datos['cantidad'], $datos['unidad'], $insumo->base_unit);
        $total    = Plata::aCentavos($datos['total']);
        $costo    = $cantidad > 0 ? (int) round($total / $cantidad) : $insumo->cost;

        StockMovement::create([
            'ingredient_id' => $insumo->id,
            'type'          => 'purchase',
            'qty'           => $cantidad,
            'cost'          => $costo,
            'user_id'       => $request->user()->id,
            'reason'        => 'Compra',
        ]);

        $insumo->increment('stock', $cantidad);
        $insumo->update(['cost' => $costo]);   // último precio de compra

        return back()->with('ok', "Compra cargada. «{$insumo->name}» ahora cuesta " .
            Plata::format($costo) . ' por ' . $insumo->base_unit . '.');
    }
}
