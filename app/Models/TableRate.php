<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TableRate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price_per_hour'   => 'integer',
            'rounding_minutes' => 'integer',
            'is_default'       => 'boolean',
            'active'           => 'boolean',
        ];
    }
}
