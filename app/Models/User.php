<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'active', 'can_edit_prices'])]
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
            'can_edit_prices' => 'boolean',
        ];
    }

    public function esDueno(): bool       { return $this->role === 'dueno'; }
    public function puedeCobrar(): bool   { return in_array($this->role, ['dueno', 'cajero'], true); }
    public function veCostos(): bool      { return $this->role === 'dueno'; }

    /**
     * Cambiar precios de la carta. El dueño siempre puede; a los demás se los
     * habilita de a uno en Ajustes → Usuarios (R-39).
     */
    public function puedeEditarPrecios(): bool
    {
        return $this->esDueno() || (bool) $this->can_edit_prices;
    }

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

    /**
     * ¿Este usuario ya operó?
     *
     * Un usuario que tocó plata o mercadería no se borra: su nombre está en
     * pedidos, cobros, arqueos y movimientos de stock, y esa trazabilidad es
     * justamente lo que hace auditable al sistema (R-32). Para esos, la baja
     * es desactivarlos.
     *
     * Sólo se puede borrar al que se creó por error y nunca hizo nada.
     */
    public function tieneHistorial(): bool
    {
        return $this->orders()->exists()
            || $this->payments()->exists()
            || CashSession::where('opened_by', $this->id)->exists()
            || CashMovement::where('user_id', $this->id)->exists()
            || TableSession::where('user_id', $this->id)->exists()
            || DriverSettlement::where('driver_id', $this->id)->exists()
            || Purchase::where('user_id', $this->id)->exists()
            || StockCount::where('user_id', $this->id)->exists();
    }
}
