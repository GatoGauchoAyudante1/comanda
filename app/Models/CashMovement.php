<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    protected $guarded = [];

    public const TIPOS = ['expense', 'withdrawal', 'deposit'];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    public function cashSession(): BelongsTo { return $this->belongsTo(CashSession::class); }
    public function user(): BelongsTo        { return $this->belongsTo(User::class); }
}
