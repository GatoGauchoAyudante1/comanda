<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abono mensual del sistema: lo que el cliente le paga al desarrollador.
 *
 * No es facturación del negocio ni comparte nada con orders/payments: es una
 * deuda del titular hacia afuera. Por eso también va en pesos con decimales y
 * no en centavos como el resto del sistema (R-31): acá no hay que cuadrar caja,
 * es un importe fijo que se escribe a mano una vez por mes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_charges', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->decimal('amount', 12, 2);
            $table->date('due_date');
            $table->string('status', 20)->default('pending'); // pending | paid
            $table->timestamp('paid_at')->nullable();
            $table->string('paid_method', 50)->nullable();
            $table->string('paid_notes')->nullable();
            $table->timestamps();

            // Un solo cargo por mes: evita duplicados si el comando corre dos veces.
            $table->unique(['period_year', 'period_month']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_charges');
    }
};
