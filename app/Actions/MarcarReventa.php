<?php

namespace App\Actions;

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\RecipeItem;

/**
 * Deja un producto listo para descontar stock vendiéndose tal cual se compra.
 *
 * Una 7UP de 1,5 L no «lleva receta»: es ella misma. Pero el sistema necesita
 * la línea igual, porque DescontarStock recorre `recipe` y con la receta vacía
 * no mueve nada aunque `tracks_stock` esté prendido. Ver docs/05-modulo-stock.md
 * · reventa 1:1.
 *
 * La ceremonia la hace el sistema, no el dueño: esto se llama solo al cargar
 * la carta de un cliente (CargarDatos) y desde el comando de backfill. El
 * botón de la pantalla de recetas es sólo la salida manual para los casos que
 * el default no acierta.
 *
 * Crea el insumo con el nombre del producto, medido en unidades. El costo
 * arranca en cero a propósito: lo fija la primera compra, y hasta entonces el
 * margen se muestra al 100% — visiblemente falso, que es lo que empuja a
 * cargarlo.
 */
class MarcarReventa
{
    /**
     * @return Ingredient|null  null si el producto ya tenía receta: no se pisa
     *                          nada, un trago cargado a mano manda sobre el default
     */
    public function __invoke(Product $producto): ?Ingredient
    {
        if ($producto->recipe()->exists()) {
            return null;
        }

        $insumo = Ingredient::firstOrCreate(
            ['name' => $producto->name],
            [
                'base_unit' => 'un',
                'area'      => $producto->goes_to_kitchen ? 'cocina' : 'barra',
                'active'    => true,
            ],
        );

        RecipeItem::create([
            'product_id'    => $producto->id,
            'ingredient_id' => $insumo->id,
            'qty'           => 1,
        ]);

        // Sin esto la receta existe pero no descuenta (DescontarStock corta antes).
        $producto->forceFill(['tracks_stock' => true])->save();

        return $insumo;
    }
}
