@extends('layouts.app')

@php
    // Variación contra el período anterior del mismo largo.
    $variacion = function (int $ahora, int $antes): ?array {
        if ($antes === 0) return null;
        $pct = round(($ahora - $antes) / $antes * 100, 1);
        return ['pct' => $pct, 'clase' => $pct >= 0 ? 't-green' : 't-red', 'signo' => $pct >= 0 ? '+' : ''];
    };

    $vsVendido  = $variacion($actual['vendido'], $anterior['vendido']);
    $vsPromedio = $variacion($actual['promedio'], $anterior['promedio']);
    $difTickets = $actual['tickets'] - $anterior['tickets'];
@endphp

@section('titulo', 'Reportes')

@section('topbar')
    <div>
        <h1>Reportes</h1>
        <div class="sub">
            @if ($desde->isSameDay($hasta))
                {{ $desde->translatedFormat('l j \d\e F') }}
            @else
                {{ $desde->format('d/m') }} al {{ $hasta->format('d/m/Y') }}
            @endif
        </div>
    </div>
    <div class="topbar-actions">
        <div class="seg hide-mobile" style="padding:3px">
            @foreach (['hoy' => 'Hoy', 'semana' => 'Semana', 'mes' => 'Mes'] as $clave => $texto)
                <a class="{{ $rango === $clave ? 'is-on' : '' }}" style="height:38px;padding:0 16px;
                          display:inline-flex;align-items:center;border-radius:11px;
                          {{ $rango === $clave ? 'background:var(--green);color:var(--green-ink);font-weight:600' : 'color:var(--txt-2)' }}"
                   href="{{ route('reportes', ['rango' => $clave]) }}">{{ $texto }}</a>
            @endforeach
        </div>
        <button class="btn" onclick="window.print()">Imprimir</button>
    </div>
@endsection

@section('contenido')

    <div class="filters only-mobile">
        @foreach (['hoy' => 'Hoy', 'semana' => 'Semana', 'mes' => 'Mes'] as $clave => $texto)
            <a class="filter {{ $rango === $clave ? 'is-on' : '' }}"
               href="{{ route('reportes', ['rango' => $clave]) }}">{{ $texto }}</a>
        @endforeach
    </div>

    @if ($actual['tickets'] === 0)
        <div class="notice mb16">
            <span class="dot dot-mute"></span>
            <div>
                <div class="tt">Todavía no hay ventas cerradas en este período</div>
                <div class="ds">Los pedidos cobrados aparecen acá apenas se confirman.</div>
            </div>
        </div>
    @endif

    <div class="stats mb16">
        <div class="stat">
            <div class="label">Vendido</div>
            <div class="val">@plata($actual['vendido'])</div>
            <div class="foot {{ $vsVendido['clase'] ?? '' }}">
                {{ $vsVendido ? $vsVendido['signo'] . $vsVendido['pct'] . '% vs período anterior' : 'sin comparación' }}
            </div>
        </div>
        <div class="stat">
            <div class="label">Tickets</div>
            <div class="val">{{ $actual['tickets'] }}</div>
            <div class="foot {{ $difTickets >= 0 ? 't-green' : 't-red' }}">
                {{ $difTickets >= 0 ? '+' : '' }}{{ $difTickets }} vs período anterior
            </div>
        </div>
        <div class="stat">
            <div class="label">Ticket promedio</div>
            <div class="val">@plata($actual['promedio'])</div>
            <div class="foot {{ $vsPromedio['clase'] ?? '' }}">
                {{ $vsPromedio ? $vsPromedio['signo'] . $vsPromedio['pct'] . '%' : 'sin comparación' }}
            </div>
        </div>
        <div class="stat">
            <div class="label">Costo de insumos</div>
            <div class="val">@plata($actual['costo'])</div>
            <div class="foot">{{ $actual['costoPct'] }}% de la venta</div>
        </div>
    </div>

    <div class="duo">

        {{-- ============ ventas por hora ============ --}}
        <div class="card">
            <div class="sec">
                Ventas por hora
                @if ($porHora)
                    <span class="meta">
                        Pico a las {{ collect($porHora)->firstWhere('pico', true)['hora'] ?? '—' }}:00
                    </span>
                @endif
            </div>

            @if ($porHora)
                <div class="bars">
                    @foreach ($porHora as $h)
                        <div class="b {{ $h['pico'] ? 'peak' : '' }}" title="@plata($h['total'])">
                            <i style="height:{{ $h['alto'] }}%"></i>
                            <span>{{ $h['hora'] }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="t-mute fs14">Sin datos en este período.</p>
            @endif
        </div>

        {{-- ============ medios de pago ============ --}}
        <div class="card">
            <div class="sec">Medios de pago</div>

            @if ($medios['total'] > 0)
                @php
                    $r = 60; $circ = 2 * M_PI * $r; $offset = 0;
                @endphp
                <div class="flex g18 wrap">
                    <svg width="176" height="176" viewBox="0 0 176 176" style="flex:none">
                        <g transform="rotate(-90 88 88)">
                            @foreach ($medios['lineas'] as $l)
                                @php
                                    $largo = $circ * $l['pct'] / 100;
                                @endphp
                                <circle cx="88" cy="88" r="{{ $r }}" fill="none" style="stroke:{{ $l['color'] }}"
                                        stroke-width="22"
                                        stroke-dasharray="{{ round(max(0, $largo - 2), 1) }} {{ round($circ, 1) }}"
                                        stroke-dashoffset="{{ round(-$offset, 1) }}"></circle>
                                @php $offset += $largo; @endphp
                            @endforeach
                        </g>
                        <text x="88" y="84" text-anchor="middle" style="fill:var(--txt)" font-size="22"
                              font-weight="700" font-family="Outfit,sans-serif">
                            {{ \App\Support\Plata::format($medios['total']) }}
                        </text>
                        <text x="88" y="105" text-anchor="middle" style="fill:var(--txt-2)" font-size="13"
                              font-family="Outfit,sans-serif">{{ $actual['tickets'] }} tickets</text>
                    </svg>

                    <div class="grow" style="min-width:170px">
                        @foreach ($medios['lineas'] as $l)
                            <div class="lv">
                                <span class="k">
                                    <span class="dot" style="display:inline-block;margin-right:8px;background:{{ $l['color'] }}"></span>
                                    {{ $l['nombre'] }} · {{ $l['pct'] }}%
                                </span>
                                <span class="v">@plata($l['monto'])</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <p class="t-mute fs14">Sin cobros en este período.</p>
            @endif
        </div>
    </div>

    <div class="trio mt16">

        {{-- ============ más vendidos ============ --}}
        <div class="card">
            <div class="sec">Más vendidos</div>
            @forelse ($ranking as $i => $p)
                <div class="rank">
                    <span class="pos {{ $i < 3 ? 'top' : '' }}">{{ $i + 1 }}</span>
                    <div class="grow">
                        <div class="fw5">{{ $p['nombre'] }}</div>
                        <div class="fs13 t-mute">{{ $p['unidades'] }} unidades</div>
                    </div>
                    <span class="fw6">@plata($p['monto'])</span>
                </div>
            @empty
                <p class="t-mute fs14">Sin ventas.</p>
            @endforelse
        </div>

        {{-- ============ por canal ============ --}}
        <div class="card">
            <div class="sec">Por canal</div>
            @forelse ($canales as $c)
                <div class="mb16">
                    <div class="between fs14 mb8">
                        <span class="fw5">{{ $c['nombre'] }}</span>
                        <span class="t-dim">{{ $c['pct'] }}% · @plata($c['monto'])</span>
                    </div>
                    <div class="meter"><i style="width:{{ $c['pct'] }}%"></i></div>
                </div>
            @empty
                <p class="t-mute fs14">Sin ventas.</p>
            @endforelse
        </div>

        {{-- ============ mesas de pool ============ --}}
        @if (\App\Support\Negocio::modulo('pool'))
            <div class="card">
                <div class="sec">Mesas de pool</div>

                <div class="mb16">
                    <div class="fs13 t-dim">Horas vendidas</div>
                    <div class="money m-lg mt4">{{ number_format($pool['horas'], 1, ',', '.') }} hs</div>
                </div>
                <div class="hr"></div>
                <div class="mb16">
                    <div class="fs13 t-dim">Ingreso por tiempo</div>
                    <div class="money m-lg mt4">@plata($pool['ingreso'])</div>
                    <div class="fs13 t-mute mt4">Sin contar los consumos de las mesas</div>
                </div>
                <div class="hr"></div>
                <div>
                    <div class="between fs13">
                        <span class="t-dim">Mesas cerradas</span>
                        <span class="fw6">{{ $pool['sesiones'] }}</span>
                    </div>
                    @if ($pool['topMesa'])
                        <div class="fs13 t-mute mt12">
                            Más usada: {{ $pool['topMesa']['nombre'] }}
                            · {{ number_format($pool['topMesa']['horas'], 1, ',', '.') }} hs
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>

    @if ($actual['vendido'] > 0)
        @php $pct = $actual['costoPct']; @endphp
        <div class="notice mt16 {{ $pct > 35 ? 'notice-amber' : 'notice-green' }}">
            <span class="dot {{ $pct > 35 ? 'dot-amber' : '' }}"></span>
            <div>
                <div class="tt">Costo de insumos en {{ $pct }}% de la venta</div>
                <div class="ds">
                    @if ($pct > 35)
                        Está por encima de lo esperado en gastronomía (25-35%).
                        Mirá primero mermas y precios de compra.
                    @elseif ($pct < 10)
                        Parece bajo. Suele significar que faltan recetas cargadas:
                        lo que no tiene receta no descuenta stock ni suma costo.
                    @else
                        Está dentro de lo esperado para gastronomía (25-35%).
                    @endif
                </div>
            </div>
        </div>
    @endif
@endsection
