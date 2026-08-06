<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PurchaseItem extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ["qty" => "float", "unit_cost" => "integer"]; }
    public function purchase(): BelongsTo { return $this->belongsTo(Purchase::class); }
    public function ingredient(): BelongsTo { return $this->belongsTo(Ingredient::class); }
    public function subtotal(): int { return (int) round($this->qty * $this->unit_cost); }
}
