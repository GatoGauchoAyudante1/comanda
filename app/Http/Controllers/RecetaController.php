<?php

namespace App\Http\Controllers;

use App\Actions\MarcarReventa;
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
    /**
     * Índice del módulo. Es la puerta de entrada del rail.
     *
     * Antes se llegaba a las recetas sólo desde el botón «Ver receta» de
     * /stock, que se dibuja a partir de produccionPosible() — y ésa sólo
     * devuelve productos que YA tienen receta. En un negocio nuevo no había
     * forma de cargar la primera.
     */
    public function index(Request $request): View
    {
        // Las dos formas de pensar la misma tabla. Ver docs/02-decisiones.md · D-09.
        $vista = $request->string('vista')->toString() === 'insumo' ? 'insumo' : 'producto';

        $productos = Product::query()
            ->where('active', true)
            ->with(['recipe.ingredient', 'category'])
            ->orderBy('name')
            ->get();

        [$conReceta, $sinReceta] = $productos->partition(fn (Product $p) => $p->recipe->isNotEmpty());

        // La reventa 1:1 tiene línea, pero no es una receta: no hay nada que
        // componer. Mezclarla en la tabla la llenaba de bebidas — 17 de 33
        // filas — y tapaba justamente lo que esta pantalla existe para mostrar.
        [$reventas, $conReceta] = $conReceta->partition(fn (Product $p) => $p->esReventa());

        // Ordenados por margen: lo primero que el dueño quiere ver es lo que menos deja.
        $conReceta = $conReceta->sortBy(fn (Product $p) => $p->margen() ?? INF)->values();

        // El promedio sale sólo de lo que se prepara. Las reventas arrancan en
        // costo 0 y darían 100% cada una, subiendo un promedio que sirve para
        // decidir precios de cocina.
        $margenes = $conReceta->map->margen()->filter(fn (?float $m) => $m !== null);

        // Los que no controlan stock no necesitan receta: no son un pendiente.
        $pendientes = $sinReceta->where('tracks_stock', true)->values();

        // El pendiente se lee distinto según de dónde salga el producto: a una
        // pizza le falta la receta, a una Corona le falta decir de qué insumo
        // sale. El dato es el mismo (docs/05-modulo-stock.md · reventa 1:1),
        // pero «cargá la receta de la Corona» no le dice nada al dueño.
        [$pendCocina, $pendBarra] = $pendientes->partition(fn (Product $p) => $p->goes_to_kitchen);

        return view('stock.recetas', [
            'vista'      => $vista,
            'conReceta'  => $conReceta,
            'pendientes' => $pendientes,
            'pendCocina' => $pendCocina->values(),
            'pendBarra'  => $pendBarra->values(),
            'reventas'   => $reventas->values(),
            'sueltos'    => $sinReceta->where('tracks_stock', false)->values(),
            'margenProm' => $margenes->isNotEmpty() ? round($margenes->avg(), 1) : null,

            // Para la vista por insumo: quién lo usa y en cuánto.
            'insumos'    => Ingredient::where('active', true)
                ->with('recipeItems.product')
                ->orderBy('name')
                ->get(),
            'destinos'   => $productos,
        ]);
    }

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

    /**
     * Salida manual de la reventa 1:1, para lo que el default no acierta.
     *
     * El caso normal ya viene resuelto de fábrica: CargarDatos marca como
     * reventa todo lo que no pasa por la cocina. Esto queda para el producto
     * que se dio de alta a mano después.
     */
    public function reventa(Product $producto, MarcarReventa $marcar): RedirectResponse
    {
        $insumo = $marcar($producto);

        if ($insumo === null) {
            return back()->with('error', "«{$producto->name}» ya tiene receta cargada.");
        }

        // Vuelve a la lista, no al detalle: las bebidas se marcan de a tanda y
        // sacar al dueño de la lista en cada click le duplica el trabajo.
        return back()->with(
            'ok',
            $insumo->wasRecentlyCreated
                ? "«{$producto->name}» queda como reventa. Se creó el insumo con el mismo nombre: cargale el stock y el costo desde Stock."
                : "«{$producto->name}» queda como reventa del insumo «{$insumo->name}».",
        );
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
