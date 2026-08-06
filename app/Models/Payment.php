<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $guarded = [];

    public const METODOS = ['cash', 'qr', 'transfer', 'debit', 'credit', 'other'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'received' => 'integer'];
    }

    public function order(): BelongsTo       { return $this->belongsTo(Order::class); }
    public function cashSession(): BelongsTo { return $this->belongsTo(CashSession::class); }
    public function user(): BelongsTo        { return $this->belongsTo(User::class); }

    /** El vuelto se muestra pero no genera movimiento. Ver R-11. */
    public function vuelto(): int
    {
        if ($this->method !== 'cash' || $this->received === null) {
            return 0;
        }

        return max(0, $this->received - $this->amount);
    }
}
