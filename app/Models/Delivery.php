<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fee'           => 'integer',
            'pays_with'     => 'integer',
            'dispatched_at' => 'datetime',
            'delivered_at'  => 'datetime',
        ];
    }

    public function order(): BelongsTo    { return $this->belongsTo(Order::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function address(): BelongsTo  { return $this->belongsTo(Address::class); }
    public function zone(): BelongsTo     { return $this->belongsTo(Zone::class); }
    public function driver(): BelongsTo   { return $this->belongsTo(User::class, 'driver_id'); }

    /*
     | A quién y adónde, como se tomó el pedido. Se lee de las columnas propias
     | y no de la ficha del cliente, que se comparte entre todos sus pedidos y
     | cambia con cada alta. Ver la migración add_datos_del_cliente_to_deliveries.
     */

    public function nombreCliente(): ?string
    {
        return $this->customer_name;
    }

    public function telefonoCliente(): ?string
    {
        return $this->customer_phone;
    }

    public function direccionCompleta(): ?string
    {
        if (! $this->street) {
            return null;
        }

        return trim($this->street . ($this->address_detail ? ', ' . $this->address_detail : ''));
    }

    /** Cuánto cambio tiene que llevar el repartidor. */
    public function vuelto(): int
    {
        if ($this->pays_with === null) {
            return 0;
        }

        return max(0, $this->pays_with - $this->order->total);
    }
}
