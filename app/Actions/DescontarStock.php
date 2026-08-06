<?php

namespace App\Actions;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * Descuenta los insumos de un pedido según las recetas.
 *
 * Reglas (docs/06-reglas-negocio.md):
 *   R-23  el stock NO bloquea la venta: si queda negativo, avisa pero deja pasar
 *   R-24  se descuenta al cerrar el pedido, no ítem por ítem
 *
 * Detalles del cálculo (docs/05-modulo-stock.md):
 *   - la variante escala la receta por `recipe_factor` (la chica lleva el 60%)
 *   - los insumos `only_for_delivery` sólo se descuentan en delivery y retiro
 *   - mitad y mitad descuenta media receta de cada uno
 *
 * También se usa al anular una mesa que ya consumió: ahí el movimiento es
 * `waste` en vez de `sale`, porque los insumos se gastaron y no se cobraron.
 *
 * @return array<int, string>  advertencias para mostrar al cajero
 */
class DescontarStock
{
    /**
     * @param  string       $tipo    `sale` en un cobro, `waste` en una anulación
     * @param  string|null  $motivo  obligatorio en `waste` (R-25)
     */
    public function __invoke(Order $orden, string $tipo = 'sale', ?string $motivo = null): array
    {
        $esParaLlevar = in_array($orden->type, ['delivery', 'retiro'], true);
        $avisos       = [];

        DB::transaction(function () use ($orden, $esParaLlevar, $tipo, $motivo, &$avisos) {
            $orden->loadMissing(['items.product.recipe.ingredient', 'items.variant', 'items.halfProduct.recipe']);

            // Se acumula por insumo para no generar 20 movimientos del mismo queso.
            $consumo = [];

            foreach ($orden->items as $item) {
                if (! $item->product->tracks_stock) {
                    continue;
                }

                $factor = $item->variant?->recipe_factor ?? 1.0;

                // Mitad y mitad: media receta de cada producto.
                $mitades = $item->esMitadYMitad()
                    ? [[$item->product, 0.5], [$item->halfProduct, 0.5]]
                    : [[$item->product, 1.0]];

                foreach ($mitades as [$producto, $parte]) {
                    foreach ($producto->recipe as $receta) {
                        if ($receta->only_for_delivery && ! $esParaLlevar) {
                            continue;
                        }

                        $cantidad = $receta->qty * $item->qty * $factor * $parte;

                        $consumo[$receta->ingredient_id] ??= ['insumo' => $receta->ingredient, 'qty' => 0.0];
                        $consumo[$receta->ingredient_id]['qty'] += $cantidad;
                    }
                }
            }

            foreach ($consumo as $linea) {
                $insumo   = $linea['insumo'];
                $cantidad = round($linea['qty'], 4);

                if ($cantidad <= 0) {
                    continue;
                }

                StockMovement::create([
                    'ingredient_id' => $insumo->id,
                    'type'          => $tipo,
                    'qty'           => -$cantidad,
                    'cost'          => $insumo->cost,
                    'user_id'       => $orden->cancelled_by ?? $orden->user_id,
                    'reason'        => $motivo,
                    'source_type'   => Order::class,
                    'source_id'     => $orden->id,
                ]);

                $insumo->decrement('stock', $cantidad);
                $insumo->refresh();

                // Avisa, pero nunca frena la venta (R-23).
                if ($insumo->stock < 0) {
                    $avisos[] = "{$insumo->name} quedó en negativo ({$insumo->stock} {$insumo->base_unit}). Revisá el stock.";
                } elseif ($insumo->bajoMinimo()) {
                    $avisos[] = "{$insumo->name} quedó bajo el mínimo.";
                }
            }
        });

        return $avisos;
    }
}
