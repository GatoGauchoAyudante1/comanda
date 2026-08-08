<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permiso delegado: cambiar precios de la carta.
 *
 * Va por usuario y no por rol (como sí va R-36) porque el dueño delega esto
 * en una persona de confianza —el encargado—, no en «los cajeros». Ver R-39.
 *
 * El dueño no necesita la marca: la tiene siempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_edit_prices')->default(false)->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('can_edit_prices');
        });
    }
};
