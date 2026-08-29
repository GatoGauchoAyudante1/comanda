<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\Bitacora;
use App\Support\Negocio;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Comprobante no fiscal para impresora térmica de 80 mm.
 *
 * En el MVP se imprime por el navegador, sin agente local: alcanza con que la
 * impresora tenga driver de Windows. Ver docs/09-pendientes.md · P-04 y T-02.
 */
class TicketController extends Controller
{
    public function mostrar(Request $request, Order $orden): View
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

            // El detalle se reescribe con la cuenta ya cerrada y sólo lo hace
            // quien cobra: el mozo imprime la precuenta, no el comprobante.
            'puedeEditarDetalle' => Negocio::detalleTicketEditable()
                && $orden->status === 'paid'
                && $request->user()->role !== 'mozo',
            'plantillas' => Negocio::detallesTicket(),

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

    /**
     * Reemplaza la lista de consumos por un texto, o la devuelve a como estaba.
     *
     * El total no se toca: acá sólo se cambia cómo se lee el comprobante.
     * Ver docs/06-reglas-negocio.md · R-40.
     */
    public function guardarDetalle(Request $request, Order $orden): RedirectResponse
    {
        abort_unless(Negocio::detalleTicketEditable(), 404);

        if ($orden->status !== 'paid') {
            return back()->with('error', 'El detalle se cambia con la cuenta ya cobrada.');
        }

        $datos = $request->validate([
            'detalle' => ['nullable', 'string', 'max:40'],
        ]);

        $antes   = $orden->receipt_detail;
        $detalle = trim($datos['detalle'] ?? '') ?: null;

        if ($detalle === $antes) {
            return redirect()->route('ticket', ['orden' => $orden, 'imprimir' => 1]);
        }

        $orden->update(['receipt_detail' => $detalle]);

        // Queda en la bitácora: el comprobante que se llevó el cliente no dice
        // lo mismo que la comanda, y eso tiene que poder explicarse después.
        Bitacora::registrar(
            'ticket.detalle',
            $detalle
                ? "Imprimió el comprobante como «{$detalle}»"
                : 'Devolvió el comprobante a su detalle completo',
            $orden,
            array_filter(['antes' => $antes, 'ahora' => $detalle]),
        );

        // Se vuelve al ticket ya listo para salir. Devolverlo al detalle
        // completo no es para entregar, así que ese no se autoimprime.
        return redirect()->route('ticket', $detalle
            ? ['orden' => $orden, 'imprimir' => 1]
            : ['orden' => $orden]);
    }

    /** Formato 0001-00002: punto de venta y correlativo. Ver T-03. */
    private function numero(Order $orden): string
    {
        return Negocio::puntoDeVenta()
            . '-' . str_pad((string) $orden->id, 5, '0', STR_PAD_LEFT);
    }
}
