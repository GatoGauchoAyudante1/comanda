<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rendición del repartidor. Cierra el circuito del efectivo: lo que cobró el
 * cadete no está en la caja hasta que rinde. Ver docs/06-reglas-negocio.md · R-17.
 */
class DriverSettlement extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'deliveries_count' => 'integer',
            'cash_expected'    => 'integer',
            'cash_received'    => 'integer',
            'difference'       => 'integer',
            'settled_at'       => 'datetime',
        ];
    }

    public function driver(): BelongsTo      { return $this->belongsTo(User::class, 'driver_id'); }
    public function cashSession(): BelongsTo { return $this->belongsTo(CashSession::class); }

    public function rendida(): bool { return $this->settled_at !== null; }
}
