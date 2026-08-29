<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineOrderItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['qty' => 'integer', 'unit_price' => 'integer'];
    }

    public function onlineOrder(): BelongsTo { return $this->belongsTo(OnlineOrder::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }

    public function subtotal(): int { return $this->qty * $this->unit_price; }
}
