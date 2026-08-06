<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roles del sistema. Ver docs/04-modelo-datos.md y docs/06-reglas-negocio.md
 * R-27 a R-29 para los permisos de cada uno.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // dueno | cajero | mozo | cocina | repartidor
            $table->string('role', 20)->default('cajero')->after('email');
            $table->boolean('active')->default(true)->after('role');

            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'active']);
        });
    }
};
