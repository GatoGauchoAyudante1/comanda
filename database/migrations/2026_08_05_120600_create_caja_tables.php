<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turnos de caja y sus movimientos.
 *
 * Fórmula del efectivo esperado en docs/06-reglas-negocio.md · R-19.
 * Un turno cerrado no se modifica (R-22).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();

            $table->bigInteger('opening_float')->default(0);  // fondo inicial
            $table->bigInteger('expected_cash')->nullable();  // lo que dice el sistema
            $table->bigInteger('counted_cash')->nullable();   // el conteo de billetes
            $table->bigInteger('difference')->nullable();
            $table->text('difference_note')->nullable();      // explicación (R-20)
            $table->json('bill_breakdown')->nullable();       // cuántos de cada denominación

            $table->timestamps();

            $table->index('closed_at');
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);                       // expense | withdrawal | deposit
            $table->bigInteger('amount');                     // centavos, siempre positivo
            $table->string('concept');
            $table->timestamps();

            $table->index(['cash_session_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cash_sessions');
    }
};
