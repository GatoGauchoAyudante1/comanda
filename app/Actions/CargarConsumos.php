<?php

namespace App\Actions;

use App\Events\PedidoEnviadoACocina;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Bitacora;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Carga ítems a una cuenta abierta.
 *
 * Reglas (docs/06-reglas-negocio.md):
 *   R-07  el precio se COPIA al cargar; un cambio en la carta no toca cuentas abiertas
 *   R-08  lo que va a cocina queda en estado `kitchen` y aparece en el KDS
 */
class CargarConsumos
{
    /**
     * @param  array<int, array{product_id:int, variant_id?:int|null, qty:int, notes?:string|null}>  $lineas
     * @return int  cuántos ítems se cargaron
     */
    public function __invoke(Order $orden, array $lineas): int
    {
        if ($orden->status !== 'open') {
            return 0;
        }

        return DB::transaction(function () use ($orden, $lineas) {
            $aCocina  = [];
            $cargados = [];

            foreach ($lineas as $linea) {
                $producto = Product::find($linea['product_id']);
                $cantidad = (int) ($linea['qty'] ?? 0);

                if (! $producto || ! $producto->active || $cantidad < 1) {
                    continue;
                }

                $variante = isset($linea['variant_id'])
                    ? ProductVariant::where('product_id', $producto->id)->find($linea['variant_id'])
                    : null;

                $item = OrderItem::create([
                    'order_id'           => $orden->id,
                    'product_id'         => $producto->id,
                    'product_variant_id' => $variante?->id,
                    'qty'                => $cantidad,
                    // Precio congelado al momento de cargar (R-07).
                    'unit_price'         => $producto->price + ($variante?->price_delta ?? 0),
                    'notes'              => $linea['notes'] ?? null,
                    'status'             => $producto->goes_to_kitchen ? 'kitchen' : 'delivered',
                    'sent_to_kitchen_at' => $producto->goes_to_kitchen ? Carbon::now() : null,
                ]);

                $cargados[] = "{$cantidad}x {$producto->name}"
                    . ($variante ? " {$variante->name}" : '')
                    . (! empty($linea['notes']) ? " ({$linea['notes']})" : '');

                if ($producto->goes_to_kitchen) {
                    $aCocina[] = $item->id;
                }
            }

            $orden->refresh()->recalcular();

            if ($cargados !== []) {
                Bitacora::registrar(
                    'item.cargado',
                    'Cargó ' . implode(', ', $cargados),
                    $orden,
                    ['items' => $cargados, 'a_cocina' => count($aCocina)],
                );
            }

            if ($aCocina !== []) {
                Bitacora::registrar(
                    'pedido.enviado_cocina',
                    count($aCocina) . ' ' . (count($aCocina) === 1 ? 'ítem enviado' : 'ítems enviados') . ' a cocina',
                    $orden,
                    ['item_ids' => $aCocina],
                );

                PedidoEnviadoACocina::dispatch($orden, $aCocina);
            }

            return count($aCocina) + $orden->items()->count();
        });
    }
}
