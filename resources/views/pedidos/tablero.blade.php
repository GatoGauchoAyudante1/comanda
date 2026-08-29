@extends('layouts.app')

@php
    $activos = collect($columnas)->sum(fn ($c) => $c['pedidos']->count());
@endphp

@section('titulo', 'Pedidos')
@section('alpine', '{ asignando: null, detalle: null, pagando: null, entregando: null }')

@section('topbar')
    <div>
        <h1>Pedidos</h1>
        <div class="sub">{{ $activos }} {{ $activos === 1 ? 'en curso' : 'en curso' }} · turno actual</div>
    </div>
    <div class="topbar-actions">
        <a class="btn hide-mobile" href="{{ route('cocina') }}">Ver cocina</a>
        @if (\App\Support\Negocio::modulo('delivery'))
            <a class="btn btn-primary" href="{{ route('pedidos.nuevo') }}">+ Nuevo pedido</a>
        @endif
    </div>
@endsection

@section('contenido')
{{-- Se refresca solo cada 15 s, salvo que haya un diálogo abierto (D-18). --}}
<div x-init="setInterval(() => {
        if (!document.hidden && !asignando && !detalle && !pagando && !entregando) location.reload()
     }, 15000)">

    <div class="kanban" style="grid-template-columns:repeat(3,1fr)">
        @foreach ($columnas as $clave => $columna)
            <div>
                <div class="kcol-hd">
                    <span class="t">{{ $columna['titulo'] }}</span>
                    <span class="badge">{{ $columna['pedidos']->count() }}</span>
                </div>

                <div class="kcol">
                    @forelse ($columna['pedidos'] as $pedido)
                        @php
                            $minutos  = (int) $pedido->created_at->diffInMinutes(now());
                            $urgencia = $minutos >= 30 ? 'late' : ($minutos >= 15 ? 'warn' : 'ok');
                            $entrega  = $pedido->delivery;
                            $esMesa   = $pedido->esMesa() || $pedido->type === 'mostrador';
                            $proximo  = $esMesa ? null : $avanzar->siguiente($pedido);
                        @endphp

                        <div class="kcard {{ $urgencia }}">

                            <div class="between">
                                <span class="fw6 fs17">#{{ $pedido->number }}</span>
                                <span class="fw6">@plata($pedido->total)</span>
                            </div>

                            {{-- De dónde viene y a dónde va --}}
                            <x-origen :orden="$pedido" class="mt8" />

                            @if ($entrega?->customer?->name)
                                <div class="fs13 t-dim mt4">{{ $entrega->customer->name }}</div>
                            @endif

                            <div class="flex g8 mt12 wrap">
                                <span class="chip chip-{{ ['ok' => 'green', 'warn' => 'amber', 'late' => 'red'][$urgencia] }}">
                                    {{ $minutos }} min
                                </span>

                                <span class="chip chip-line">
                                    {{ $pedido->items->sum('qty') }} {{ $pedido->items->sum('qty') === 1 ? 'ítem' : 'ítems' }}
                                </span>

                                @if (! $esMesa)
                                    <span class="chip chip-line">
                                        {{ match ($entrega?->payment_method) {
                                            'cash'  => 'Cobrar en efectivo',
                                            null    => 'Pago a definir',
                                            default => 'Ya pagó',
                                        } }}
                                    </span>
                                @endif

                                @if ($entrega?->driver)
                                    <span class="chip chip-blue">{{ $entrega->driver->name }}</span>
                                @endif
                            </div>

                            <div class="flex g8 mt12">
                                <button class="btn btn-sm grow" @click="detalle = {{ $pedido->id }}">
                                    Ver comanda
                                </button>

                                @if ($proximo)
                                    @if ($proximo === 'on_route' && ! $entrega?->driver_id)
                                        <button class="btn btn-sm btn-primary grow" @click="asignando = {{ $pedido->id }}">
                                            Asignar
                                        </button>
                                    {{-- Entregar registra el cobro: si nadie definió cómo paga,
                                         se pregunta antes de cerrar (si no, la caja no cuadra). --}}
                                    @elseif ($proximo === 'delivered' && ! $esMesa && ! $entrega?->payment_method)
                                        <button class="btn btn-sm btn-primary grow" @click="entregando = {{ $pedido->id }}">
                                            Entregado
                                        </button>
                                    @else
                                        <form method="POST" action="{{ route('pedidos.avanzar', $pedido) }}" class="grow">
                                            @csrf
                                            <input type="hidden" name="estado" value="{{ $proximo }}">
                                            <button class="btn btn-sm btn-primary btn-block" type="submit">
                                                {{ ['ready' => 'Listo', 'on_route' => 'A la calle', 'delivered' => 'Entregado'][$proximo] ?? 'Avanzar' }}
                                            </button>
                                        </form>
                                    @endif
                                @elseif ($esMesa && $clave === 'ready')
                                    <form method="POST" action="{{ route('pedidos.servido', $pedido) }}" class="grow">
                                        @csrf
                                        <button class="btn btn-sm btn-primary btn-block" type="submit">Servido</button>
                                    </form>
                                @elseif ($esMesa && $pedido->tableSession)
                                    <a class="btn btn-sm grow" href="{{ route('mesa', $pedido->tableSession) }}">Ver mesa</a>
                                @endif

                                @if ($proximo && $anterior = $avanzar->anterior($pedido))
                                    <form method="POST" action="{{ route('pedidos.avanzar', $pedido) }}">
                                        @csrf
                                        <input type="hidden" name="estado" value="{{ $anterior }}">
                                        <button class="btn btn-sm" type="submit" title="Volver un paso">&larr;</button>
                                    </form>
                                @endif
                            </div>

                            {{-- ============ comanda ============ --}}
                            <div class="overlay" x-show="detalle === {{ $pedido->id }}" x-cloak
                                 @click.self="detalle = null" @keydown.escape.window="detalle = null">
                                <div class="modal" style="max-width:520px">
                                    <div class="modal-hd">
                                        <div class="grow">
                                            <h2>Comanda #{{ $pedido->number }}</h2>
                                            <div class="sub">
                                                Tomada {{ $pedido->created_at->format('H:i') }}
                                                · hace {{ $minutos }} min
                                            </div>
                                        </div>
                                        <button class="xbtn" type="button" @click="detalle = null">&times;</button>
                                    </div>

                                    <div class="modal-bd">
                                        <x-origen :orden="$pedido" class="mb16" />

                                        @if ($entrega?->address)
                                            <div class="card card-tight mb16">
                                                <div class="fw6">{{ $entrega->address->completa() }}</div>
                                                <div class="fs13 t-mute mt4">
                                                    {{ $entrega->customer?->name }}
                                                    @if ($entrega->customer?->phone) · {{ $entrega->customer->phone }} @endif
                                                </div>
                                            </div>
                                        @endif

                                        <div class="sec">Qué lleva</div>
                                        @foreach ($pedido->items as $item)
                                            <div class="row">
                                                <span class="qty">{{ $item->qty }}</span>
                                                <div class="grow">
                                                    <div class="nm">
                                                        {{ $item->product->name }}
                                                        @if ($item->variant) {{ $item->variant->name }} @endif
                                                    </div>
                                                    @if ($item->notes)
                                                        <div class="sb t-amber">{{ $item->notes }}</div>
                                                    @endif
                                                    <div class="sb">
                                                        @switch($item->status)
                                                            @case('kitchen') <span class="t-amber">en cocina</span> @break
                                                            @case('ready')   <span class="t-green">listo</span> @break
                                                            @case('delivered') entregado @break
                                                            @default pendiente
                                                        @endswitch
                                                        · @plata($item->unit_price) c/u
                                                    </div>
                                                </div>
                                                <span class="pr">@plata($item->subtotal())</span>
                                            </div>
                                        @endforeach

                                        @if ($pedido->notes)
                                            <div class="notice notice-amber mt16">
                                                <span class="dot dot-amber"></span>
                                                <div class="ds t-white">{{ $pedido->notes }}</div>
                                            </div>
                                        @endif

                                        <div class="hr"></div>
                                        <div class="lv"><span class="k">Productos</span><span class="v">@plata($pedido->items_total)</span></div>
                                        @if ($pedido->delivery_fee > 0)
                                            <div class="lv"><span class="k">Envío</span><span class="v">@plata($pedido->delivery_fee)</span></div>
                                        @endif
                                        @if ($pedido->time_amount > 0)
                                            <div class="lv"><span class="k">Tiempo de mesa</span><span class="v">@plata($pedido->time_amount)</span></div>
                                        @endif
                                        <div class="hr-strong"></div>
                                        <div class="between">
                                            <span class="t-dim">Total</span>
                                            <span class="money m-lg">@plata($pedido->total)</span>
                                        </div>

                                        @if (! $esMesa)
                                            @php
                                                $claseNotice = match ($entrega?->payment_method) {
                                                    'cash'  => 'notice-amber',
                                                    null    => '',
                                                    default => 'notice-green',
                                                };
                                            @endphp
                                            <div class="notice mt16 {{ $claseNotice }}">
                                                <span class="dot {{ $entrega?->payment_method === 'cash' ? 'dot-amber' : ($entrega?->payment_method ? '' : 'dot-mute') }}"></span>
                                                <div class="grow">
                                                    <div class="tt">
                                                        {{ match ($entrega?->payment_method) {
                                                            'cash'  => 'Cobrar ' . \App\Support\Plata::format($pedido->total) . ' en efectivo',
                                                            null    => 'Medio de pago a definir',
                                                            default => 'Ya está pagado',
                                                        } }}
                                                    </div>
                                                    @if ($entrega?->vuelto() > 0)
                                                        <div class="ds">Llevar @plata($entrega->vuelto()) de vuelto.</div>
                                                    @endif
                                                </div>
                                                <button class="btn btn-sm" type="button"
                                                        @click="detalle = null; pagando = {{ $pedido->id }}">
                                                    {{ $entrega?->payment_method ? 'Cambiar' : 'Definir' }}
                                                </button>
                                            </div>
                                        @endif

                                        {{-- Quién hizo qué con este pedido (docs/11-auditoria.md) --}}
                                        <div class="sec mt26">Historial</div>
                                        @include('partials.bitacora', [
                                            'eventos' => \App\Support\Bitacora::de($pedido),
                                        ])
                                    </div>

                                    <div class="modal-ft">
                                        <a class="btn grow" href="{{ route('ticket', $pedido) }}" target="_blank">Imprimir</a>
                                        <button class="btn btn-primary" type="button" @click="detalle = null">Cerrar</button>
                                    </div>
                                </div>
                            </div>

                            {{-- ============ asignar repartidor ============ --}}
                            @if (! $esMesa)
                                <div class="overlay" x-show="asignando === {{ $pedido->id }}" x-cloak
                                     @click.self="asignando = null" @keydown.escape.window="asignando = null">
                                    <form class="modal" style="max-width:420px" method="POST"
                                          action="{{ route('pedidos.avanzar', $pedido) }}">
                                        @csrf
                                        <input type="hidden" name="estado" value="on_route">

                                        <div class="modal-hd">
                                            <div class="grow">
                                                <h2>Pedido #{{ $pedido->number }}</h2>
                                                <div class="sub">¿Quién lo lleva?</div>
                                            </div>
                                            <button class="xbtn" type="button" @click="asignando = null">&times;</button>
                                        </div>

                                        <div class="modal-bd">
                                            <div class="opt-row opt-col">
                                                @forelse ($repartidores as $r)
                                                    <button type="submit" name="driver_id" value="{{ $r->id }}"
                                                            class="opt" style="height:56px">
                                                        <span>{{ $r->name }}</span>
                                                    </button>
                                                @empty
                                                    <p class="t-mute fs14">
                                                        No hay repartidores cargados. Agregalos en Ajustes → Usuarios.
                                                    </p>
                                                @endforelse
                                            </div>

                                            @if ($entrega?->payment_method === 'cash')
                                                <div class="notice notice-amber mt16">
                                                    <span class="dot dot-amber"></span>
                                                    <div>
                                                        <div class="tt">Cobra @plata($pedido->total) en efectivo</div>
                                                        @if ($entrega->vuelto() > 0)
                                                            <div class="ds">Llevá @plata($entrega->vuelto()) de vuelto.</div>
                                                        @endif
                                                    </div>
                                                </div>
                                            @elseif (! $entrega?->payment_method)
                                                <div class="notice mt16">
                                                    <span class="dot dot-mute"></span>
                                                    <div>
                                                        <div class="tt">Medio de pago a definir</div>
                                                        <div class="ds">El repartidor lo confirma con el cliente antes de entregar.</div>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </form>
                                </div>

                                {{-- ============ entregar definiendo el cobro ============ --}}
                                <div class="overlay" x-show="entregando === {{ $pedido->id }}" x-cloak
                                     @click.self="entregando = null" @keydown.escape.window="entregando = null"
                                     x-data="{ metodo: null, pagaCon: null }">
                                    <form class="modal" style="max-width:420px" method="POST"
                                          action="{{ route('pedidos.avanzar', $pedido) }}">
                                        @csrf
                                        <input type="hidden" name="estado" value="delivered">

                                        <div class="modal-hd">
                                            <div class="grow">
                                                <h2>Entregar #{{ $pedido->number }}</h2>
                                                <div class="sub">¿Cómo abonó el cliente?</div>
                                            </div>
                                            <button class="xbtn" type="button" @click="entregando = null">&times;</button>
                                        </div>

                                        <div class="modal-bd">
                                            <div class="notice mb16">
                                                <span class="dot dot-mute"></span>
                                                <div>
                                                    <div class="tt">Se cobran @plata($pedido->total)</div>
                                                    <div class="ds">Queda registrado en la caja con el medio que elijas.</div>
                                                </div>
                                            </div>

                                            <x-medios-pago :id="'entrega-' . $pedido->id" />
                                        </div>

                                        <div class="modal-ft">
                                            <div class="grow"></div>
                                            <button class="btn" type="button" @click="entregando = null">Cancelar</button>
                                            <button class="btn btn-primary" type="submit" :disabled="! metodo">
                                                Entregado
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                {{-- ============ cambiar método de pago ============ --}}
                                <div class="overlay" x-show="pagando === {{ $pedido->id }}" x-cloak
                                     @click.self="pagando = null" @keydown.escape.window="pagando = null"
                                     x-data="{ metodo: {{ $entrega?->payment_method ? "'{$entrega->payment_method}'" : 'null' }}, pagaCon: null }">
                                    <form class="modal" style="max-width:420px" method="POST"
                                          action="{{ route('pedidos.metodo_pago', $pedido) }}">
                                        @csrf

                                        <div class="modal-hd">
                                            <div class="grow">
                                                <h2>Pedido #{{ $pedido->number }}</h2>
                                                <div class="sub">¿Cómo paga?</div>
                                            </div>
                                            <button class="xbtn" type="button" @click="pagando = null">&times;</button>
                                        </div>

                                        <div class="modal-bd">
                                            <x-medios-pago :id="$pedido->id" />
                                        </div>

                                        <div class="modal-ft">
                                            <div class="grow"></div>
                                            <button class="btn" type="button" @click="pagando = null">Cancelar</button>
                                            <button class="btn btn-primary" type="submit" :disabled="! metodo">Guardar</button>
                                        </div>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="tcard tcard--free" style="min-height:90px">
                            <span class="fs13 t-mute">Sin pedidos</span>
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>

    <div class="dock">
        <div class="dock-inner">
            <div class="grow flex g18 wrap">
                <div>
                    <div class="fs13 t-mute">Delivery y retiro cobrados hoy</div>
                    <div class="money m-md mt4">@plata($delDia->sum('total'))</div>
                </div>
                <div class="hide-mobile" style="width:1px;height:38px;background:var(--line)"></div>
                <div>
                    <div class="fs13 t-mute">Pedidos entregados</div>
                    <div class="money m-md mt4">{{ $delDia->count() }}</div>
                </div>
                <div class="hide-mobile" style="width:1px;height:38px;background:var(--line)"></div>
                <div>
                    <div class="fs13 t-mute">Ticket promedio</div>
                    <div class="money m-md mt4">
                        @plata($delDia->count() ? (int) round($delDia->sum('total') / $delDia->count()) : 0)
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
