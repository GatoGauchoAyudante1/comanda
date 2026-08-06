<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\RecipeItem;
use App\Support\Unidades;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Editor de recetas. Ver mockups-html/09-receta.html
 *
 * La decisión de fondo está en docs/02-decisiones.md · D-09: el dueño carga
 * "de una horma me salen 40 pizzas" y el sistema guarda 200 g por pizza.
 */
class RecetaController extends Controller
{
    public function mostrar(Product $producto): View
    {
        $producto->load(['recipe.ingredient', 'category', 'variants']);

        return view('stock.receta', [
            'producto'   => $producto,
            'insumos'    => Ingredient::where('active', true)->orderBy('name')->get(),
            'produccion' => $producto->produccionPosible(),
            'sinReceta'  => Product::where('active', true)
                ->where('tracks_stock', true)
                ->doesntHave('recipe')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function guardarLinea(Request $request, Product $producto): RedirectResponse
    {
        $datos = $request->validate([
            'ingredient_id'     => ['required', 'exists:ingredients,id'],
            'modo'              => ['required', 'in:rendimiento,cantidad'],
            'unidad'            => ['required', 'string', 'max:5'],
            // modo rendimiento
            'contenido'         => ['nullable', 'numeric', 'min:0.0001'],
            'rinde'             => ['nullable', 'integer', 'min:1'],
            // modo cantidad
            'cantidad'          => ['nullable', 'numeric', 'min:0.0001'],
            'only_for_delivery' => ['nullable', 'boolean'],
        ]);

        $insumo = Ingredient::findOrFail($datos['ingredient_id']);

        // Los dos modos terminan en lo mismo: cantidad por unidad vendida,
        // en unidad base. El rendimiento es sólo una forma más cómoda de decirlo.
        if ($datos['modo'] === 'rendimiento') {
            if (empty($datos['contenido']) || empty($datos['rinde'])) {
                return back()->with('error', 'Indicá el contenido del envase y cuántas unidades rinde.');
            }

            $enBase = Unidades::aBase((float) $datos['contenido'], $datos['unidad'], $insumo->base_unit);
            $qty    = RecipeItem::desdeRendimiento($enBase, (int) $datos['rinde']);
        } else {
            if (empty($datos['cantidad'])) {
                return back()->with('error', 'Indicá la cantidad por unidad.');
            }

            $qty = Unidades::aBase((float) $datos['cantidad'], $datos['unidad'], $insumo->base_unit);
        }

        if ($qty <= 0) {
            return back()->with('error', 'La cantidad calculada dio cero. Revisá los números.');
        }

        RecipeItem::updateOrCreate(
            ['product_id' => $producto->id, 'ingredient_id' => $insumo->id],
            ['qty' => $qty, 'only_for_delivery' => $request->boolean('only_for_delivery')],
        );

        return back()->with('ok', "«{$insumo->name}» cargado: " .
            Unidades::legible($qty, $insumo->base_unit, 3) . ' por unidad.');
    }

    public function borrarLinea(Product $producto, RecipeItem $linea): RedirectResponse
    {
        abort_unless($linea->product_id === $producto->id, 404);

        $nombre = $linea->ingredient->name;
        $linea->delete();

        return back()->with('ok', "«{$nombre}» quitado de la receta.");
    }
}
