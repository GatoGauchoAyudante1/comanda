<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnlineOrder extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'items_total' => 'integer',
            'delivery_fee' => 'integer',
            'total' => 'integer',
            'pays_with' => 'integer',
            'estimated_minutes' => 'integer',
            'responded_at' => 'datetime',
        ];
    }

    public function items(): HasMany { return $this->hasMany(OnlineOrderItem::class); }
    public function zone(): BelongsTo { return $this->belongsTo(Zone::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function responder(): BelongsTo { return $this->belongsTo(User::class, 'responded_by'); }

    public function lineasParaPedido(): array
    {
        return $this->items->map(fn (OnlineOrderItem $item) => [
            'product_id' => $item->product_id,
            'variant_id' => $item->product_variant_id,
            'qty' => $item->qty,
            'notes' => $item->notes,
        ])->all();
    }

    public function whatsappUrl(string $mensaje): string
    {
        return 'https://wa.me/'.preg_replace('/\D/', '', $this->phone).'?text='.rawurlencode($mensaje);
    }
}
