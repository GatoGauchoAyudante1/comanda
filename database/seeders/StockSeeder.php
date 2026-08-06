<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\RecipeItem;
use Illuminate\Database\Seeder;

/**
 * Insumos y recetas de ejemplo.
 *
 * Arranca con pocos insumos a propósito: los que son el 80% del costo.
 * Cargar el inventario completo es la razón principal por la que estos
 * módulos se abandonan. Ver docs/05-modulo-stock.md.
 *
 * Las cantidades van SIEMPRE en unidad base (g, ml, un).
 * Los costos, en centavos por unidad base.
 */
class StockSeeder extends Seeder
{
    public function run(): void
    {
        $insumos = [
            // nombre                 unidad  stock     minimo   costo/unidad  area
            ['Queso muzzarella',      'g',    14200,    10000,   890,   'cocina'],
            ['Harina 000',            'g',     4500,    25000,   120,   'cocina'],
            ['Salsa de tomate',       'ml',   18000,     8000,   210,   'cocina'],
            ['Masa de pizza',         'un',      80,       40, 64000,   'cocina'],
            ['Carne picada',          'g',     6800,     5000,   940,   'cocina'],
            ['Jamón cocido',          'g',     3200,     2000,  1120,   'cocina'],
            ['Cerveza Quilmes 1 L',   'un',      48,       24, 210000,  'barra'],
            ['Coca 500',              'un',       9,       30,  52000,  'barra'],
            ['Fernet Branca 750',     'ml',    5250,     3000,  1973,   'barra'],
            ['Caja de pizza',         'un',     120,      100,  18000,  'descartables'],
        ];

        foreach ($insumos as [$nombre, $unidad, $stock, $minimo, $costo, $area]) {
            Ingredient::updateOrCreate(
                ['name' => $nombre],
                [
                    'base_unit' => $unidad,
                    'stock'     => $stock,
                    'min_stock' => $minimo,
                    'cost'      => $costo,
                    'area'      => $area,
                    'active'    => true,
                ],
            );
        }

        $ing = Ingredient::pluck('id', 'name');

        /*
         * Recetas. `qty` es la cantidad por unidad vendida, en unidad base.
         *
         * "De una horma de 8 kg me salen 40 pizzas" se guarda como
         * 8000 g / 40 = 200 g por pizza. Ver docs/02-decisiones.md · D-09.
         */
        $recetas = [
            'Pizza muzzarella' => [
                ['Queso muzzarella', 200],
                ['Masa de pizza', 1],
                ['Salsa de tomate', 80],
                ['Caja de pizza', 1, true],   // sólo en delivery
            ],
            'Pizza napolitana' => [
                ['Queso muzzarella', 180],
                ['Masa de pizza', 1],
                ['Salsa de tomate', 100],
                ['Caja de pizza', 1, true],
            ],
            'Pizza jamón y morrones' => [
                ['Queso muzzarella', 180],
                ['Jamón cocido', 60],
                ['Masa de pizza', 1],
                ['Salsa de tomate', 80],
                ['Caja de pizza', 1, true],
            ],
            'Empanadas de carne (docena)' => [
                ['Carne picada', 720],         // 60 g por empanada
            ],
            'Hamburguesa completa' => [
                ['Carne picada', 180],
                ['Queso muzzarella', 40],
                ['Jamón cocido', 30],
            ],
            // Reventa 1:1 — una receta de un solo insumo con qty = 1.
            'Quilmes 1 litro' => [['Cerveza Quilmes 1 L', 1]],
            'Coca 500'        => [['Coca 500', 1]],
            // Un trago: la medida de fernet son 70 ml.
            'Fernet con coca' => [['Fernet Branca 750', 70], ['Coca 500', 1]],
        ];

        foreach ($recetas as $nombreProducto => $lineas) {
            $producto = Product::where('name', $nombreProducto)->first();

            if (! $producto) {
                continue;
            }

            foreach ($lineas as $linea) {
                [$insumo, $cantidad] = $linea;
                $soloDelivery = $linea[2] ?? false;

                if (! isset($ing[$insumo])) {
                    continue;
                }

                RecipeItem::updateOrCreate(
                    ['product_id' => $producto->id, 'ingredient_id' => $ing[$insumo]],
                    ['qty' => $cantidad, 'only_for_delivery' => $soloDelivery],
                );
            }
        }
    }
}
