<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foto del producto.
 *
 * Guarda sólo la ruta relativa dentro del disco `public`
 * (ej: `productos/12-a1b2c3.jpg`), no la URL: si mañana cambia el dominio
 * o el disco pasa a S3, las filas no hay que tocarlas.
 *
 * La foto es para la carta pública: adentro del sistema nadie elige un
 * producto por la foto, elige por el nombre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
