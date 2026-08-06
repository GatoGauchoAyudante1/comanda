<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * El turno de caja. Ver docs/06-reglas-negocio.md · R-18 a R-22.
 */
class CashSession extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'opened_at'      => 'datetime',
            'closed_at'      => 'datetime',
            'opening_float'  => 'integer',
            'expected_cash'  => 'integer',
            'counted_cash'   => 'integer',
            'difference'     => 'integer',
            'bill_breakdown' => 'array',
        ];
    }

    public function movements(): HasMany   { return $this->hasMany(CashMovement::class); }
    public function payments(): HasMany    { return $this->hasMany(Payment::class); }
    public function orders(): HasMany      { return $this->hasMany(Order::class); }
    public function settlements(): HasMany { return $this->hasMany(DriverSettlement::class); }
    public function openedBy(): BelongsTo  { return $this->belongsTo(User::class, 'opened_by'); }
    public function closedBy(): BelongsTo  { return $this->belongsTo(User::class, 'closed_by'); }

    public function abierta(): bool { return $this->closed_at === null; }

    public static function actual(): ?self
    {
        return static::whereNull('closed_at')->latest('opened_at')->first();
    }

    /** Ventas cobradas por método, en centavos. */
    public function ventasPor(string $metodo): int
    {
        return (int) $this->payments()->where('method', $metodo)->sum('amount');
    }

    public function gastos(): int
    {
        return (int) $this->movements()->where('type', 'expense')->sum('amount');
    }

    public function retiros(): int
    {
        return (int) $this->movements()->where('type', 'withdrawal')->sum('amount');
    }

    public function rendicionesDeCadetes(): int
    {
        return (int) $this->settlements()->whereNotNull('settled_at')->sum('cash_received');
    }

    /**
     * Efectivo cobrado por repartidores que todavía no rindieron.
     *
     * Está físicamente en el bolsillo del cadete, no en el cajón: hay que
     * restarlo del arqueo hasta que rinda. Ver R-17.
     */
    public function efectivoEnLaCalle(): int
    {
        return (int) $this->payments()
            ->where('method', 'cash')
            ->whereNull('settlement_id')
            ->whereHas('order.delivery', fn ($q) => $q->whereNotNull('driver_id'))
            ->sum('amount');
    }

    /**
     * Efectivo que debería haber en el cajón. Ver R-19.
     *
     * Las ventas en efectivo ya incluyen lo que cobró el repartidor, así que
     * lo que todavía no rindió se resta. Cuando rinde, el pago queda ligado a
     * la rendición y deja de restarse: la plata entró.
     *
     * Sólo se arquea el efectivo: QR y tarjeta se concilian con el banco (R-18).
     */
    public function efectivoEsperado(): int
    {
        return $this->opening_float
             + $this->ventasPor('cash')
             - $this->efectivoEnLaCalle()
             - $this->gastos()
             - $this->retiros();
    }

    /**
     * Lo que hay que avisar antes de cerrar. Ver R-21.
     *
     * @return array<int, string>
     */
    public function pendientes(): array
    {
        $avisos = [];

        $mesas = $this->orders()->whereIn('type', ['mesa_pool', 'mesa_salon'])
            ->where('status', 'open')->count();
        if ($mesas > 0) {
            $avisos[] = "Hay {$mesas} mesa(s) abiertas sin cobrar.";
        }

        $sinEntregar = $this->orders()->whereIn('status', ['kitchen', 'ready', 'on_route'])->count();
        if ($sinEntregar > 0) {
            $avisos[] = "Hay {$sinEntregar} pedido(s) sin entregar.";
        }

        // Un repartidor sin rendir no deja rastro en `settlements`: la fila
        // recién se crea cuando rinde. Lo que lo delata es el efectivo suyo
        // que todavía no entró al cajón.
        if ($this->efectivoEnLaCalle() > 0) {
            $cadetes = $this->payments()
                ->where('method', 'cash')
                ->whereNull('settlement_id')
                ->whereHas('order.delivery', fn ($q) => $q->whereNotNull('driver_id'))
                ->with('order.delivery.driver')
                ->get()
                ->pluck('order.delivery.driver.name')
                ->unique()
                ->filter();

            $avisos[] = 'Falta rendir ' . \App\Support\Plata::format($this->efectivoEnLaCalle())
                      . ' de ' . $cadetes->join(', ') . '.';
        }

        // Diferencias de rendición: explican un faltante que no es del cajero.
        foreach ($this->settlements()->where('difference', '!=', 0)->with('driver')->get() as $r) {
            $avisos[] = $r->driver->name . ' rindió ' . \App\Support\Plata::format($r->difference)
                      . ' de diferencia.';
        }

        return $avisos;
    }
}
