<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * El pedido, para TODOS los canales. Ver docs/02-decisiones.md · D-03.
 */
class Order extends Model
{
    protected $guarded = [];

    public const TIPOS   = ['mesa_pool', 'mesa_salon', 'delivery', 'retiro', 'mostrador'];
    public const ESTADOS = ['open', 'kitchen', 'ready', 'on_route', 'delivered', 'paid', 'cancelled'];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'closed_at'     => 'datetime',
            'cancelled_at'  => 'datetime',
            'items_total'   => 'integer',
            'time_amount'   => 'integer',
            'delivery_fee'  => 'integer',
            'discount'      => 'integer',
            'total'         => 'integer',
        ];
    }

    public function items(): HasMany            { return $this->hasMany(OrderItem::class); }
    public function payments(): HasMany         { return $this->hasMany(Payment::class); }
    public function tableSession(): HasOne      { return $this->hasOne(TableSession::class); }
    public function delivery(): HasOne          { return $this->hasOne(Delivery::class); }
    public function user(): BelongsTo           { return $this->belongsTo(User::class); }
    public function cashSession(): BelongsTo    { return $this->belongsTo(CashSession::class); }

    public function esMesa(): bool
    {
        return in_array($this->type, ['mesa_pool', 'mesa_salon'], true);
    }

    /**
     * Correlativo por día operativo. El día operativo es la fecha en que abrió
     * el turno de caja, no la fecha calendario: un turno que arranca a las 19:00
     * y cierra a las 03:00 es un solo día. Ver docs/09-pendientes.md · T-03.
     */
    public static function siguienteNumero(CashSession $caja): int
    {
        return static::where('business_date', $caja->opened_at->toDateString())
            ->lockForUpdate()
            ->max('number') + 1;
    }

    /** Recalcula los totales a partir de los ítems y del tiempo de mesa. */
    public function recalcular(): void
    {
        $itemsTotal = $this->items->sum(fn (OrderItem $i) => $i->subtotal());
        $tiempo     = $this->tableSession?->importeTiempo() ?? 0;

        $this->items_total = $itemsTotal;
        $this->time_amount = $tiempo;
        $this->total       = $itemsTotal + $tiempo + $this->delivery_fee - $this->discount;

        $this->save();
    }

    /** Cuánto se pagó, en centavos. */
    public function pagado(): int
    {
        return (int) $this->payments()->sum('amount');
    }

    /** Cuánto falta. Ver docs/06-reglas-negocio.md · R-10. */
    public function saldo(): int
    {
        return $this->total - $this->pagado();
    }

    public function estaSaldado(): bool
    {
        return $this->saldo() <= 0;
    }
}
