<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Un evento de la bitácora. Ver App\Support\Bitacora y docs/11-auditoria.md.
 *
 * Los eventos no se editan ni se borran: si algo salió mal, se registra otro.
 */
class AuditEvent extends Model
{
    protected $guarded = [];

    /** Un evento ocurre una vez. No tiene sentido actualizarlo. */
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'business_date' => 'date', 'meta' => 'array'];
    }

    public function user(): BelongsTo  { return $this->belongsTo(User::class); }
    public function subject(): MorphTo { return $this->morphTo(); }

    /**
     * Familias de evento, para colorear y filtrar.
     * La clave es el prefijo antes del punto.
     */
    public const FAMILIAS = [
        'pedido' => ['Pedidos', 'green'],
        'item'   => ['Ítems', 'line'],
        'mesa'   => ['Mesas', 'green'],
        'cobro'  => ['Cobros', 'green'],
        'caja'   => ['Caja', 'amber'],
        'cadete' => ['Repartidores', 'blue'],
        'stock'  => ['Stock', 'amber'],
        'config' => ['Configuración', 'line'],
    ];

    public function familia(): string
    {
        return explode('.', $this->type)[0];
    }

    public function color(): string
    {
        return self::FAMILIAS[$this->familia()][1] ?? 'line';
    }

    /** Quién lo hizo, con el rol que tenía en ese momento. */
    public function responsable(): string
    {
        if (! $this->user_name) {
            return 'Sistema';
        }

        return $this->user_role
            ? "{$this->user_name} ({$this->user_role})"
            : $this->user_name;
    }
}
