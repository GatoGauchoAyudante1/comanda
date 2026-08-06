<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class StockCount extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ["closed_at" => "datetime", "difference_value" => "integer"]; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function items(): HasMany { return $this->hasMany(StockCountItem::class); }
    public function abierto(): bool { return $this->status === "open"; }
}
