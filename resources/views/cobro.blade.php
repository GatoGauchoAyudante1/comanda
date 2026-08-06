@extends('layouts.app')

@php
    $sesion = $orden->tableSession;
    $mesa   = $sesion?->table;
    $saldo  = $orden->saldo();

    $medios = [
        'cash'     => ['Efectivo', 'cash'],
        'qr'       => ['QR Mercado Pago', 'qr'],
        'transfer' => ['Transferencia', 'arrow'],
        'debit'    => ['Débito', 'card'],
        'credit'   => ['Crédito', 'card'],
    ];
@endphp

@section('titulo', 'Cobrar')

@section('topbar')
    <a class="back" href="{{ $sesion ? route('mesa', $sesion) : route('panel') }}"><x-icono nombre="back" /></a>
    <div>
        <h1>Cobrar {{ $mesa?->name ?? "pedido #{$orden->number}" }}</h1>
        <div class="sub">
            @if ($sesion)
                Ocupó de {{ $sesion->started_at->format('H:i') }}
                a {{ $sesion->ended_at?->format('H:i') ?? 'ahora' }}
                @if ($orden->time_amount > 0)
                    · se cobran {{ intdiv($sesion->minutosCobrados(), 60) }}:{{ str_pad($sesion->minutosCobrados() % 60, 2, '0', STR_PAD_LEFT) }} hs
                @endif
            @endif
        </div>
    </div>
    <div class="topbar-actions">
        <form method="POST" action="{{ route('cobro.cancelar', $orden) }}">
            @csrf
            <button class="btn btn-quiet" type="submit">Cancelar cobro</button>
        </form>
    </div>
@endsection

@section('contenido')
<div x-data="{ metodo: 'cash', importe: {{ max(0, $saldo) / 100 }}, recibido: '' }">

    <div class="cols cols-wide">

        {{-- ============ detalle y medios ============ --}}
        <div>
            <div class="card">
                <div class="sec">
                    Detalle de la cuenta
                    <span class="meta">{{ $orden->items->sum('qty') }} consumos</span>
                </div>

                @if ($orden->time_amount > 0)
                    <div class="lv">
                        <span class="k">
                            Tiempo de mesa · {{ $sesion->tiempoLegible() }},
                            se cobra {{ intdiv($sesion->minutosCobrados(), 60) }}:{{ str_pad($sesion->minutosCobrados() % 60, 2, '0', STR_PAD_LEFT) }}
                        </span>
                        <span class="v">@plata($orden->time_amount)</span>
                    </div>
                @endif

                <div class="lv"><span class="k">Consumos</span><span class="v">@plata($orden->items_total)</span></div>
                <div class="lv"><span class="k">Descuento</span><span class="v t-mute">@plata($orden->discount)</span></div>

                <div class="hr-strong"></div>
                <div class="between">
                    <span class="t-dim">Total a cobrar</span>
                    <span class="money m-xxl">@plata($orden->total)</span>
                </div>
            </div>

            @if ($saldo > 0)
                <form method="POST" action="{{ route('cobro.pago', $orden) }}">
                    @csrf

                    <div class="sec mt26">
                        Con qué paga
                        <span class="meta">Se puede combinar más de un medio</span>
                    </div>

                    <div class="pays">
                        @foreach ($medios as $clave => [$etiqueta, $icono])
                            <button type="button" class="pay" :class="{ 'is-on': metodo === '{{ $clave }}' }"
                                    @click="metodo = '{{ $clave }}'">
                                <x-icono :nombre="$icono" />{{ $etiqueta }}
                            </button>
                        @endforeach
                        <button type="button" class="pay" :class="{ 'is-on': metodo === 'other' }"
                                @click="metodo = 'other'">
                            <x-icono nombre="plus" />Otro
                        </button>
                    </div>
                    <input type="hidden" name="method" :value="metodo">

                    <div class="card mt16">
                        <div class="grid3">
                            <div class="field">
                                <label for="amount">Importe a imputar</label>
                                <input id="amount" class="inp inp-lg" type="number" step="0.01" min="0.01"
                                       max="{{ $saldo / 100 }}" name="amount" x-model="importe" required
                                       inputmode="decimal">
                            </div>

                            <div class="field" x-show="metodo === 'cash'">
                                <label for="received">Con cuánto paga</label>
                                <input id="received" class="inp inp-lg" type="number" step="0.01" min="0"
                                       name="received" x-model="recibido" inputmode="decimal"
                                       placeholder="{{ number_format($saldo / 100, 0, ',', '') }}">
                            </div>

                            <div class="field" x-show="metodo !== 'cash'">
                                <label for="reference">N° de operación</label>
                                <input id="reference" class="inp inp-lg" name="reference" maxlength="60"
                                       placeholder="Opcional">
                            </div>

                            <div class="col" style="justify-content:flex-end">
                                <button class="btn btn-primary btn-lg btn-block" type="submit">Cargar pago</button>
                            </div>
                        </div>

                        <div class="notice notice-green mt16"
                             x-show="metodo === 'cash' && Number(recibido) > Number(importe)">
                            <span class="dot"></span>
                            <div>
                                <div class="tt">Vuelto
                                    <span x-text="'$' + (Number(recibido) - Number(importe)).toLocaleString('es-AR')"></span>
                                </div>
                                <div class="ds">El vuelto no genera movimiento de caja, sólo se muestra.</div>
                            </div>
                        </div>
                    </div>
                </form>
            @endif
        </div>

        {{-- ============ pagos y saldo ============ --}}
        <div>
            <div class="pane">
                <div class="pane-hd">
                    <h3>Pagos cargados</h3>
                    <span class="badge">{{ $orden->payments->count() }}</span>
                </div>

                @forelse ($orden->payments as $pago)
                    <div class="card card-tight mb12">
                        <div class="between">
                            <div>
                                <div class="fw6">
                                    {{ ['cash' => 'Efectivo', 'qr' => 'QR Mercado Pago', 'transfer' => 'Transferencia',
                                        'debit' => 'Débito', 'credit' => 'Crédito', 'other' => 'Otro'][$pago->method] }}
                                </div>
                                <div class="fs13 t-mute mt4">
                                    @if ($pago->method === 'cash' && $pago->received)
                                        Recibí @plata($pago->received) · vuelto @plata($pago->vuelto())
                                    @elseif ($pago->reference)
                                        op. {{ $pago->reference }}
                                    @else
                                        {{ $pago->created_at->format('H:i') }} · {{ $pago->user->name }}
                                    @endif
                                </div>
                            </div>
                            <div class="flex g10">
                                <span class="money m-sm">@plata($pago->amount)</span>
                                <form method="POST" action="{{ route('cobro.pago.quitar', [$orden, $pago]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="xbtn" type="submit">&times;</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="t-mute fs14">Todavía no se cargó ningún pago.</p>
                @endforelse
            </div>

            @if ($saldo > 0)
                <div class="callout mt16">
                    <div class="between">
                        <span class="fw6 t-amber">Saldo pendiente</span>
                        <span class="money m-xl t-amber">@plata($saldo)</span>
                    </div>
                </div>
            @else
                <div class="callout callout-green mt16">
                    <div class="between">
                        <span class="fw6 t-green">Cuenta saldada</span>
                        <span class="money m-xl t-green">@plata(0)</span>
                    </div>
                </div>

                <form method="POST" action="{{ route('cobro.confirmar', $orden) }}">
                    @csrf
                    <button class="btn btn-primary btn-lg btn-block mt16" type="submit">
                        Confirmar cobro y liberar la mesa
                    </button>
                </form>

                <div class="fs13 t-mute mt12 ta-r">
                    Al confirmar se descuenta el stock de los insumos.
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
