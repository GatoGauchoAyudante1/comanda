<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El corazón del sistema: UNA sola tabla de pedidos para todos los canales.
 *
 * Ver docs/02-decisiones.md · D-03. Lo específico de cada tipo vive en tablas
 * satélite: table_sessions (el reloj) y deliveries (el envío).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // mesa_pool | mesa_salon | delivery | retiro | mostrador
            $table->string('type', 20);
            // open | kitchen | ready | on_route | delivered | paid | cancelled
            $table->string('status', 20)->default('open');

            $table->unsignedInteger('number');                // correlativo del día
            $table->date('business_date');                    // a qué día operativo pertenece

            $table->foreignId('cash_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            $table->bigInteger('items_total')->default(0);    // suma de los ítems
            $table->bigInteger('time_amount')->default(0);    // importe del tiempo de pool
            $table->bigInteger('delivery_fee')->default(0);
            $table->bigInteger('discount')->default(0);
            $table->bigInteger('total')->default(0);

            $table->text('notes')->nullable();
            $table->string('cancel_reason')->nullable();      // R-06
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['business_date', 'number']);
            $table->index(['status', 'type']);
            $table->index('cash_session_id');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            // mitad y mitad: se cobra la más cara, se descuenta medio de cada receta (D-15)
            $table->foreignId('half_product_id')->nullable()->constrained('products')->nullOnDelete();

            $table->unsignedInteger('qty')->default(1);
            $table->bigInteger('unit_price');                 // precio copiado al cargar (R-07)
            $table->string('notes')->nullable();              // "sin aceitunas"
            // pending | kitchen | ready | delivered
            $table->string('status', 20)->default('pending');
            $table->timestamp('sent_to_kitchen_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index('status');
        });

        Schema::create('table_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete(); // mozo que atiende

            $table->timestamp('started_at');                  // editable al abrir (R-04)
            $table->timestamp('ended_at')->nullable();

            // La tarifa se COPIA, no se referencia (R-03).
            $table->string('rate_name')->nullable();
            $table->bigInteger('rate_price_per_hour')->default(0);
            $table->unsignedSmallInteger('rate_rounding_minutes')->default(30);

            $table->unsignedSmallInteger('guests')->nullable();
            $table->string('reference')->nullable();          // "los del fondo"

            $table->unsignedInteger('paused_minutes')->default(0); // R-05
            $table->timestamp('paused_at')->nullable();

            // Auditoría del ajuste de hora de inicio (R-04, R-32).
            $table->foreignId('start_adjusted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['table_id', 'ended_at']);
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('address_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();

            $table->bigInteger('fee')->default(0);            // copiado de la zona (R-15)
            $table->bigInteger('pays_with')->nullable();      // para calcular el vuelto

            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'delivered_at']);
        });

        Schema::create('driver_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('cash_session_id')->constrained()->restrictOnDelete();

            $table->unsignedInteger('deliveries_count')->default(0);
            $table->bigInteger('cash_expected')->default(0);
            $table->bigInteger('cash_received')->default(0);
            $table->bigInteger('difference')->default(0);
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['cash_session_id', 'settled_at']);
        });

        // Muchos pagos por pedido: el pago mixto es de primera clase (D-11).
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_session_id')->constrained()->restrictOnDelete(); // R-13
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            // cash | qr | transfer | debit | credit | other
            $table->string('method', 20);
            $table->bigInteger('amount');
            $table->bigInteger('received')->nullable();       // sólo efectivo, para el vuelto (R-11)
            $table->string('reference')->nullable();          // número de operación
            $table->timestamps();

            $table->index(['cash_session_id', 'method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('driver_settlements');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('table_sessions');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
