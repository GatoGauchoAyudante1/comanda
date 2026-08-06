<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    protected $guarded = [];

    public function addresses(): HasMany { return $this->hasMany(Address::class); }
    public function deliveries(): HasMany { return $this->hasMany(Delivery::class); }

    public function direccionPrincipal(): HasOne
    {
        return $this->hasOne(Address::class)->where('is_default', true);
    }

    /** Búsqueda por teléfono: es la identidad del cliente. Ver R-14. */
    public static function porTelefono(string $telefono): ?self
    {
        return static::where('phone', preg_replace('/\D/', '', $telefono))->first();
    }
}
