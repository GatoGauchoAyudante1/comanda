<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tamaño de un producto. El `recipe_factor` escala la receta base:
 * 0.6 = consume el 60% de lo que consume la variante grande.
 * Ver docs/02-decisiones.md · D-15.
 */
class ProductVariant extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price_delta'   => 'integer',
            'recipe_factor' => 'float',
            'is_default'    => 'boolean',
        ];
    }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }

    /** Precio final de esta variante, en centavos. */
    public function precio(): int
    {
        return $this->product->price + $this->price_delta;
    }
}
