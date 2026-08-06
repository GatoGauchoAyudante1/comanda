<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock: insumos, recetas, compras, movimientos y conteos.
 *
 * Las cantidades SIEMPRE se guardan en unidad base (g, ml, un), aunque la
 * pantalla deje cargarlas por rendimiento. Ver docs/05-modulo-stock.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('base_unit', 5);                    // g | ml | un
            $table->decimal('stock', 14, 3)->default(0);       // en unidad base
            $table->decimal('min_stock', 14, 3)->default(0);
            $table->bigInteger('cost')->default(0);            // centavos por unidad base
            $table->string('area', 20)->default('cocina');     // cocina | barra | descartables
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['area', 'active']);
        });

        // Producto -> insumo. La reventa 1:1 es una receta de un solo insumo.
        Schema::create('recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->decimal('qty', 14, 4);                     // por unidad vendida, en unidad base
            $table->boolean('only_for_delivery')->default(false); // ej. la caja de pizza
            $table->timestamps();

            $table->unique(['product_id', 'ingredient_id']);
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('purchased_on');
            $table->string('document')->nullable();            // número de remito o factura
            $table->bigInteger('total')->default(0);
            $table->timestamps();
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->decimal('qty', 14, 3);                     // en unidad base
            $table->bigInteger('unit_cost');                   // centavos por unidad base
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            // purchase | sale | waste | adjustment | internal
            $table->string('type', 20);
            $table->decimal('qty', 14, 4);                     // positivo o negativo
            $table->bigInteger('cost')->default(0);            // costo unitario al momento
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason')->nullable();              // obligatorio en waste y adjustment (R-25)
            $table->nullableMorphs('source');                  // el pedido o la compra que lo originó
            $table->timestamps();

            $table->index(['ingredient_id', 'type']);
            $table->index('created_at');
        });

        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('area', 20)->nullable();
            $table->string('status', 20)->default('open');     // open | closed
            $table->bigInteger('difference_value')->default(0); // diferencia valorizada, centavos
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->decimal('expected_qty', 14, 3);            // lo que decía el sistema
            $table->decimal('counted_qty', 14, 3)->nullable(); // lo que se contó
            $table->bigInteger('difference_value')->default(0);
            $table->timestamps();

            $table->unique(['stock_count_id', 'ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_items');
        Schema::dropIfExists('stock_counts');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('recipe_items');
        Schema::dropIfExists('ingredients');
    }
};
