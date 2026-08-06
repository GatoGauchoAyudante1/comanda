@extends('layouts.app')

@php
    $activos = collect($columnas)->sum(fn ($c) => $c['pedidos']->count());
@endphp

@section('titulo', 'Pedidos')

@section('topbar')
    <div>
        <h1>Pedidos</h1>
        <div class="sub">{{ $activos }} {{ $activos === 1 ? 'activo' : 'activos' }} · turno en curso</div>
    </div>
    <div class="topbar-actions">
        <a class="btn hide-mobile" href="{{ route('cocina') }}">Ver cocina</a>
        <a class="btn btn-primary" href="{{ route('pedidos.nuevo') }}">+ Nuevo pedido</a>
    </div>
@endsection

@section('contenido')
{{-- Se refresca solo cada 15 s, sólo si la pestaña está a la vista (D-18). --}}
<div x-data="{ asignando: null }"
     x-init="setInterval(() => { if (!document.hidden && !asignando) location.reload() }, 15000)">

    <div class="kanban" style="grid-template-columns:repeat(3,1fr)">
        @foreach ($columnas as $clave => $columna)
            <div>
                <div class="kcol-hd">
                    <span class="t">{{ $columna['titulo'] }}</span>
                    <span class="badge {{ $columna['pedidos']->isEmpty() ? '' : '' }}">{{ $columna['pedidos']->count() }}</span>
                </div>

                <div class="kcol">
                    @forelse ($columna['pedidos'] as $pedido)
                        @php
                            $minutos  = (int) $pedido->created_at->diffInMinutes(now());
                            $urgencia = $minutos >= 30 ? 'late' : ($minutos >= 15 ? 'warn' : 'ok');
                            $entrega  = $pedido->delivery;
                            $proximo  = $avanzar->siguiente($pedido);
                        @endphp

                        <div class="kcard {{ $urgencia }}">
                            <div class="between">
                                <span class="fw6 fs17">#{{ $pedido->number }}</span>
                                <span class="fw6">@plata($pedido->total)</span>
                            </div>

                            <div class="fs13 t-dim mt4">
                                {{ $entrega?->customer?->name ?: 'Sin nombre' }}
                                @if ($pedido->type === 'retiro')
                                    · <span class="t-white">retira</span>
                                @elseif ($entrega?->zone)
                                    · {{ $entrega->zone->name }}
                                @endif
                            </div>

                            <div class="fs13 t-mute mt4">
                                {{ $pedido->items->sum('qty') }} productos
                                @if ($entrega?->address) · {{ $entrega->address->street }} @endif
                            </div>

                            <div class="flex g8 mt12 wrap">
                                <span class="chip chip-{{ ['ok' => 'green', 'warn' => 'amber', 'late' => 'red'][$urgencia] }}">
                                    {{ $minutos }} min
                                </span>

                                @if ($entrega?->payment_method === 'cash')
                                    <span class="chip chip-line">Efectivo</span>
                                @else
                                    <span class="chip chip-line">Ya pagó</span>
                                @endif

                                @if ($entrega?->driver)
                                    <span class="chip chip-blue">{{ $entrega->driver->name }}</span>
                                @endif
                            </div>

                            @if ($proximo)
                                <div class="flex g8 mt12">
                                    @if ($proximo === 'on_route' && ! $entrega?->driver_id)
                                        <button class="btn btn-sm btn-primary grow"
                                                @click="asignando = {{ $pedido->id }}">
                                            Asignar repartidor
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

                                    @if ($anterior = $avanzar->anterior($pedido))
                                        <form method="POST" action="{{ route('pedidos.avanzar', $pedido) }}">
                                            @csrf
                                            <input type="hidden" name="estado" value="{{ $anterior }}">
                                            <button class="btn btn-sm" type="submit" title="Volver un paso">&larr;</button>
                                        </form>
                                    @endif
                                </div>
                            @endif

                            {{-- asignar repartidor --}}
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
                                                <p class="t-mute fs14">No hay repartidores cargados.</p>
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
                                        @endif
                                    </div>
                                </form>
                            </div>
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
                    <div class="fs13 t-mute">Vendido hoy</div>
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
