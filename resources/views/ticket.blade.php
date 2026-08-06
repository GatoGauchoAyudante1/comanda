<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Comprobante {{ $numero }}</title>

{{--
  Hoja autocontenida a propósito: no usa @vite ni el CSS de la aplicación.
  Un ticket que sale mal impreso por un cambio de Tailwind es un problema
  que nadie quiere depurar un sábado a la noche.

  Ancho de papel 80 mm · área imprimible ~72 mm · 32 caracteres por línea.
--}}
<style>
    @page { size: 80mm auto; margin: 0; }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        font-family: 'Courier New', Courier, monospace;
        font-size: 12px;
        line-height: 1.4;
        color: #000;
        background: #2a2f2d;
    }

    .papel {
        width: 80mm;
        min-height: 100mm;
        margin: 24px auto;
        padding: 5mm 4mm;
        background: #fff;
        box-shadow: 0 8px 40px rgba(0,0,0,.5);
    }

    .centro { text-align: center; }
    .der    { text-align: right; }
    .fuerte { font-weight: bold; }
    .grande { font-size: 15px; font-weight: bold; }
    .chico  { font-size: 10.5px; }

    .sep  { border-top: 1px dashed #000; margin: 6px 0; }
    .sep2 { border-top: 1px solid #000;  margin: 6px 0; }

    table { width: 100%; border-collapse: collapse; }
    td { padding: 1px 0; vertical-align: top; }
    .cant   { width: 22px; }
    .imp    { text-align: right; white-space: nowrap; padding-left: 6px; }
    .nota   { padding-left: 22px; font-size: 10.5px; }

    .total td { font-size: 16px; font-weight: bold; padding-top: 3px; }

    /* Barra de acciones: sólo en pantalla, nunca en el papel. */
    .acciones {
        width: 80mm; margin: 0 auto 40px; display: flex; gap: 8px;
        font-family: system-ui, sans-serif;
    }
    .acciones a, .acciones button {
        flex: 1; height: 44px; border-radius: 10px; cursor: pointer;
        font-size: 14px; font-weight: 600; border: 1px solid #4a534f;
        background: #1a1f1d; color: #e8ece9; text-decoration: none;
        display: inline-flex; align-items: center; justify-content: center;
    }
    .acciones .primario { background: #28be7e; color: #04150c; border-color: transparent; }

    @media print {
        body { background: #fff; }
        .papel { margin: 0; box-shadow: none; width: auto; min-height: 0; }
        .acciones { display: none !important; }
    }
</style>
</head>
<body>

<div class="papel">

    @php $cobrado = $orden->status === 'paid'; @endphp

    <div class="centro grande">{{ mb_strtoupper(\App\Support\Negocio::nombre()) }}</div>

    @if ($cobrado)
        <div class="centro chico">Comprobante no fiscal</div>
        <div class="centro fuerte" style="margin-top:4px">N° {{ $numero }}</div>
    @else
        {{-- La precuenta no es comprobante: no lleva número ni acredita pago. --}}
        <div class="centro grande" style="margin-top:4px">PRECUENTA</div>
        <div class="centro chico">No acredita el pago</div>
    @endif

    <div class="sep"></div>

    <table>
        <tr>
            <td>{{ $orden->closed_at?->format('d/m/Y') ?? now()->format('d/m/Y') }}</td>
            <td class="der">{{ $orden->closed_at?->format('H:i') ?? now()->format('H:i') }}</td>
        </tr>
        <tr>
            <td colspan="2">{{ $encabezado }}</td>
        </tr>
        @if ($orden->tableSession)
            <tr><td colspan="2">Atendió: {{ $orden->tableSession->user->name }}</td></tr>
        @endif
        <tr><td colspan="2">Cajero: {{ $orden->payments->first()?->user->name ?? $orden->user->name }}</td></tr>
    </table>

    <div class="sep"></div>

    <table>
        <tr class="chico fuerte">
            <td class="cant">CANT</td>
            <td>DETALLE</td>
            <td class="imp">IMPORTE</td>
        </tr>

        @foreach ($orden->items as $item)
            <tr>
                <td class="cant">{{ $item->qty }}</td>
                <td>
                    {{ $item->product->name }}@if ($item->variant) {{ $item->variant->name }}@endif
                </td>
                <td class="imp">{{ $importe($item->subtotal()) }}</td>
            </tr>
            @if ($item->notes)
                <tr><td colspan="3" class="nota">- {{ $item->notes }}</td></tr>
            @endif
        @endforeach

        @if ($orden->time_amount > 0)
            @php $s = $orden->tableSession; @endphp
            <tr>
                <td class="cant"></td>
                <td>Tiempo de mesa
                    {{ intdiv($s->minutosCobrados(), 60) }}:{{ str_pad($s->minutosCobrados() % 60, 2, '0', STR_PAD_LEFT) }} hs</td>
                <td class="imp">{{ $importe($orden->time_amount) }}</td>
            </tr>
            <tr>
                <td colspan="3" class="nota">
                    {{ $s->started_at->format('H:i') }} a {{ $s->ended_at?->format('H:i') }}
                    · {{ $importe($s->rate_price_per_hour) }}/hora
                </td>
            </tr>
        @endif
    </table>

    <div class="sep"></div>

    <table>
        @if ($orden->time_amount > 0)
            <tr><td>Consumos</td><td class="imp">{{ $importe($orden->items_total) }}</td></tr>
            <tr><td>Tiempo</td><td class="imp">{{ $importe($orden->time_amount) }}</td></tr>
        @endif
        @if ($orden->delivery_fee > 0)
            <tr><td>Envío</td><td class="imp">{{ $importe($orden->delivery_fee) }}</td></tr>
        @endif
        @if ($orden->discount > 0)
            <tr><td>Descuento</td><td class="imp">-{{ $importe($orden->discount) }}</td></tr>
        @endif
        <tr class="total"><td>TOTAL</td><td class="imp">{{ $importe($orden->total) }}</td></tr>
    </table>

    @if ($cobrado)
    <div class="sep2"></div>

    <table>
        <tr class="chico fuerte"><td colspan="2">FORMA DE PAGO</td></tr>
        @foreach ($orden->payments as $pago)
            <tr>
                <td>{{ $nombreMetodo($pago->method) }}</td>
                <td class="imp">{{ $importe($pago->amount) }}</td>
            </tr>
            @if ($pago->vuelto() > 0)
                <tr>
                    <td class="nota" style="padding-left:0">Recibido {{ $importe($pago->received) }} · vuelto</td>
                    <td class="imp">{{ $importe($pago->vuelto()) }}</td>
                </tr>
            @endif
        @endforeach
    </table>
    @endif

    <div class="sep"></div>

    <div class="centro chico" style="margin-top:8px">
        @if ($cobrado)
            ¡Gracias por tu visita!<br>
            Este comprobante no tiene validez fiscal.
        @else
            Detalle de consumo para revisar.<br>
            Solicitá el comprobante al pagar.
        @endif
    </div>

</div>

<div class="acciones">
    <a href="{{ $volver }}">Volver</a>
    <button class="primario" onclick="window.print()">Imprimir</button>
</div>

@if (request()->boolean('imprimir'))
    <script>window.addEventListener('load', () => window.print());</script>
@endif

</body>
</html>
