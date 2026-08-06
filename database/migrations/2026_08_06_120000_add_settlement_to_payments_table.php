<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vincula cada cobro en efectivo con la rendición en la que entró a la caja.
 *
 * Sin esto, la plata que un repartidor lleva en el bolsillo se contaba como
 * si ya estuviera en el cajón, y el arqueo daba de más.
 * Ver docs/06-reglas-negocio.md · R-17 y R-19.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('settlement_id')->nullable()->after('reference')
                ->constrained('driver_settlements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('settlement_id');
        });
    }
};
