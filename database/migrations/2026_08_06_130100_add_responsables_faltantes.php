<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los responsables que faltaba capturar.
 *
 * Ningún historial puede reconstruir lo que nunca se guardó. Estos son los
 * huecos que dejaban los tres reclamos típicos sin respuesta:
 *
 *   «no me entregaron la comida»  -> quién confirmó la entrega
 *   «yo no llevé ese pedido»      -> quién lo despachó
 *   «yo lo marqué listo»          -> quién marcó listo en cocina
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('ready_by')->nullable()->after('sent_to_kitchen_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('ready_at')->nullable()->after('ready_by');
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->foreignId('dispatched_by')->nullable()->after('dispatched_at')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('delivered_by')->nullable()->after('delivered_at')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('driver_settlements', function (Blueprint $table) {
            // Quién recibió la plata. El parámetro existía en RendirCaja y se
            // descartaba: el cadete rendía y no quedaba ante quién.
            $table->foreignId('received_by')->nullable()->after('settled_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ready_by');
            $table->dropColumn('ready_at');
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dispatched_by');
            $table->dropConstrainedForeignId('delivered_by');
        });

        Schema::table('driver_settlements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('received_by');
        });
    }
};
