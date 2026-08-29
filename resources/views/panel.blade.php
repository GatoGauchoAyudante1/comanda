@extends('layouts.app')

@php
    // Los importes van en centavos. Ver docs/06-reglas-negocio.md · R-31.
    // TODO: mover a un helper o a un Cast cuando haya más pantallas usándolo.
    $plata = fn ($centavos) => '$' . number_format(($centavos ?? 0) / 100, 0, ',', '.');

    $ocupadasPool  = $mesasPool->filter(fn ($m) => $m->sesionAbierta)->count();
    $ocupadasSalon = $mesasSalon->filter(fn ($m) => $m->sesionAbierta)->count();
    $abiertas      = $ocupadasPool + $ocupadasSalon;
@endphp

@section('titulo', $caja ? 'Caja abierta' : 'Caja cerrada')

@section('topbar')
    <span class="dot {{ $caja ? '' : 'dot-red' }}" style="width:9px;height:9px"></span>
    <div>
        <h1>{{ $caja ? 'Caja abierta · turno en curso' : 'Caja cerrada' }}</h1>
        <div class="sub">
            @if ($caja)
                Abrió {{ $caja->openedBy->name }} a las {{ $caja->opened_at->format('H:i') }}
                · {{ $abiertas }} {{ $abiertas === 1 ? 'cuenta abierta' : 'cuentas abiertas' }}
            @else
                Abrí la caja para empezar a operar el turno.
            @endif
        </div>
    </div>
    <div class="topbar-actions">
        <span class="money m-sm hide-mobile" style="margin-right:8px">{{ now()->format('H:i') }}</span>
        @if ($caja)
            <a class="btn hide-mobile" href="{{ route('caja') }}">Cerrar caja</a>
            <button class="btn btn-primary">Venta rápida</button>
        @else
            <a class="btn btn-primary" href="{{ route('caja') }}">Abrir caja</a>
        @endif
    </div>
@endsection

@section('contenido')
@php
    $tarifaPorDefecto = $tarifas->firstWhere('is_default', true)?->id ?? $tarifas->first()?->id ?? 0;
    // Si el que está operando es mozo, se preselecciona a sí mismo.
    $mozoPorDefecto = $mozos->firstWhere('id', auth()->id())?->id ?? $mozos->first()?->id ?? 0;
@endphp

<div x-data="{
    abrir: false,
    mesaId: null,
    mesa: '',
    pool: true,
    tarifa: {{ $tarifaPorDefecto }},
    minutos: 0,
    guests: 2,
    mozo: {{ $mozoPorDefecto }},
}">

    <div class="cols">

        {{-- ================= mesas ================= --}}
        <div>

            @if (\App\Support\Negocio::modulo('pool') && $mesasPool->isNotEmpty())
                <div class="sec">
                    Mesas de pool
                    <span class="meta">{{ $ocupadasPool }} ocupadas · {{ $mesasPool->count() - $ocupadasPool }} libres</span>
                </div>
                <div class="tables">
                    @foreach ($mesasPool as $mesa)
                        @include('partials.mesa-card', ['mesa' => $mesa, 'esPool' => true])
                    @endforeach
                </div>
            @endif

            @if (\App\Support\Negocio::modulo('salon') && $mesasSalon->isNotEmpty())
                <div class="sec {{ \App\Support\Negocio::modulo('pool') ? 'mt26' : '' }}">
                    Mesas de salón
                    <span class="meta">{{ $ocupadasSalon }} ocupadas · {{ $mesasSalon->count() - $ocupadasSalon }} libres</span>
                </div>
                <div class="tables">
                    @foreach ($mesasSalon as $mesa)
                        @include('partials.mesa-card', ['mesa' => $mesa, 'esPool' => false])
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ================= panel derecho ================= --}}
        <div>
            @if (\App\Support\Negocio::modulo('delivery'))
                <div class="pane">
                    <div class="pane-hd"><h3>Delivery y retiro</h3></div>
                    <a class="btn btn-dashed btn-block" href="{{ route('pedidos') }}">+ Nuevo pedido</a>
                </div>
            @endif

            <div class="pane">
                <div class="pane-hd"><h3>Vista del cliente</h3></div>
                <p class="t-mute fs14 mb16">
                    Abrí las pantallas públicas para ver exactamente lo que ve el cliente.
                </p>
                <div class="grid2">
                    <a class="btn btn-soft btn-block" href="{{ route('carta.publica') }}"
                       target="_blank" rel="noopener noreferrer">
                        Ver carta pública
                    </a>
                    <a class="btn btn-primary btn-block" href="{{ route('pedido-online') }}"
                       target="_blank" rel="noopener noreferrer">
                        Ver pedido online
                    </a>
                </div>
            </div>

            <div class="pane">
                <div class="pane-hd"><h3>Resumen del turno</h3></div>
                @if ($caja)
                    <div class="lv"><span class="k">Ventas cobradas</span><span class="v">{{ $plata($caja->payments()->sum('amount')) }}</span></div>
                    <div class="lv"><span class="k">Gastos</span><span class="v">{{ $plata($caja->gastos()) }}</span></div>
                    <div class="lv"><span class="k">Retiros</span><span class="v">{{ $plata($caja->retiros()) }}</span></div>
                    <div class="hr-strong"></div>
                    <div class="lv">
                        <span class="k">Efectivo en caja</span>
                        <span class="v money m-md t-green">{{ $plata($caja->efectivoEsperado()) }}</span>
                    </div>
                @else
                    <p class="t-mute fs14">Sin turno abierto todavía.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- barra inferior --}}
    <div class="dock">
        <div class="dock-inner">
            <div class="grow">
                <div class="fw6">Gastos del turno</div>
                <div class="fs13 t-mute">Hielo, delivery, changas</div>
            </div>
            <span class="money m-md">{{ $plata($caja?->gastos()) }}</span>
            <a class="btn btn-sm" href="{{ route('caja') }}">+ Gasto</a>
        </div>
    </div>

    {{-- ============================================================
         DIÁLOGO: ABRIR MESA · ver mockups-html/01-panel.html#abrir
         ============================================================ --}}
    <div class="overlay" x-show="abrir" x-cloak @click.self="abrir = false" @keydown.escape.window="abrir = false">
        <form class="modal" method="POST"
              :action="'{{ route('mesa.abrir', ['mesa' => '__ID__']) }}'.replace('__ID__', mesaId)">
            @csrf

            <div class="modal-hd">
                <div class="grow">
                    <h2>Abrir <span x-text="mesa"></span></h2>
                    <div class="sub">Mesa libre · son las {{ now()->format('H:i') }}</div>
                </div>
                <button class="xbtn" type="button" @click="abrir = false">&times;</button>
            </div>

            <div class="modal-bd">

                {{-- Tarifa y reloj: sólo pool. La tarifa se congela al abrir (R-03). --}}
                <template x-if="pool">
                    <div>
                        <div class="modal-sec">
                            <div class="opt-lbl">Tarifa</div>
                            <div class="opt-row">
                                @foreach ($tarifas as $t)
                                    <button type="button" class="opt" :class="{ 'is-on': tarifa === {{ $t->id }} }"
                                            @click="tarifa = {{ $t->id }}">
                                        {{ $t->name }} <span class="d">@plata($t->price_per_hour) /h</span>
                                    </button>
                                @endforeach
                            </div>
                            <div class="fs13 t-mute mt12">
                                Se cobra por fracción de {{ $tarifas->first()?->rounding_minutes ?? 30 }} minutos:
                                si juegan 1:25, se cobra 1:30.
                            </div>
                        </div>

                        {{-- Hora de inicio editable y auditada (R-04). --}}
                        <div class="modal-sec">
                            <div class="opt-lbl">El reloj arranca</div>
                            <div class="opt-row">
                                @foreach ([0 => 'Ahora', 10 => 'Hace 10 min', 20 => 'Hace 20 min', 30 => 'Hace 30 min'] as $min => $texto)
                                    <button type="button" class="opt" :class="{ 'is-on': minutos === {{ $min }} }"
                                            @click="minutos = {{ $min }}">
                                        {{ $texto }}
                                        @if ($min === 0)<span class="d">{{ now()->format('H:i') }}</span>@endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </template>

                <div class="modal-sec">
                    <div class="opt-lbl" x-text="pool ? 'Jugadores' : 'Personas'"></div>
                    <div class="stepper">
                        <button type="button" @click="guests = Math.max(1, guests - 1)">&minus;</button>
                        <span class="n" x-text="guests"></span>
                        <button type="button" @click="guests = Math.min(50, guests + 1)">+</button>
                    </div>
                </div>

                <div class="modal-sec">
                    <div class="opt-lbl">Referencia</div>
                    <input class="inp" name="reference" maxlength="120"
                           placeholder="Los del fondo, Juan, cumpleaños…">
                </div>

                <div class="modal-sec">
                    <div class="opt-lbl">Atiende</div>
                    <div class="opt-row">
                        @foreach ($mozos as $mozo)
                            <button type="button" class="opt" :class="{ 'is-on': mozo === {{ $mozo->id }} }"
                                    @click="mozo = {{ $mozo->id }}">{{ $mozo->name }}</button>
                        @endforeach
                    </div>
                </div>

                <template x-if="pool">
                    <div class="notice notice-green mt20">
                        <span class="dot"></span>
                        <div>
                            <div class="tt">El reloj corre en el servidor</div>
                            <div class="ds">Podés cerrar el navegador o apagar la PC: la mesa sigue contando igual.</div>
                        </div>
                    </div>
                </template>

                {{-- Lo que realmente se envía --}}
                <input type="hidden" name="table_rate_id" :value="pool ? tarifa : ''">
                <input type="hidden" name="minutos_atras" :value="pool ? minutos : 0">
                <input type="hidden" name="guests" :value="guests">
                <input type="hidden" name="mozo_id" :value="mozo">
            </div>

            <div class="modal-ft">
                <div class="grow fs13 t-mute hide-mobile">Se puede cambiar la tarifa antes de cobrar.</div>
                <button class="btn btn-primary" type="submit"
                        x-text="pool ? 'Abrir mesa y arrancar reloj' : 'Abrir mesa'"></button>
            </div>

        </form>
    </div>
</div>
@endsection
