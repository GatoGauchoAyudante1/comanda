<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    protected $guarded = [];

    public const TIPOS = ['purchase', 'sale', 'waste', 'adjustment', 'internal'];

    /** Estos exigen motivo. Ver docs/06-reglas-negocio.md · R-25. */
    public const EXIGEN_MOTIVO = ['waste', 'adjustment'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'cost' => 'integer'];
    }

    public function ingredient(): BelongsTo { return $this->belongsTo(Ingredient::class); }
    public function user(): BelongsTo       { return $this->belongsTo(User::class); }
    public function source(): MorphTo       { return $this->morphTo(); }
}
