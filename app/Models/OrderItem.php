<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'qty'                => 'integer',
            'unit_price'         => 'integer',
            'sent_to_kitchen_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo   { return $this->belongsTo(Order::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }

    /** La otra mitad, en pizzas mitad y mitad. */
    public function halfProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'half_product_id');
    }

    public function subtotal(): int
    {
        return $this->qty * $this->unit_price;
    }

    public function esMitadYMitad(): bool
    {
        return $this->half_product_id !== null;
    }
}
