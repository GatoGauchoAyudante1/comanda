{{--
  Línea de tiempo de un pedido: qué pasó, quién lo hizo y a qué hora.
  Ver docs/11-auditoria.md.
--}}
@props(['eventos', 'conFecha' => false])

@forelse ($eventos as $e)
    <div class="evento">
        <div class="evento-hora">
            @if ($conFecha)
                <div class="fs12 t-mute">{{ $e->created_at->format('d/m') }}</div>
            @endif
            {{ $e->created_at->format('H:i') }}
        </div>

        <div class="evento-linea">
            <span class="evento-punto {{ 'punto-' . $e->color() }}"></span>
        </div>

        <div class="evento-cuerpo">
            <div class="fs15">{{ $e->description }}</div>
            <div class="fs13 t-mute mt4">{{ $e->responsable() }}</div>
        </div>
    </div>
@empty
    <p class="t-mute fs14">Sin movimientos registrados.</p>
@endforelse
