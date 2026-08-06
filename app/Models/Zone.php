<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zone extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['delivery_fee' => 'integer', 'active' => 'boolean'];
    }

    public function addresses(): HasMany  { return $this->hasMany(Address::class); }
    public function deliveries(): HasMany { return $this->hasMany(Delivery::class); }
}
