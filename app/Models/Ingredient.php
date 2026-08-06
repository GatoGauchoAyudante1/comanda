<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingredient extends Model
{
    protected $guarded = [];

    public const UNIDADES = ['g', 'ml', 'un'];
    public const AREAS    = ['cocina', 'barra', 'descartables'];

    protected function casts(): array
    {
        return [
            'stock'     => 'float',
            'min_stock' => 'float',
            'cost'      => 'integer',
            'active'    => 'boolean',
        ];
    }

    public function movements(): HasMany   { return $this->hasMany(StockMovement::class); }
    public function recipeItems(): HasMany { return $this->hasMany(RecipeItem::class); }

    public function bajoMinimo(): bool
    {
        return $this->stock < $this->min_stock;
    }

    /** Valor del stock actual, en centavos. */
    public function valor(): int
    {
        return (int) round($this->stock * $this->cost);
    }
}
