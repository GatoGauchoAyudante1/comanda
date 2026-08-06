@extends('layouts.app')

@php
    $mesa    = $sesion->table;
    $orden   = $sesion->order;
    $esPool  = $mesa->esPool();
    $minutos = $sesion->minutosJugados();
    $alerta  = $esPool && $minutos >= 120;
@endphp

@section('titulo', $mesa->name)

@section('topbar')
    <a class="back" href="{{ route('panel') }}"><x-icono nombre="back" /></a>
    <div>
        <h1>{{ $mesa->name }}</h1>
        <div class="sub">
            @if ($sesion->reference) {{ $sesion->reference }} · @endif
            Abierta {{ $sesion->started_at->format('H:i') }} · atiende {{ $sesion->user->name }}
        </div>
    </div>

    @if ($esPool)
        <span class="chip chip-lg {{ $sesion->pausada() ? 'chip-line' : ($alerta ? 'chip-red' : 'chip-amber') }} hide-mobile">
            {{ $sesion->pausada() ? 'en pausa' : $sesion->tiempoLegible() . ' hs' }}
        </span>
    @endif

    <div class="topbar-actions">
        @if ($esPool)
            <form method="POST" action="{{ route('mesa.pausar', $sesion) }}">
                @csrf
                <button class="btn" type="submit">{{ $sesion->pausada() ? 'Reanudar' : 'Pausar mesa' }}</button>
            </form>
        @endif
        {{-- Precuenta: el cliente pide la cuenta bastante antes de pagar. --}}
        <a class="btn" href="{{ route('ticket', $orden) }}" target="_blank">Precuenta</a>
    </div>
@endsection

@section('contenido')
<div x-data="{ anular: false, consumio: {{ $orden->items->isNotEmpty() ? 'true' : 'false' }} }">

    {{-- ============ el reloj, en mobile va arriba ============ --}}
    @if ($esPool)
        <div class="card only-mobile mb16">
            <div class="between">
                <div>
                    <div class="fs13 t-dim">{{ $sesion->pausada() ? 'Tiempo pausado' : 'Tiempo corriendo' }}</div>
                    <div class="money m-xl {{ $sesion->pausada() ? 't-dim' : 't-amber' }} mt4"
                         data-reloj
                         data-inicio="{{ $sesion->started_at->toIso8601String() }}"
                         data-pausa="{{ $sesion->paused_minutes }}"
                         data-corriendo="{{ $sesion->pausada() ? 0 : 1 }}">{{ $sesion->tiempoLegible() }}</div>
                </div>
                <div class="ta-r">
                    <div class="fs13 t-dim">Se cobra {{ intdiv($sesion->minutosCobrados(), 60) }}:{{ str_pad($sesion->minutosCobrados() % 60, 2, '0', STR_PAD_LEFT) }} hs</div>
                    <div class="money m-lg mt4">@plata($sesion->importeTiempo())</div>
                    <div class="fs13 t-mute mt4">@plata($sesion->rate_price_per_hour) / hora</div>
                </div>
            </div>
        </div>
    @endif

    <div class="cols">

        {{-- ============ consumos ============ --}}
        <div>
            <div class="card">
                <div class="sec">
                    Consumos de la mesa
                    <span class="meta">{{ $orden->items->sum('qty') }} unidades · {{ $orden->items->count() }} productos</span>
                </div>

                @forelse ($orden->items as $item)
                    <div class="row">
                        <span class="qty">{{ $item->qty }}</span>
                        <div class="grow">
                            <div class="nm">{{ $item->product->name }}</div>
                            <div class="sb">
                                @plata($item->unit_price) c/u · cargado {{ $item->created_at->format('H:i') }}
                                @if ($item->notes) · <span class="t-amber">{{ $item->notes }}</span> @endif
                            </div>
                        </div>
                        <span class="pr">@plata($item->subtotal())</span>
                    </div>
                @empty
                    <p class="t-mute fs14" style="padding:12px 0">
                        Todavía no se cargó nada. Tocá <b class="t-white">Agregar consumo</b> para empezar.
                    </p>
                @endforelse

                <a class="btn btn-primary btn-lg btn-block mt16" href="{{ route('consumo', $sesion) }}">
                    + Agregar consumo
                </a>
            </div>

            {{-- Quién hizo qué con esta mesa (docs/11-auditoria.md) --}}
            <div class="card mt16">
                <div class="sec">Historial de la mesa</div>
                @include('partials.bitacora', ['eventos' => \App\Support\Bitacora::de($orden)])
            </div>

            <div class="notice mt16">
                @if ($esPool)
                    <span class="dot" style="background:var(--blue)"></span>
                    <div class="grow">
                        <div class="tt">El reloj corre en el servidor</div>
                        <div class="ds">
                            Arrancó {{ $sesion->started_at->format('H:i') }}.
                            @if ($sesion->start_adjusted_by)
                                El inicio fue ajustado al abrir.
                            @endif
                            @if ($sesion->paused_minutes > 0)
                                Se descontaron {{ $sesion->paused_minutes }} min de pausa.
                            @endif
                        </div>
                    </div>
                @else
                    <span class="dot dot-mute"></span>
                    <div class="grow">
                        <div class="tt">Mesa de salón</div>
                        <div class="ds">Abierta {{ $sesion->started_at->format('H:i') }} · {{ $sesion->guests }} personas</div>
                    </div>
                @endif

                @if (auth()->user()->puedeCobrar())
                    <button class="btn btn-danger btn-sm" @click="anular = true">Anular mesa</button>
                @endif
            </div>
        </div>

        {{-- ============ tiempo y total ============ --}}
        <div>
            @if ($esPool)
                <div class="pane hide-mobile">
                    <div class="pane-hd">
                        <h3>Tiempo de mesa</h3>
                        <span class="chip {{ $sesion->pausada() ? 'chip-line' : 'chip-amber' }}">
                            {{ $sesion->pausada() ? 'en pausa' : 'corriendo' }}
                        </span>
                    </div>

                    <div class="money m-xxl {{ $sesion->pausada() ? 't-dim' : 't-amber' }}"
                         data-reloj
                         data-inicio="{{ $sesion->started_at->toIso8601String() }}"
                         data-pausa="{{ $sesion->paused_minutes }}"
                         data-corriendo="{{ $sesion->pausada() ? 0 : 1 }}">{{ $sesion->tiempoLegible() }}</div>
                    <div class="fs13 t-mute mt8">
                        Desde las {{ $sesion->started_at->format('H:i') }} · el reloj corre en el servidor
                    </div>

                    <div class="hr"></div>
                    <div class="lv"><span class="k">Tarifa</span><span class="v">{{ $sesion->rate_name }}</span></div>
                    <div class="lv"><span class="k">Precio</span><span class="v">@plata($sesion->rate_price_per_hour) / hora</span></div>
                    <div class="lv">
                        <span class="k">Se cobra</span>
                        <span class="v">{{ intdiv($sesion->minutosCobrados(), 60) }}:{{ str_pad($sesion->minutosCobrados() % 60, 2, '0', STR_PAD_LEFT) }} hs</span>
                    </div>
                    <div class="fs13 t-mute mt8">
                        Fracción de {{ $sesion->rate_rounding_minutes }} minutos, redondeando hacia arriba.
                        El importe final se calcula al cerrar la mesa.
                    </div>
                </div>
            @endif

            <div class="pane">
                <div class="pane-hd"><h3>Total de la mesa</h3></div>
                @if ($esPool)
                    <div class="lv">
                        <span class="k">Tiempo ({{ intdiv($sesion->minutosCobrados(), 60) }}:{{ str_pad($sesion->minutosCobrados() % 60, 2, '0', STR_PAD_LEFT) }} hs)</span>
                        <span class="v">@plata($sesion->importeTiempo())</span>
                    </div>
                @endif
                <div class="lv">
                    <span class="k">Consumos ({{ $orden->items->sum('qty') }})</span>
                    <span class="v">@plata($orden->items->sum(fn ($i) => $i->subtotal()))</span>
                </div>
                <div class="hr-strong"></div>
                <div class="between">
                    <span class="t-dim">A cobrar</span>
                    <span class="money m-xl">@plata($orden->items->sum(fn ($i) => $i->subtotal()) + $sesion->importeTiempo())</span>
                </div>
            </div>

            @if (auth()->user()->puedeCobrar())
                <form method="POST" action="{{ route('cobro.iniciar', $orden) }}">
                    @csrf
                    <button class="btn btn-primary btn-lg btn-block mt16" type="submit">
                        Cerrar mesa y cobrar
                    </button>
                </form>
                @if ($esPool)
                    <div class="fs13 t-mute mt8 ta-r">Al tocar esto se frena el reloj.</div>
                @endif
            @else
                <div class="notice mt16">
                    <span class="dot dot-mute"></span>
                    <div class="ds">El cobro lo hace el cajero. Avisale cuando la mesa esté lista.</div>
                </div>
            @endif
        </div>

    </div>

    {{-- ============================================================
         DIÁLOGO: ANULAR MESA · R-06
         ============================================================ --}}
    <div class="overlay" x-show="anular" x-cloak @click.self="anular = false" @keydown.escape.window="anular = false">
        <form class="modal" style="max-width:520px" method="POST" action="{{ route('mesa.anular', $sesion) }}">
            @csrf

            <div class="modal-hd">
                <div class="grow">
                    <h2>Anular {{ $mesa->name }}</h2>
                    <div class="sub">La cuenta se cierra sin cobrar</div>
                </div>
                <button class="xbtn" type="button" @click="anular = false">&times;</button>
            </div>

            <div class="modal-bd">

                <div class="callout callout-red">
                    <div class="fw6 t-red mb8">Se pierden @plata($orden->items_total + $sesion->importeTiempo())</div>
                    <div class="fs13 t-dim">
                        Queda registrado con tu usuario y la hora. No se borra nada: la cuenta
                        se puede revisar después.
                    </div>
                </div>

                <div class="modal-sec">
                    <div class="opt-lbl">Motivo</div>
                    <textarea class="inp" name="motivo" required minlength="5" maxlength="200"
                              placeholder="Se equivocaron de mesa, el cliente se fue sin pagar, error de carga…"></textarea>
                </div>

                @if ($orden->items->isNotEmpty())
                    <div class="modal-sec">
                        <div class="opt-lbl">¿Se consumió lo cargado?</div>
                        <div class="opt-row opt-col">
                            <button type="button" class="opt" style="height:auto;padding:12px 16px;text-align:left"
                                    :class="{ 'is-on': consumio }" @click="consumio = true">
                                <span>
                                    <b>Sí, se sirvió y no se cobró</b><br>
                                    <span class="fs13" style="opacity:.75">Se descuenta del stock como merma</span>
                                </span>
                            </button>
                            <button type="button" class="opt" style="height:auto;padding:12px 16px;text-align:left"
                                    :class="{ 'is-on': ! consumio }" @click="consumio = false">
                                <span>
                                    <b>No, fue un error de carga</b><br>
                                    <span class="fs13" style="opacity:.75">El stock queda como está</span>
                                </span>
                            </button>
                        </div>
                        <input type="hidden" name="se_consumio" :value="consumio ? 1 : 0">
                    </div>
                @else
                    <input type="hidden" name="se_consumio" value="0">
                @endif
            </div>

            <div class="modal-ft">
                <div class="grow"></div>
                <button class="btn" type="button" @click="anular = false">Volver</button>
                <button class="btn btn-danger" type="submit">Anular la cuenta</button>
            </div>
        </form>
    </div>
</div>
@endsection
