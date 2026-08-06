{{-- Tarjeta de mesa del panel. Ver mockups-html/01-panel.html --}}
@php
    $sesion = $mesa->sesionAbierta;
    $plata  = fn ($c) => '$' . number_format(($c ?? 0) / 100, 0, ',', '.');
@endphp

@if ($sesion)
    @php
        // El tiempo se calcula en el servidor, siempre. Ver R-01.
        $minutos = $sesion->minutosJugados();
        $alerta  = $esPool && $minutos >= 120;
        $avance  = $esPool ? min(100, (int) round($minutos / 150 * 100)) : 0;
    @endphp

    <a class="tcard {{ $esPool ? 'tcard--pool' : '' }}" href="{{ route('mesa', $sesion) }}">
        <div class="between">
            <span class="name">{{ $mesa->name }}</span>
            @if ($esPool)
                <span class="chip {{ $alerta ? 'chip-red' : 'chip-amber' }}">{{ $sesion->tiempoLegible() }} hs</span>
            @elseif ($sesion->guests)
                <span class="chip chip-line">{{ $sesion->guests }} personas</span>
            @endif
        </div>

        <div class="money">{{ $plata($sesion->order?->total) }}</div>

        <div class="note">
            desde {{ $sesion->started_at->format('H:i') }}
            · {{ $sesion->order?->items()->count() ?? 0 }} consumos
            · {{ $sesion->user->name }}
        </div>

        @if ($esPool)
            <div class="tprog {{ $alerta ? 'hot' : '' }}"><i style="width:{{ $avance }}%"></i></div>
        @endif
    </a>
@else
    <div class="tcard tcard--free"
         @click="abrir = true; mesaId = {{ $mesa->id }}; mesa = '{{ $mesa->name }}'; pool = {{ $esPool ? 'true' : 'false' }}">
        <span class="name">{{ $mesa->name }}</span>
        <span class="flex g6 fs13 t-dim"><span class="dot"></span>libre</span>
    </div>
@endif
