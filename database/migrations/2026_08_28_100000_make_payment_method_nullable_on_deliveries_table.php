<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El método de pago deja de ser obligatorio al tomar el pedido: mucha gente
 * no sabe con qué va a pagar hasta que el pedido llega a destino. Queda en
 * null ("a definir") hasta que se confirma, y se puede cambiar después
 * (ver App\Actions\CambiarMetodoPago).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('payment_method', 20)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('payment_method', 20)->default('cash')->change();
        });
    }
};
