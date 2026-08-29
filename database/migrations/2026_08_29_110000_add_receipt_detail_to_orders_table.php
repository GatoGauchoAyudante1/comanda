<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Detalle alternativo del comprobante.
 *
 * Guarda el texto que reemplaza a la lista de consumos cuando el cliente pide
 * un ticket sin detalle: el viajante que rinde gastos necesita el comprobante,
 * pero no que en la empresa lean qué cenó. Ver R-40.
 *
 * Nulo es lo normal: el ticket sale con el detalle completo, igual que la
 * comanda. Sólo se llena cuando alguien lo pide expresamente.
 *
 * Corto (40) porque entra en el ancho del papel de 80 mm: son 32 caracteres
 * por línea y la columna del importe se lleva una parte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('receipt_detail', 40)->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('receipt_detail');
        });
    }
};
