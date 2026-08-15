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

    /**
     * ¿Se vende tal cual se compra?
     *
     * Una 7UP tiene línea de receta porque el stock la necesita, pero no es una
     * receta: no hay nada que componer ni que editar. La pantalla de Recetas la
     * saca del medio para hablar sólo de lo que se prepara.
     *
     * Las tres condiciones juntas, porque cada una sola se equivoca:
     *   - no va a cocina  → el trago sale de la barra pero se prepara, y tiene
     *                       varias líneas: no entra acá
     *   - una sola línea  → la pizza tiene 3 o más
     *   - qty = 1         → «Sola» es una milanesa de cocina con una línea; el
     *                       flag de cocina ya la deja afuera, pero si mañana
     *                       alguien la mueve de área, esto la sigue salvando
     */
    public function esReventa(): bool
    {
        return ! $this->goes_to_kitchen
            && $this->recipe->count() === 1
            && (float) $this->recipe->first()->qty === 1.0;
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
