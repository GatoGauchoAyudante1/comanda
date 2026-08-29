<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\StockMovement;
use App\Models\TableSession;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Reportes del dueño. Ver mockups-html/13-reportes.html
 *
 * Todo se calcula sobre el DÍA OPERATIVO (`business_date`), no sobre la fecha
 * calendario: un turno que arranca a las 19:00 y cierra a las 03:00 es un solo
 * día. Ver docs/09-pendientes.md · T-03.
 */
class ReporteController extends Controller
{
    /**
     * Tope de días de un período a medida.
     *
     * El costo real recorre en PHP los movimientos de stock del período (ver
     * resumen()): con un «desde» de hace cinco años eso es traerse la tabla
     * entera a memoria. Un año es más de lo que nadie compara de una sentada.
     */
    private const MAX_DIAS = 366;

    public function __invoke(Request $request): View
    {
        ['rango' => $rango, 'desde' => $desde, 'hasta' => $hasta, 'recortado' => $recortado]
            = $this->periodo($request);

        // Mismo largo de período, corrido hacia atrás, para comparar.
        $largo = $desde->diffInDays($hasta) + 1;
        [$desdeAnt, $hastaAnt] = [$desde->copy()->subDays($largo), $desde->copy()->subDay()];

        return view('reportes', [
            'rango'     => $rango,
            'desde'     => $desde,
            'hasta'     => $hasta,
            'recortado' => $recortado,
            'maxDias'   => self::MAX_DIAS,
            'actual'    => $this->resumen($desde, $hasta),
            'anterior'  => $this->resumen($desdeAnt, $hastaAnt),
            'porHora'   => $this->porHora($desde, $hasta),
            'medios'    => $this->medios($desde, $hasta),
            'ranking'   => $this->ranking($desde, $hasta),
            'canales'   => $this->canales($desde, $hasta),
            'pool'      => $this->pool($desde, $hasta),
        ]);
    }

    /**
     * Qué período se está mirando.
     *
     * Los tres botones (hoy · semana · mes) resuelven la pregunta de todos los
     * días. Las fechas sueltas son para la otra: el finde largo, el mes ya
     * cerrado, el día que hubo lío y hay que ir a mirarlo.
     *
     * Es tolerante a propósito. Esto entra por la URL y un reporte no es un
     * alta: ante una fecha dada vuelta o una sola punta cargada muestra algo
     * razonable, en vez de devolver al dueño a un formulario con un error.
     *
     * @return array{rango:string, desde:Carbon, hasta:Carbon, recortado:bool}
     */
    private function periodo(Request $request): array
    {
        $desde = $this->fecha($request->query('desde'));
        $hasta = $this->fecha($request->query('hasta'));

        // Sin fechas manda el botón, que es el camino de siempre.
        if (! $desde && ! $hasta) {
            $rango = $request->string('rango')->toString() ?: 'hoy';
            [$desde, $hasta] = $this->preset($rango);

            return ['rango' => $rango, 'desde' => $desde, 'hasta' => $hasta, 'recortado' => false];
        }

        // Con una sola punta cargada, el período es ese día solo: es lo que se
        // está pidiendo cuando se elige una fecha y se aprieta Ver.
        $desde ??= $hasta;
        $hasta ??= $desde;

        // Al revés se dan vuelta: quien las cargó igual quería mirar esos días.
        if ($desde->gt($hasta)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $recortado = $desde->diffInDays($hasta) + 1 > self::MAX_DIAS;

        // Se recorta y la pantalla lo dice. Devolver los números de un período
        // distinto al pedido, sin avisar, es peor que no devolverlos.
        if ($recortado) {
            $desde = $hasta->copy()->subDays(self::MAX_DIAS - 1);
        }

        return ['rango' => 'personalizado', 'desde' => $desde, 'hasta' => $hasta, 'recortado' => $recortado];
    }

    /**
     * Una fecha de la URL, o null si no vino o no se entiende.
     *
     * El input date siempre manda Y-m-d. Cualquier otra cosa es alguien
     * escribiendo en la barra de direcciones, y se ignora en silencio. El
     * formato se compara de ida y de vuelta porque createFromFormat() acepta
     * un 2026-02-31 y lo corre solo al 3 de marzo.
     */
    private function fecha(mixed $valor): ?Carbon
    {
        if (! is_string($valor) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return null;
        }

        try {
            $fecha = Carbon::createFromFormat('Y-m-d', $valor)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        return $fecha->format('Y-m-d') === $valor ? $fecha : null;
    }

    /** @return array{0:Carbon,1:Carbon} */
    private function preset(string $rango): array
    {
        // "Hoy" es el día operativo del turno abierto, no la fecha del reloj.
        $hoy = CashSession::actual()?->opened_at->copy()->startOfDay() ?? Carbon::today();

        return match ($rango) {
            'semana' => [$hoy->copy()->subDays(6), $hoy],
            'mes'    => [$hoy->copy()->startOfMonth(), $hoy],
            default  => [$hoy, $hoy],
        };
    }

    private function pedidos(Carbon $desde, Carbon $hasta)
    {
        return Order::where('status', 'paid')
            ->whereBetween('business_date', [$desde->toDateString(), $hasta->toDateString()]);
    }

    private function resumen(Carbon $desde, Carbon $hasta): array
    {
        $pedidos = $this->pedidos($desde, $hasta);
        $vendido = (int) $pedidos->clone()->sum('total');
        $tickets = $pedidos->clone()->count();

        // Costo real: lo que efectivamente se descontó del stock por esas ventas.
        $costo = (int) round(
            StockMovement::where('type', 'sale')
                ->whereIn('source_id', $pedidos->clone()->select('id'))
                ->where('source_type', Order::class)
                ->get()
                ->sum(fn ($m) => abs($m->qty) * $m->cost)
        );

        return [
            'vendido'  => $vendido,
            'tickets'  => $tickets,
            'promedio' => $tickets > 0 ? (int) round($vendido / $tickets) : 0,
            'costo'    => $costo,
            'costoPct' => $vendido > 0 ? round($costo / $vendido * 100, 1) : 0,
        ];
    }

    private function porHora(Carbon $desde, Carbon $hasta): array
    {
        $filas = $this->pedidos($desde, $hasta)
            ->selectRaw('HOUR(closed_at) AS hora, SUM(total) AS total')
            ->groupBy('hora')
            ->pluck('total', 'hora');

        if ($filas->isEmpty()) {
            return [];
        }

        // Se muestra desde la primera hora con ventas hasta la última.
        $horas = $filas->keys()->sort()->values();
        $desdeH = (int) $horas->first();
        $hastaH = (int) $horas->last();

        // Un turno de noche cruza la medianoche: 19..23 y después 0..3.
        $secuencia = $desdeH <= $hastaH
            ? range($desdeH, $hastaH)
            : array_merge(range($desdeH, 23), range(0, $hastaH));

        $max = (int) $filas->max();

        return collect($secuencia)->map(fn ($h) => [
            'hora'  => str_pad((string) $h, 2, '0', STR_PAD_LEFT),
            'total' => (int) ($filas[$h] ?? 0),
            'alto'  => $max > 0 ? max(2, (int) round(($filas[$h] ?? 0) / $max * 100)) : 0,
            'pico'  => ($filas[$h] ?? 0) === $max,
        ])->all();
    }

    private function medios(Carbon $desde, Carbon $hasta): array
    {
        $pagos = Payment::whereIn('order_id', $this->pedidos($desde, $hasta)->select('id'))
            ->selectRaw('method, SUM(amount) AS total')
            ->groupBy('method')
            ->pluck('total', 'method');

        $total = (int) $pagos->sum();

        $nombres = [
            'cash' => 'Efectivo', 'qr' => 'QR / MP', 'transfer' => 'Transferencia',
            'debit' => 'Débito', 'credit' => 'Crédito', 'other' => 'Otro',
        ];
        // Variables CSS: los graficos cambian con el tema (ver sistema.css).
        $colores = ['var(--serie-1)', 'var(--serie-2)', 'var(--serie-3)', 'var(--serie-4)', 'var(--txt-3)', 'var(--line-2)'];

        return [
            'total'  => $total,
            'lineas' => $pagos->sortDesc()->values()->map(fn ($monto, $i) => [
                'nombre' => $nombres[$pagos->sortDesc()->keys()[$i]] ?? $pagos->sortDesc()->keys()[$i],
                'monto'  => (int) $monto,
                'pct'    => $total > 0 ? round($monto / $total * 100, 1) : 0,
                'color'  => $colores[$i] ?? '#55635D',
            ])->all(),
        ];
    }

    private function ranking(Carbon $desde, Carbon $hasta): array
    {
        return OrderItem::whereIn('order_id', $this->pedidos($desde, $hasta)->select('id'))
            ->selectRaw('product_id, SUM(qty) AS unidades, SUM(qty * unit_price) AS monto')
            ->groupBy('product_id')
            ->orderByDesc('monto')
            ->with('product')
            ->take(6)
            ->get()
            ->map(fn ($f) => [
                'nombre'   => $f->product->name,
                'unidades' => (int) $f->unidades,
                'monto'    => (int) $f->monto,
            ])->all();
    }

    private function canales(Carbon $desde, Carbon $hasta): array
    {
        $filas = $this->pedidos($desde, $hasta)
            ->selectRaw('type, SUM(total) AS total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $total = (int) $filas->sum();

        $nombres = [
            'mesa_salon' => 'Salón', 'mesa_pool' => 'Pool', 'delivery' => 'Delivery',
            'retiro' => 'Retiro', 'mostrador' => 'Mostrador',
        ];

        return $filas->sortDesc()->map(fn ($monto, $tipo) => [
            'nombre' => $nombres[$tipo] ?? $tipo,
            'monto'  => (int) $monto,
            'pct'    => $total > 0 ? round($monto / $total * 100, 1) : 0,
        ])->values()->all();
    }

    private function pool(Carbon $desde, Carbon $hasta): array
    {
        $sesiones = TableSession::whereIn('order_id', $this->pedidos($desde, $hasta)->select('id'))
            ->whereNotNull('ended_at')
            ->with('table')
            ->get();

        $minutos = $sesiones->sum(fn (TableSession $s) => $s->minutosCobrados());

        return [
            'horas'    => round($minutos / 60, 1),
            'ingreso'  => (int) $this->pedidos($desde, $hasta)->sum('time_amount'),
            'sesiones' => $sesiones->count(),
            'topMesa'  => $sesiones->groupBy('table.name')
                ->map(fn ($g) => $g->sum(fn ($s) => $s->minutosCobrados()))
                ->sortDesc()
                ->take(1)
                ->map(fn ($min, $nombre) => ['nombre' => $nombre, 'horas' => round($min / 60, 1)])
                ->first(),
        ];
    }
}
