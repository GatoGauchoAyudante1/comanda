<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * El abono de un mes: lo que el cliente le debe al desarrollador por el sistema.
 *
 * Un registro por período, creado por App\Services\LicenseService. No se
 * relaciona con nada del dominio del negocio a propósito. Ver /dev-panel.
 */
class LicenseCharge extends Model
{
    protected $fillable = [
        'period_year',
        'period_month',
        'amount',
        'due_date',
        'status',
        'paid_at',
        'paid_method',
        'paid_notes',
    ];

    /** La vista muestra siempre estos dos; calcularlos a mano en Blade sería repetirlo por fila. */
    protected $appends = ['period_label', 'is_overdue'];

    protected function casts(): array
    {
        return [
            'period_year'  => 'integer',
            'period_month' => 'integer',
            'amount'       => 'decimal:2',
            'due_date'     => 'date',
            'paid_at'      => 'datetime',
        ];
    }

    /** «septiembre 2026». Sale en castellano por APP_LOCALE=es. */
    public function getPeriodLabelAttribute(): string
    {
        return Carbon::create($this->period_year, $this->period_month, 1)->translatedFormat('F Y');
    }

    /** Vencido es pendiente y con la fecha pasada: el día del vencimiento todavía no lo está. */
    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'pending' && now()->startOfDay()->gt($this->due_date);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }
}
