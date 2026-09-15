<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Configuración del abono, sobre la tabla `settings` que ya usa el sistema.
 *
 * insertOrIgnore y no updateOrCreate: si la migración se vuelve a correr en una
 * instalación andando, no puede pisar el importe ni el alias que ya ajusté.
 *
 * Ojo: estas claves las edita el desarrollador desde /dev-panel, no el dueño
 * desde /ajustes. La pantalla de configuración del negocio no las toca.
 */
return new class extends Migration
{
    private const DEFAULTS = [
        ['key' => 'license_enabled',        'value' => '1',              'type' => 'bool'],
        ['key' => 'license_monthly_amount', 'value' => '50000',          'type' => 'string'],
        ['key' => 'license_notice_day',     'value' => '1',              'type' => 'int'],
        ['key' => 'license_due_day',        'value' => '10',             'type' => 'int'],
        ['key' => 'license_payee',          'value' => 'Codeland',       'type' => 'string'],
        ['key' => 'license_payment_alias',  'value' => 'codeland.it.mp', 'type' => 'string'],
        ['key' => 'license_payment_method', 'value' => 'Mercado Pago',   'type' => 'string'],
        ['key' => 'license_contact_phone',  'value' => '',               'type' => 'string'],
    ];

    public function up(): void
    {
        $ahora = now();

        DB::table('settings')->insertOrIgnore(array_map(
            fn (array $fila) => $fila + ['created_at' => $ahora, 'updated_at' => $ahora],
            self::DEFAULTS,
        ));
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_column(self::DEFAULTS, 'key'))->delete();
    }
};
