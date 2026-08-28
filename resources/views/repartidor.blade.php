@extends('layouts.app')

@section('titulo', 'Mis envíos')

@section('topbar')
    <div class="grow">
        <h1>Mis envíos</h1>
        <div class="sub">{{ auth()->user()->name }} · turno en curso</div>
    </div>
    @if ($envios->isNotEmpty())
        <span class="chip chip-blue chip-lg">En viaje</span>
    @endif
@endsection

@section('contenido')
<div x-data="{ rindiendo: false, entregado: {{ $aRendir / 100 }}, pagando: null }">
    <div class="narrow">

        <div class="strip3">
            <div><div class="k">Pendientes</div><div class="v">{{ $envios->count() }}</div></div>
            <div class="sep"></div>
            <div><div class="k">Entregados</div><div class="v t-green">{{ $entregados }}</div></div>
            <div class="sep"></div>
            <div><div class="k">A rendir</div><div class="v">@plata($aRendir)</div></div>
        </div>

        @forelse ($envios as $orden)
            @php $e = $orden->delivery; @endphp

            <div class="card mb12">
                <div class="between">
                    <span class="fw7 fs20">#{{ $orden->number }}</span>
                    <span class="money m-md">@plata($orden->total)</span>
                </div>

                <div class="mt12">
                    <div class="fs17 fw5">{{ $e->address?->street ?? 'Sin dirección' }}</div>
                    <div class="fs13 t-mute mt4">
                        @if ($e->address?->detail) {{ $e->address->detail }} · @endif
                        {{ $e->zone?->name }} · {{ $e->customer?->name }}
                    </div>
                    @if ($orden->notes)
                        <div class="fs13 t-amber mt4">{{ $orden->notes }}</div>
                    @endif
                </div>

                <div class="flex g8 mt12 wrap">
                    @if ($e->payment_method === 'cash')
                        <span class="chip chip-amber">Cobrar @plata($orden->total)</span>
                        @if ($e->vuelto() > 0)
                            <span class="chip chip-line">Vuelto @plata($e->vuelto())</span>
                        @endif
                    @elseif ($e->payment_method)
                        <span class="chip chip-green">Ya pagó</span>
                        <span class="chip chip-line">No cobrar nada</span>
                    @else
                        <span class="chip chip-line">Pago a definir</span>
                    @endif
                    <button class="btn btn-sm" type="button"
                            @click="pagando = {{ $orden->id }}">
                        {{ $e->payment_method ? 'Cambiar' : 'Definir' }}
                    </button>
                </div>

                <div class="flex g10 mt16">
                    @if ($e->customer?->phone)
                        <a class="btn grow" href="tel:{{ $e->customer->phone }}">
                            <x-icono nombre="phone" />Llamar
                        </a>
                    @endif
                    <form method="POST" action="{{ route('envios.entregar', $orden) }}" class="grow">
                        @csrf
                        <button class="btn btn-primary btn-block" type="submit">Entregado</button>
                    </form>
                </div>
            </div>

            {{-- ============ cambiar método de pago ============ --}}
            <div class="overlay" x-show="pagando === {{ $orden->id }}" x-cloak
                 @click.self="pagando = null" @keydown.escape.window="pagando = null"
                 x-data="{ metodo: {{ $e->payment_method ? "'{$e->payment_method}'" : 'null' }}, pagaCon: null }">
                <form class="modal" style="max-width:420px" method="POST"
                      action="{{ route('envios.metodo_pago', $orden) }}">
                    @csrf

                    <div class="modal-hd">
                        <div class="grow">
                            <h2>Pedido #{{ $orden->number }}</h2>
                            <div class="sub">¿Cómo paga?</div>
                        </div>
                        <button class="xbtn" type="button" @click="pagando = null">&times;</button>
                    </div>

                    <div class="modal-bd">
                        <div class="pays">
                            <button type="button" class="pay" :class="{ 'is-on': metodo === 'cash' }"
                                    @click="metodo = 'cash'"><x-icono nombre="cash" />Efectivo</button>
                            <button type="button" class="pay" :class="{ 'is-on': metodo === 'qr' }"
                                    @click="metodo = 'qr'; pagaCon = null"><x-icono nombre="qr" />QR / Transf.</button>
                            <button type="button" class="pay" :class="{ 'is-on': metodo === 'debit' }"
                                    @click="metodo = 'debit'; pagaCon = null"><x-icono nombre="card" />Tarjeta</button>
                        </div>
                        <input type="hidden" name="metodo_pago" :value="metodo ?? ''">

                        <template x-if="metodo === 'cash'">
                            <div class="field mt16">
                                <label for="pagacon-{{ $orden->id }}">Paga con</label>
                                <input id="pagacon-{{ $orden->id }}" class="inp" type="number" step="1" min="0"
                                       name="paga_con" x-model.number="pagaCon" inputmode="numeric"
                                       placeholder="50000">
                            </div>
                        </template>
                    </div>

                    <div class="modal-ft">
                        <div class="grow"></div>
                        <button class="btn" type="button" @click="pagando = null">Cancelar</button>
                        <button class="btn btn-primary" type="submit" :disabled="! metodo">Guardar</button>
                    </div>
                </form>
            </div>
        @empty
            <div class="card">
                <div class="col" style="align-items:center;gap:10px;padding:20px 0">
                    <div class="money m-lg t-green">Sin envíos pendientes</div>
                    <div class="t-dim fs14">Cuando te asignen uno, aparece acá.</div>
                </div>
            </div>
        @endforelse

        @if ($rendiciones->isNotEmpty())
            <div class="card mt16">
                <div class="sec">Rendiciones del turno</div>
                @foreach ($rendiciones as $r)
                    <div class="row">
                        <div class="grow">
                            <div class="nm">{{ $r->settled_at->format('H:i') }} · {{ $r->deliveries_count }} envíos</div>
                            <div class="sb">
                                Esperado @plata($r->cash_expected)
                                @if ($r->difference !== 0)
                                    · <span class="{{ $r->difference < 0 ? 't-red' : 't-amber' }}">
                                        diferencia @plata($r->difference)
                                    </span>
                                @endif
                            </div>
                        </div>
                        <span class="pr">@plata($r->cash_received)</span>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="notice mt16">
            <span class="dot dot-mute"></span>
            <div>
                <div class="tt">La rendición cierra el circuito</div>
                <div class="ds">
                    Hasta que rendís, la plata que cobraste figura fuera de la caja.
                    El cajero no puede cerrar el turno sin tu rendición.
                </div>
            </div>
        </div>
    </div>

    @if ($aRendir > 0)
        <div class="dock">
            <div class="dock-inner narrow" style="width:100%">
                <div class="grow">
                    <div class="fs13 t-mute">Efectivo a rendir</div>
                    <div class="money m-lg mt4">@plata($aRendir)</div>
                </div>
                <button class="btn btn-primary btn-lg" @click="rindiendo = true">Rendir caja</button>
            </div>
        </div>
    @endif

    {{-- ============ rendición ============ --}}
    <div class="overlay" x-show="rindiendo" x-cloak @click.self="rindiendo = false" @keydown.escape.window="rindiendo = false">
        <form class="modal" style="max-width:460px" method="POST" action="{{ route('envios.rendir') }}">
            @csrf

            <div class="modal-hd">
                <div class="grow">
                    <h2>Rendir caja</h2>
                    <div class="sub">Entregale la plata al cajero</div>
                </div>
                <button class="xbtn" type="button" @click="rindiendo = false">&times;</button>
            </div>

            <div class="modal-bd">
                <div class="lv"><span class="k">Cobraste en efectivo</span><span class="v money m-sm">@plata($aRendir)</span></div>

                <div class="field mt16">
                    <label for="entregado">Cuánto entregás</label>
                    <input id="entregado" class="inp inp-lg" type="number" step="1" min="0"
                           name="entregado" x-model.number="entregado" required inputmode="numeric">
                </div>

                <div class="callout mt16" x-show="entregado * 100 !== {{ $aRendir }}">
                    <div class="fw6 t-amber">
                        Diferencia <span x-text="'$' + (entregado - {{ $aRendir / 100 }}).toLocaleString('es-AR')"></span>
                    </div>
                    <div class="fs13 t-dim mt8">
                        Queda registrada con tu usuario. Avisale al cajero por qué.
                    </div>
                </div>
            </div>

            <div class="modal-ft">
                <div class="grow"></div>
                <button class="btn" type="button" @click="rindiendo = false">Cancelar</button>
                <button class="btn btn-primary" type="submit">Confirmar rendición</button>
            </div>
        </form>
    </div>
</div>
@endsection
