<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function zone(): BelongsTo     { return $this->belongsTo(Zone::class); }

    public function completa(): string
    {
        return trim($this->street . ($this->detail ? ', ' . $this->detail : ''));
    }
}
