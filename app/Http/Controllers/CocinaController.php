<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

/**
 * KDS: la pantalla de la tablet colgada en la cocina.
 * Ver mockups-html/07-cocina.html
 */
class CocinaController extends Controller
{
    public function index(): View
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

        return view('cocina', ['comandas' => $comandas]);
    }

    /** Cocina marca la comanda entera como lista. */
    public function listo(Order $orden): RedirectResponse
    {
        $orden->items()->where('status', 'kitchen')->update([
            'status' => 'ready',
        ]);

        // Los pedidos de delivery avanzan de estado; las mesas siguen abiertas
        // porque el cliente sigue consumiendo.
        if (in_array($orden->type, ['delivery', 'retiro'], true)) {
            $orden->update(['status' => 'ready']);
        }

        return back()->with('ok', "Comanda #{$orden->number} lista.");
    }

    /** Un solo ítem, cuando la comanda sale en tandas. */
    public function itemListo(OrderItem $item): RedirectResponse
    {
        $item->update(['status' => 'ready']);

        return back();
    }
}
