<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Configuración del negocio en base de datos.
 *
 * Es el reemplazo de config/negocio.php una vez que el dueño pueda editarla
 * desde la pantalla de configuración. Ver docs/02-decisiones.md · D-02.
 */
class Setting extends Model
{
    protected $guarded = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::where('key', $key)->first();

        if (! $row) {
            return $default;
        }

        return match ($row->type) {
            'int'  => (int) $row->value,
            'bool' => filter_var($row->value, FILTER_VALIDATE_BOOL),
            'json' => json_decode($row->value, true),
            default => $row->value,
        };
    }

    public static function put(string $key, mixed $value, string $type = 'string'): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $type === 'json' ? json_encode($value) : (string) $value, 'type' => $type],
        );
    }
}
