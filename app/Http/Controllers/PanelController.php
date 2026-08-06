<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\Table;
use App\Models\TableRate;
use App\Models\User;
use Illuminate\Contracts\View\View;

class PanelController extends Controller
{
    /** Panel de atención. Ver mockups-html/01-panel.html */
    public function __invoke(): View
    {
        $mesas = Table::query()
            ->where('active', true)
            ->with(['sesionAbierta.order', 'sesionAbierta.user'])
            ->orderBy('sort_order')
            ->get()
            ->groupBy('type');

        return view('panel', [
            'mesasPool'  => $mesas->get('pool', collect()),
            'mesasSalon' => $mesas->get('salon', collect()),
            'caja'       => CashSession::actual(),
            'tarifas'    => TableRate::where('active', true)->orderByDesc('is_default')->get(),
            'mozos'      => User::whereIn('role', ['mozo', 'cajero', 'dueno'])->where('active', true)->get(),
        ]);
    }
}
