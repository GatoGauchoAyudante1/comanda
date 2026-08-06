<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración del negocio. Todo lo que cambia de una instalación a otra
 * vive acá, nunca en el código.
 *
 * Ver docs/02-decisiones.md · D-02 y docs/06-reglas-negocio.md · R-30.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string'); // string | int | bool | json
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
