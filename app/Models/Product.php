<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'goes_to_kitchen' => 'boolean',
            'tracks_stock' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function variants(): HasMany   { return $this->hasMany(ProductVariant::class)->orderBy('sort_order'); }
    public function recipe(): HasMany     { return $this->hasMany(RecipeItem::class); }

    /**
     * URL de la foto, o null si no tiene.
     *
     * En base se guarda la ruta relativa; la URL se arma acá para que un
     * cambio de dominio o de disco no obligue a tocar filas. Ver
     * App\Actions\GuardarFotoProducto.
     */
    public function foto(): ?string
    {
        return $this->image_path
            ? Storage::disk('public')->url($this->image_path)
            : null;
    }

    /** Costo de insumos por unidad, en centavos. */
    public function costo(): int
    {
        return (int) round(
            $this->recipe->sum(fn (RecipeItem $i) => $i->qty * $i->ingredient->cost)
        );
    }

    /** Margen sobre el precio de venta, en porcentaje. */
    public function margen(): ?float
    {
        if ($this->price <= 0 || $this->recipe->isEmpty()) {
            return null;
        }

        return round(($this->price - $this->costo()) / $this->price * 100, 1);
    }

    /**
     * Cuántas unidades se pueden producir con el stock actual.
     * Devuelve también qué insumo limita. Ver docs/05-modulo-stock.md.
     *
     * @return array{unidades:int, limita:?Ingredient}
     */
    public function produccionPosible(): array
    {
        $limite  = null;
        $minimas = null;

        foreach ($this->recipe as $item) {
            if ($item->qty <= 0) {
                continue;
            }

            $posibles = (int) floor($item->ingredient->stock / $item->qty);

            if ($minimas === null || $posibles < $minimas) {
                $minimas = $posibles;
                $limite  = $item->ingredient;
            }
        }

        return ['unidades' => $minimas ?? 0, 'limita' => $limite];
    }
}
