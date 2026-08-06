<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carta: categorías, productos y variantes de tamaño.
 *
 * Los precios se guardan en CENTAVOS (enteros). Ver docs/06-reglas-negocio.md · R-31.
 * Las variantes usan un factor sobre la receta base. Ver docs/02-decisiones.md · D-15.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('goes_to_kitchen')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->bigInteger('price')->default(0);          // centavos
            $table->boolean('goes_to_kitchen')->default(false);
            $table->boolean('tracks_stock')->default(true);   // si descuenta insumos (D-07)
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['category_id', 'active']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');                            // "Chica", "Grande"
            $table->bigInteger('price_delta')->default(0);     // puede ser negativo
            $table->decimal('recipe_factor', 6, 3)->default(1); // 0.600 = consume el 60%
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
