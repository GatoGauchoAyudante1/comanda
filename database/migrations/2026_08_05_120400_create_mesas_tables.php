<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mesas y tarifas de pool.
 *
 * `tables` es palabra reservada en varios contextos pero es un nombre válido
 * en MySQL y es el que corresponde por convención de Laravel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->string('name');                     // "Pool 1", "Mesa 3"
            $table->string('type', 10);                 // pool | salon
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['type', 'active']);
        });

        Schema::create('table_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name');                     // "Hora normal", "Happy hour"
            $table->bigInteger('price_per_hour');       // centavos
            $table->unsignedSmallInteger('rounding_minutes')->default(30); // fracción (R-02)
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_rates');
        Schema::dropIfExists('tables');
    }
};
