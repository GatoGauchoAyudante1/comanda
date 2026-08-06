<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Contracts\View\View;

/**
 * Comprobante no fiscal para impresora térmica de 80 mm.
 *
 * En el MVP se imprime por el navegador, sin agente local: alcanza con que la
 * impresora tenga driver de Windows. Ver docs/09-pendientes.md · P-04 y T-02.
 */
class TicketController extends Controller
{
    public function __invoke(Order $orden): View
    {
        $orden->load(['items.product', 'items.variant', 'payments.user', 'tableSession.table', 'tableSession.user', 'user']);

        return view('ticket', [
            'orden'   => $orden,
            'numero'  => $this->numero($orden),
            'volver'  => $orden->tableSession && $orden->status !== 'paid'
                ? route('mesa', $orden->tableSession)
                : route('panel'),

            'encabezado' => match ($orden->type) {
                'mesa_pool', 'mesa_salon' => $orden->tableSession?->table->name ?? 'Mesa',
                'delivery' => 'Delivery',
                'retiro'   => 'Retira en el local',
                default    => 'Mostrador',
            },

            // Sin el signo $: en 32 caracteres de ancho cada uno cuenta.
            'importe' => fn (int $centavos) => number_format($centavos / 100, 0, ',', '.'),

            'nombreMetodo' => fn (string $m) => [
                'cash'     => 'Efectivo',
                'qr'       => 'QR Mercado Pago',
                'transfer' => 'Transferencia',
                'debit'    => 'Débito',
                'credit'   => 'Crédito',
                'other'    => 'Otro',
            ][$m] ?? $m,
        ]);
    }

    /** Formato 0001-00002: punto de venta y correlativo. Ver T-03. */
    private function numero(Order $orden): string
    {
        return \App\Support\Negocio::puntoDeVenta()
            . '-' . str_pad((string) $orden->id, 5, '0', STR_PAD_LEFT);
    }
}
