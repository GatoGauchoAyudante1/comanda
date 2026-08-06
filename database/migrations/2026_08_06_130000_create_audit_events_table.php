<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de eventos de negocio. Ver docs/11-auditoria.md.
 *
 * No es un log técnico de cambios de campos: es la historia de lo que pasó,
 * en el vocabulario del local. «Nico marcó el pedido #128 como entregado»,
 * no «deliveries.delivered_at pasó de null a 23:41».
 *
 * Decisiones importantes:
 *
 *  - `description` se escribe YA REDACTADA. El día que se renombre un producto
 *    o se borre una mesa, la bitácora sigue contando lo que pasó esa noche.
 *  - `user_name` y `user_role` son una foto del momento. Si al usuario le
 *    cambian el rol después, el registro no miente sobre quién era entonces.
 *  - No hay `updated_at`: un evento no se corrige. Si algo salió mal, se
 *    registra otro evento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();

            $table->string('type', 40);
            $table->text('description');

            // Quién. El id para relacionar, el nombre para que sobreviva.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name', 60)->nullable();
            $table->string('user_role', 20)->nullable();

            // Sobre qué: un pedido, un turno de caja, una mesa.
            $table->nullableMorphs('subject');

            // Día operativo, para poder preguntar «qué pasó anoche» (T-03).
            $table->date('business_date')->nullable();

            $table->json('meta')->nullable();
            $table->timestamp('created_at');

            $table->index('type');
            $table->index('created_at');
            $table->index(['business_date', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
