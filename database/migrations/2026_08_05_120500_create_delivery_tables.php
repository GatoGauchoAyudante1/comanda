<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zonas, clientes y direcciones.
 *
 * El teléfono es la identidad del cliente y por eso va indexado:
 * al escribirlo se autocompleta todo. Ver docs/06-reglas-negocio.md · R-14.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->bigInteger('delivery_fee')->default(0);   // centavos
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('phone', 40);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique('phone');
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
            $table->string('street');
            $table->string('detail')->nullable();             // "timbre 3B"
            $table->boolean('is_default')->default(true);
            $table->timestamps();

            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('zones');
    }
};
