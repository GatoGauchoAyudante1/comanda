<?php

namespace App\Actions;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Sube o baja precios en lote por porcentaje.
 *
 * En Argentina esto no es un lujo: si hay que tocar 68 productos de a uno
 * cada vez que aumenta un proveedor, no lo hacen y la carta queda desfasada.
 *
 * El redondeo importa tanto como el porcentaje: nadie quiere una pizza
 * a $14.375. Se redondea al múltiplo que elija el dueño.
 */
class AjustarPrecios
{
    /**
     * @param  float     $porcentaje  15 = subir 15%, -10 = bajar 10%
     * @param  int|null  $categoriaId null = toda la carta
     * @param  int       $redondeo    múltiplo en pesos al que ajustar; 0 = sin redondear
     * @return int  productos modificados
     */
    public function __invoke(float $porcentaje, ?int $categoriaId = null, int $redondeo = 100): int
    {
        return DB::transaction(function () use ($porcentaje, $categoriaId, $redondeo) {
            $productos = Product::query()
                ->when($categoriaId, fn ($q) => $q->where('category_id', $categoriaId))
                ->where('price', '>', 0)
                ->get();

            foreach ($productos as $producto) {
                $producto->update([
                    'price' => $this->calcular($producto->price, $porcentaje, $redondeo),
                ]);
            }

            return $productos->count();
        });
    }

    /** Todo en centavos. El redondeo se expresa en pesos. */
    public function calcular(int $precio, float $porcentaje, int $redondeo): int
    {
        $nuevo = $precio * (1 + $porcentaje / 100);

        if ($redondeo > 0) {
            $paso  = $redondeo * 100;
            $nuevo = round($nuevo / $paso) * $paso;

            // Con precios muy bajos el redondeo podría dejarlos en cero.
            $nuevo = max($paso, $nuevo);
        }

        return (int) round($nuevo);
    }
}
