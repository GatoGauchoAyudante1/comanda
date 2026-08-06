<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class StockCountItem extends Model
{
    protected $guarded = [];
    protected function casts(): array
    {
        return ["expected_qty" => "float", "counted_qty" => "float", "difference_value" => "integer"];
    }
    public function stockCount(): BelongsTo { return $this->belongsTo(StockCount::class); }
    public function ingredient(): BelongsTo { return $this->belongsTo(Ingredient::class); }
    public function diferencia(): float { return (float) ($this->counted_qty - $this->expected_qty); }
    public function contado(): bool { return $this->counted_qty !== null; }
}
