<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una línea de receta: cuánto de un insumo lleva una unidad de un producto.
 *
 * `qty` SIEMPRE está en unidad base. La carga por rendimiento
 * ("de 1 horma me salen 40") se convierte antes de guardar.
 * Ver docs/05-modulo-stock.md y docs/02-decisiones.md · D-09.
 */
class RecipeItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['qty' => 'float', 'only_for_delivery' => 'boolean'];
    }

    public function product(): BelongsTo    { return $this->belongsTo(Product::class); }
    public function ingredient(): BelongsTo { return $this->belongsTo(Ingredient::class); }

    /**
     * Convierte "de 1 envase de X unidades base me salen N" a cantidad por unidad.
     * Es la operación que hace la pantalla de receta en modo rendimiento.
     */
    public static function desdeRendimiento(float $contenidoEnvase, int $unidadesQueRinde): float
    {
        if ($unidadesQueRinde <= 0) {
            return 0;
        }

        return $contenidoEnvase / $unidadesQueRinde;
    }

    /** La lectura inversa: cuántas unidades rinde un envase. */
    public function rinde(float $contenidoEnvase): int
    {
        return $this->qty > 0 ? (int) floor($contenidoEnvase / $this->qty) : 0;
    }
}
