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

    /* Reescribir el detalle: sólo en pantalla, igual que las acciones. */
    .editor {
        width: 80mm; margin: 0 auto 12px; padding: 14px;
        background: #1a1f1d; color: #e8ece9;
        border: 1px solid #333b37; border-radius: 12px;
        font-family: system-ui, sans-serif;
    }
    .editor .et { font-size: 14px; font-weight: 700; }
    .editor .ep { margin: 4px 0 12px; font-size: 12.5px; line-height: 1.45; color: #98a29c; }
    .editor .chips { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }
    .editor .fila { display: flex; gap: 8px; }
    .editor input {
        flex: 1; min-width: 0; height: 40px; padding: 0 10px;
        font-size: 14px; font-family: inherit; color: #e8ece9;
        background: #121614; border: 1px solid #4a534f; border-radius: 8px;
    }
    .editor .chip {
        padding: 6px 12px; font-size: 12.5px; font-family: inherit; cursor: pointer;
        color: #e8ece9; background: #232926;
        border: 1px solid #4a534f; border-radius: 999px;
    }
    .editor .chip:hover { color: #28be7e; border-color: #28be7e; }
    .editor .guardar {
        padding: 0 14px; height: 40px; font-size: 13px; font-weight: 600;
        font-family: inherit; cursor: pointer; white-space: nowrap;
        color: #04150c; background: #28be7e; border: none; border-radius: 8px;
    }
    .editor .deshacer {
        margin-top: 10px; padding: 0; font-size: 12.5px; font-family: inherit;
        color: #98a29c; background: none; border: none; cursor: pointer;
        text-decoration: underline;
    }

    @media print {
        body { background: #fff; }
        .papel { margin: 0; box-shadow: none; width: auto; min-height: 0; }
        .acciones, .editor { display: none !important; }
    }
</style>
</head>
<body>

<div class="papel">

    @php
        $cobrado = $orden->status === 'paid';
        // Detalle pedido por el cliente: una línea en vez de los consumos (R-40).
        // Se respeta siempre que esté guardado, aunque después apaguen la
        // opción: un comprobante reimpreso tiene que salir como el original.
        $resumen = $orden->receipt_detail;
    @endphp

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

        @if ($resumen)

            <tr>
                <td class="cant"></td>
                <td>{{ $resumen }}</td>
                <td class="imp">{{ $importe($orden->total) }}</td>
            </tr>

        @else

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

        @endif
    </table>

    <div class="sep"></div>

    <table>
        {{-- La apertura del total sólo se imprime con el detalle completo:
             desglosar consumos y tiempo delataría lo que el resumen oculta. --}}
        @if (! $resumen)
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

@if ($puedeEditarDetalle)
    {{--
      Cambiar el detalle antes de entregar el comprobante.

      Lo pide el cliente que rinde gastos: necesita el ticket, pero no que en
      la empresa se lea qué consumió. El total no se toca — acá sólo se elige
      cómo se lee. Ver docs/06-reglas-negocio.md · R-40.
    --}}
    <div class="editor">
        <div class="et">Detalle del comprobante</div>
        <p class="ep">
            Reemplaza la lista de consumos por un texto. El total no cambia
            y en el sistema queda todo como está.
        </p>

        <form id="fdetalle" method="POST" action="{{ route('ticket.detalle', $orden) }}">
            @csrf

            @if ($plantillas)
                <div class="chips">
                    @foreach ($plantillas as $plantilla)
                        <button class="chip" type="button" data-texto="{{ $plantilla }}">{{ $plantilla }}</button>
                    @endforeach
                </div>
            @endif

            <div class="fila">
                <input id="detalle" name="detalle" maxlength="40" autocomplete="off"
                       value="{{ $resumen }}" placeholder="Ej: Almuerzo">
                <button class="guardar" type="submit">Guardar e imprimir</button>
            </div>
        </form>

        @if ($resumen)
            <form method="POST" action="{{ route('ticket.detalle', $orden) }}">
                @csrf
                <input type="hidden" name="detalle" value="">
                <button class="deshacer" type="submit">Volver al detalle completo</button>
            </form>
        @endif
    </div>

    {{-- Un toque en el texto frecuente ya imprime: es el caso de todos los días. --}}
    <script>
        document.querySelectorAll('.editor .chip').forEach((boton) => {
            boton.addEventListener('click', () => {
                document.getElementById('detalle').value = boton.dataset.texto;
                document.getElementById('fdetalle').submit();
            });
        });
    </script>
@endif

<div class="acciones">
    <a href="{{ $volver }}">Volver</a>
    <button class="primario" onclick="window.print()">Imprimir</button>
</div>

@if (request()->boolean('imprimir'))
    <script>window.addEventListener('load', () => window.print());</script>
@endif

</body>
</html>
