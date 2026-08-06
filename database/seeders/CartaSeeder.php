<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

/**
 * Carta de ejemplo. Se reemplaza por la carta real de cada cliente
 * cuando la entreguen (ver docs/09-pendientes.md · P-07).
 *
 * Precios en centavos: 350000 = $3.500
 */
class CartaSeeder extends Seeder
{
    public function run(): void
    {
        $carta = [
            'Cervezas' => ['cocina' => false, 'productos' => [
                ['Quilmes 1 litro', 350000], ['Quilmes porrón', 220000],
                ['Brahma 1 litro', 340000], ['Stella Artois porrón', 310000],
                ['Chopp tirado 500', 280000], ['Chopp tirado 1 litro', 490000],
                ['Corona porrón', 360000], ['Andes roja porrón', 290000],
                ['Patagonia amber', 420000], ['Balde 6 porrones', 1200000],
                ['Heineken porrón', 370000], ['Sin alcohol 0.0', 260000],
            ]],
            'Tragos' => ['cocina' => false, 'productos' => [
                ['Fernet con coca', 410000], ['Gin tonic', 480000],
                ['Campari con naranja', 450000], ['Vodka con Speed', 460000],
                ['Caipirinha', 520000],
            ]],
            'Sin alcohol' => ['cocina' => false, 'productos' => [
                ['Coca 500', 80000], ['Coca 1.5 L', 180000],
                ['Agua mineral 500', 70000], ['Agua saborizada', 90000],
                ['Café', 120000],
            ]],
            'Para picar' => ['cocina' => true, 'productos' => [
                ['Papas con cheddar', 890000], ['Provoleta', 760000],
                ['Rabas', 1450000], ['Picada para dos', 1850000],
            ]],
            'Cocina' => ['cocina' => true, 'productos' => [
                ['Milanesa napolitana', 1540000], ['Hamburguesa completa', 1320000],
                ['Empanadas de carne (docena)', 980000], ['Bondiola al plato', 1680000],
            ]],
            'Pizzas' => ['cocina' => true, 'productos' => [
                ['Pizza muzzarella', 1250000], ['Pizza napolitana', 1420000],
                ['Pizza fugazzeta', 1380000], ['Pizza calabresa', 1510000],
                ['Pizza cuatro quesos', 1640000], ['Pizza especial', 1590000],
                ['Pizza jamón y morrones', 1460000],
            ]],
            'Postres' => ['cocina' => true, 'productos' => [
                ['Flan casero', 620000], ['Helado dos bochas', 580000],
                ['Budín de pan', 540000],
            ]],
        ];

        $orden = 0;

        foreach ($carta as $nombreCat => $datos) {
            $categoria = Category::updateOrCreate(
                ['name' => $nombreCat],
                ['goes_to_kitchen' => $datos['cocina'], 'sort_order' => ++$orden, 'active' => true],
            );

            foreach ($datos['productos'] as $i => [$nombre, $precio]) {
                $producto = Product::updateOrCreate(
                    ['name' => $nombre],
                    [
                        'category_id'     => $categoria->id,
                        'price'           => $precio,
                        'goes_to_kitchen' => $datos['cocina'],
                        'tracks_stock'    => $nombre !== 'Café', // el café no descuenta (D-07)
                        'sort_order'      => $i + 1,
                        'active'          => true,
                    ],
                );

                // Las pizzas tienen tamaño. La chica consume el 60% de la receta (D-15).
                if ($nombreCat === 'Pizzas') {
                    ProductVariant::updateOrCreate(
                        ['product_id' => $producto->id, 'name' => 'Grande'],
                        ['price_delta' => 0, 'recipe_factor' => 1.0, 'is_default' => true, 'sort_order' => 2],
                    );
                    ProductVariant::updateOrCreate(
                        ['product_id' => $producto->id, 'name' => 'Chica'],
                        ['price_delta' => -400000, 'recipe_factor' => 0.6, 'is_default' => false, 'sort_order' => 1],
                    );
                }
            }
        }
    }
}
