<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Bitacora;
use App\Support\Negocio;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * KDS: la pantalla de la tablet colgada en la cocina.
 * Ver mockups-html/07-cocina.html
 */
class CocinaController extends Controller
{
    public function index(Request $request): View
    {
        $comandas = Order::query()
            ->whereIn('status', ['open', 'kitchen'])
            ->whereHas('items', fn ($q) => $q->where('status', 'kitchen'))
            ->with([
                'items' => fn ($q) => $q->where('status', 'kitchen')->with('product', 'variant'),
                'tableSession.table',
                'delivery',
            ])
            ->orderBy('created_at')
            ->get();

        return view('cocina', [
            'comandas'    => $comandas,
            'puedeMarcar' => Negocio::puedeMarcarListo($request->user()),
        ]);
    }

    /**
     * Marca la comanda entera como lista.
     *
     * Ver la pantalla y marcar listo son permisos distintos: el cajero mira
     * cómo viene la cocina, pero por defecto no toca nada. Ver R-36.
     */
    public function listo(Request $request, Order $orden): RedirectResponse
    {
        abort_unless(
            Negocio::puedeMarcarListo($request->user()),
            403,
            'Tu rol no puede marcar comandas como listas.',
        );

        $pendientes = $orden->items()->where('status', 'kitchen')->with('product')->get();

        $orden->items()->where('status', 'kitchen')->update([
            'status'   => 'ready',
            'ready_by' => $request->user()->id,
            'ready_at' => now(),
        ]);

        // Los pedidos de delivery avanzan de estado; las mesas siguen abiertas
        // porque el cliente sigue consumiendo.
        if (in_array($orden->type, ['delivery', 'retiro'], true)) {
            $orden->update(['status' => 'ready']);
        }

        Bitacora::registrar(
            'item.listo',
            'Cocina marcó listo: ' . $pendientes->map(
                fn ($i) => "{$i->qty}x {$i->product->name}"
            )->join(', '),
            $orden,
            ['items' => $pendientes->pluck('id')],
        );

        return back()->with('ok', "Comanda #{$orden->number} lista.");
    }

    /** Un solo ítem, cuando la comanda sale en tandas. */
    public function itemListo(Request $request, OrderItem $item): RedirectResponse
    {
        abort_unless(Negocio::puedeMarcarListo($request->user()), 403);

        $item->update(['status' => 'ready']);

        return back();
    }
}
