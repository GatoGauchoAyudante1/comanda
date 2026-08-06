<?php

namespace App\Support;

use App\Models\AuditEvent;
use App\Models\CashSession;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Registra lo que pasa en el local, en el idioma del local.
 *
 * Se llama desde las acciones de dominio, no desde los modelos: lo que
 * interesa es la intención («el cadete entregó»), no el cambio de campo.
 *
 * Ver docs/11-auditoria.md.
 */
class Bitacora
{
    /** Se calcula una vez por request: casi todas las acciones registran varios eventos. */
    private static ?string $diaOperativo = null;

    public static function registrar(
        string $tipo,
        string $descripcion,
        ?Model $sujeto = null,
        array $meta = [],
        ?User $usuario = null,
    ): AuditEvent {
        $usuario ??= Auth::user();

        return AuditEvent::create([
            'type'          => $tipo,
            'description'   => $descripcion,
            'user_id'       => $usuario?->id,
            // Foto del momento: si después le cambian el rol o el nombre,
            // el registro sigue diciendo quién era esa noche.
            'user_name'     => $usuario?->name,
            'user_role'     => $usuario?->role,
            'subject_type'  => $sujeto ? $sujeto::class : null,
            'subject_id'    => $sujeto?->getKey(),
            'business_date' => self::diaOperativo($sujeto),
            'meta'          => $meta ?: null,
            'created_at'    => now(),
        ]);
    }

    /**
     * El día operativo del evento. Si el sujeto es un pedido usa el suyo;
     * si no, el del turno abierto. Ver T-03.
     */
    private static function diaOperativo(?Model $sujeto): ?string
    {
        if ($sujeto instanceof Order && $sujeto->business_date) {
            return $sujeto->business_date->toDateString();
        }

        if (self::$diaOperativo === null) {
            self::$diaOperativo = CashSession::actual()?->opened_at->toDateString()
                ?? now()->toDateString();
        }

        return self::$diaOperativo;
    }

    public static function olvidar(): void
    {
        self::$diaOperativo = null;
    }

    /** La historia de un pedido, de la más vieja a la más nueva. */
    public static function de(Model $sujeto)
    {
        return AuditEvent::where('subject_type', $sujeto::class)
            ->where('subject_id', $sujeto->getKey())
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }
}
