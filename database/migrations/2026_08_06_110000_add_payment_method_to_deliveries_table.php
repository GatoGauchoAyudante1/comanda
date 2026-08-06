<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cómo va a pagar el pedido, decidido al tomarlo por teléfono.
 *
 * No es un `payment` todavía: el pago real se registra cuando se entrega
 * (efectivo) o al confirmarlo (QR ya acreditado). Guardarlo acá permite que
 * el repartidor sepa qué tiene que cobrar y cuánto cambio llevar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('payment_method', 20)->default('cash')->after('fee');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};
