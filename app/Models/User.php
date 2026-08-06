<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    // Ver docs/04-modelo-datos.md y docs/06-reglas-negocio.md R-27 a R-29.
    public const ROLES = ['dueno', 'cajero', 'mozo', 'cocina', 'repartidor'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    public function esDueno(): bool       { return $this->role === 'dueno'; }
    public function puedeCobrar(): bool   { return in_array($this->role, ['dueno', 'cajero'], true); }
    public function veCostos(): bool      { return $this->role === 'dueno'; }

    /**
     * A dónde va cada rol al entrar. Cocina y repartidor no tienen nada que
     * hacer en el panel de mesas: mandarlos ahí les daría un 403 en la cara.
     */
    public function rutaInicio(): string
    {
        return match ($this->role) {
            'cocina'     => 'cocina',
            'repartidor' => 'envios',
            default      => 'panel',
        };
    }

    public function orders(): HasMany     { return $this->hasMany(Order::class); }
    public function payments(): HasMany   { return $this->hasMany(Payment::class); }
    public function envios(): HasMany     { return $this->hasMany(Delivery::class, 'driver_id'); }
}
