<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Descripción del producto.
 *
 * Es para la carta: dice de qué está hecho algo cuando el nombre solo no
 * alcanza («Rabas» no dice si vienen con alioli). Opcional a propósito —
 * una Coca no necesita explicación, y una carta donde todo tiene un renglón
 * de texto abajo se lee peor que una donde lo tienen los tres platos que
 * lo necesitan.
 *
 * Corta (300) porque va en el celular del que está sentado en la mesa: si
 * no entra en dos renglones, ya nadie la lee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('description', 300)->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
