<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El pedido guarda a quién y adónde, tal como se dictó.
 *
 * Hasta acá mostraba la ficha del cliente, compartida por teléfono (R-14):
 * un pedido nuevo con el mismo número y otro nombre renombraba a todos los
 * anteriores. Es la misma regla que el precio (R-07) y el envío (R-15): lo
 * que se tomó queda congelado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('customer_name', 120)->nullable()->after('customer_id');
            $table->string('customer_phone', 40)->nullable()->after('customer_name');
            $table->string('street', 160)->nullable()->after('address_id');
            $table->string('address_detail', 120)->nullable()->after('street');
        });

        // Lo de antes se completa con lo que dice hoy la ficha. Si ya se había
        // pisado no hay forma de recuperar el nombre original: es lo mejor que hay.
        DB::table('deliveries')->orderBy('id')->each(function ($entrega) {
            $cliente   = DB::table('customers')->find($entrega->customer_id);
            $direccion = DB::table('addresses')->find($entrega->address_id);

            DB::table('deliveries')->where('id', $entrega->id)->update([
                'customer_name'  => $cliente?->name,
                'customer_phone' => $cliente?->phone,
                'street'         => $direccion?->street,
                'address_detail' => $direccion?->detail,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['customer_name', 'customer_phone', 'street', 'address_detail']);
        });
    }
};
