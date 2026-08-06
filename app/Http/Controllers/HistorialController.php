<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\CashSession;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * El historial del local. Ver docs/11-auditoria.md.
 *
 * Existe porque las pantallas de operación muestran sólo lo que está en curso:
 * apenas se cobra una mesa, desaparece del panel. Cuando al rato aparece un
 * reclamo, este es el único lugar donde queda lo que pasó.
 */
class HistorialController extends Controller
{
    public function __invoke(Request $request): View
    {
        $eventos = AuditEvent::query()
            ->when($request->filled('dia'), fn ($q) => $q->whereDate('business_date', $request->date('dia')))
            ->when($request->filled('usuario'), fn ($q) => $q->where('user_id', $request->integer('usuario')))
            ->when($request->filled('familia'), fn ($q) => $q->where('type', 'like', $request->string('familia') . '.%'))
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $texto = $request->string('buscar')->toString();

                // Un número suelto se interpreta como número de comprobante:
                // es lo que trae el cliente cuando reclama.
                if (ctype_digit($texto)) {
                    $ids = Order::where('number', (int) $texto)->pluck('id');

                    return $q->where(fn ($sub) => $sub
                        ->where('description', 'like', "%{$texto}%")
                        ->orWhere(fn ($o) => $o->where('subject_type', Order::class)->whereIn('subject_id', $ids)));
                }

                return $q->where('description', 'like', "%{$texto}%");
            })
            ->with('user')
            ->latest('created_at')
            ->latest('id')
            ->paginate(60)
            ->withQueryString();

        // Los pedidos que aparecen, para poder saltar a su detalle.
        $pedidos = Order::whereIn('id', $eventos->getCollection()
                ->where('subject_type', Order::class)
                ->pluck('subject_id')
                ->unique())
            ->with('tableSession.table', 'delivery.customer')
            ->get()
            ->keyBy('id');

        return view('historial', [
            'eventos'  => $eventos,
            'pedidos'  => $pedidos,
            'usuarios' => User::orderBy('name')->get(),
            'dias'     => $this->diasConActividad(),
            'filtros'  => [
                'dia'     => $request->string('dia')->toString(),
                'usuario' => $request->integer('usuario'),
                'familia' => $request->string('familia')->toString(),
                'buscar'  => $request->string('buscar')->toString(),
            ],
        ]);
    }

    /** Días operativos con movimientos, del más nuevo al más viejo. */
    private function diasConActividad(): array
    {
        return AuditEvent::query()
            ->selectRaw('business_date, COUNT(*) AS eventos')
            ->whereNotNull('business_date')
            ->groupBy('business_date')
            ->orderByDesc('business_date')
            ->limit(30)
            ->get()
            ->map(fn ($f) => [
                'fecha'   => Carbon::parse($f->business_date),
                'eventos' => (int) $f->eventos,
            ])
            ->all();
    }
}
