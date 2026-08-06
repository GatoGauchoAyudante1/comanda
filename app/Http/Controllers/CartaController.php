<?php

namespace App\Http\Controllers;

use App\Actions\AjustarPrecios;
use App\Models\Category;
use App\Models\Product;
use App\Support\Plata;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CartaController extends Controller
{
    public function index(Request $request): View
    {
        $categorias = Category::withCount('products')->orderBy('sort_order')->get();
        $actual     = $categorias->firstWhere('id', $request->integer('categoria')) ?? $categorias->first();

        $productos = Product::query()
            ->when($actual, fn ($q) => $q->where('category_id', $actual->id))
            ->with('recipe.ingredient', 'variants')
            ->orderBy('sort_order')
            ->get();

        return view('carta', [
            'categorias' => $categorias,
            'actual'     => $actual,
            'productos'  => $productos,
            'sinReceta'  => Product::where('active', true)
                ->where('tracks_stock', true)
                ->doesntHave('recipe')
                ->count(),
        ]);
    }

    public function guardarProducto(Request $request, ?Product $producto = null): RedirectResponse
    {
        $datos = $request->validate([
            'name'            => ['required', 'string', 'max:120', Rule::unique('products', 'name')->ignore($producto)],
            'category_id'     => ['required', 'exists:categories,id'],
            'price'           => ['required', 'numeric', 'min:0'],
            'goes_to_kitchen' => ['nullable', 'boolean'],
            'tracks_stock'    => ['nullable', 'boolean'],
        ]);

        $valores = [
            ...$datos,
            'price'           => Plata::aCentavos($datos['price']),
            'goes_to_kitchen' => $request->boolean('goes_to_kitchen'),
            'tracks_stock'    => $request->boolean('tracks_stock'),
        ];

        if ($producto?->exists) {
            $producto->update($valores);
            $mensaje = "«{$producto->name}» actualizado.";
        } else {
            $producto = Product::create([...$valores, 'active' => true]);
            $mensaje  = "«{$producto->name}» agregado a la carta.";
        }

        return redirect()
            ->route('carta', ['categoria' => $producto->category_id])
            ->with('ok', $mensaje);
    }

    /** Los switches de la tabla: activo y "va a cocina". */
    public function alternar(Request $request, Product $producto): RedirectResponse
    {
        $campo = $request->validate([
            'campo' => ['required', 'in:active,goes_to_kitchen'],
        ])['campo'];

        $producto->update([$campo => ! $producto->$campo]);

        return back();
    }

    public function guardarCategoria(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:60', 'unique:categories,name'],
        ]);

        $categoria = Category::create([
            ...$datos,
            'goes_to_kitchen' => $request->boolean('goes_to_kitchen'),
            'sort_order'      => (Category::max('sort_order') ?? 0) + 1,
            'active'          => true,
        ]);

        return redirect()
            ->route('carta', ['categoria' => $categoria->id])
            ->with('ok', "Categoría «{$categoria->name}» creada.");
    }

    public function ajustarPrecios(Request $request, AjustarPrecios $ajustar): RedirectResponse
    {
        $datos = $request->validate([
            'porcentaje'  => ['required', 'numeric', 'min:-90', 'max:200', 'not_in:0'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'redondeo'    => ['required', 'integer', 'in:0,10,50,100,500'],
        ]);

        $cuantos = $ajustar(
            porcentaje: (float) $datos['porcentaje'],
            categoriaId: $datos['category_id'] ?: null,
            redondeo: (int) $datos['redondeo'],
        );

        $signo = $datos['porcentaje'] > 0 ? 'Subieron' : 'Bajaron';

        return back()->with('ok', "{$signo} {$cuantos} precios un {$datos['porcentaje']}%.");
    }
}
