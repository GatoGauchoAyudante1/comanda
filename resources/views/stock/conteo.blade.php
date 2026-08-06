@extends('layouts.app')

@php use App\Support\Unidades; @endphp

@section('titulo', 'Conteo de stock')

@section('topbar')
    <a class="back" href="{{ route('stock') }}"><x-icono nombre="back" /></a>
    <div class="grow">
        <h1>Conteo de stock</h1>
        @if ($conteo)
            @php
                $total    = $conteo->items->count();
                $contados = $conteo->items->whereNotNull('counted_qty')->count();
            @endphp
            <div class="sub">
                {{ $conteo->area ? ucfirst($conteo->area) : 'Todas las áreas' }}
                · {{ $contados }} de {{ $total }} insumos
            </div>
            <div class="prog"><i style="width:{{ $total ? round($contados / $total * 100) : 0 }}%"></i></div>
        @else
            <div class="sub">Recorré el depósito con el celular y cargá lo que hay</div>
        @endif
    </div>
@endsection

@section('contenido')
<div class="narrow">

    @if (! $conteo)
        {{-- ============ arrancar ============ --}}
        <div class="card">
            <div class="sec">Empezar un conteo</div>
            <p class="t-dim fs14 mb16">
                Al abrirlo se saca una foto del stock teórico. Si alguien vende mientras
                contás, la diferencia no se ensucia.
            </p>

            <form method="POST" action="{{ route('conteo.abrir') }}">
                @csrf
                <div class="field mb16">
                    <label for="area">Qué vas a contar</label>
                    <select id="area" class="inp inp-lg" name="area">
                        <option value="">Todo</option>
                        @foreach ($areas as $a)
                            <option value="{{ $a }}">{{ ucfirst($a) }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-primary btn-lg btn-block" type="submit">Abrir conteo</button>
            </form>
        </div>

        <div class="notice notice-green mt16">
            <span class="dot"></span>
            <div>
                <div class="tt">Empezá por pocos insumos</div>
                <div class="ds">
                    Los 15 que son el 80% del costo: queso, harina, carne, bebidas.
                    Contar todo el inventario es la razón número uno por la que estos
                    módulos se abandonan a las dos semanas.
                </div>
            </div>
        </div>

        @if ($ultimos->isNotEmpty())
            <div class="card mt16">
                <div class="sec">Conteos anteriores</div>
                @foreach ($ultimos as $c)
                    <div class="row">
                        <div class="grow">
                            <div class="nm">{{ $c->closed_at->format('d/m/Y H:i') }}</div>
                            <div class="sb">
                                {{ $c->area ? ucfirst($c->area) : 'Todo' }} · {{ $c->user->name }}
                            </div>
                        </div>
                        <span class="pr {{ $c->difference_value < 0 ? 't-red' : ($c->difference_value > 0 ? 't-amber' : 't-green') }}">
                            @plata($c->difference_value)
                        </span>
                    </div>
                @endforeach
            </div>
        @endif

    @else
        {{-- ============ contando ============ --}}
        @foreach ($conteo->items as $item)
            @php
                $insumo     = $item->ingredient;
                $contado    = $item->counted_qty !== null;
                $diferencia = $contado ? (float) $item->counted_qty - (float) $item->expected_qty : 0;
            @endphp

            <div class="cnt-row {{ $contado ? '' : 'pendiente' }}">
                <form method="POST" action="{{ route('conteo.item', $item) }}">
                    @csrf
                    <div class="flex g14">
                        <div class="grow">
                            <div class="fw6 fs17">{{ $insumo->name }}</div>
                            <div class="fs13 t-mute mt4">
                                Sistema: {{ Unidades::legible($item->expected_qty, $insumo->base_unit, 2) }}
                            </div>
                        </div>

                        <input class="cnt-in" type="number" step="0.001" min="0" name="contado"
                               inputmode="decimal" placeholder="0"
                               value="{{ $contado ? rtrim(rtrim(number_format($item->counted_qty, 3, '.', ''), '0'), '.') : '' }}">

                        <select class="inp inp-sm" name="unidad" style="width:74px">
                            @foreach (Unidades::opciones($insumo->base_unit) as $u)
                                <option value="{{ $u }}" @selected($u === $insumo->base_unit)>{{ $u }}</option>
                            @endforeach
                        </select>

                        <button class="btn btn-sm" type="submit">Guardar</button>
                    </div>
                </form>

                @if ($contado)
                    <div class="flex g8 mt12">
                        @if (abs($diferencia) < 0.0001)
                            <span class="chip chip-green">Coincide</span>
                        @elseif ($diferencia < 0)
                            <span class="chip chip-red">
                                Faltan {{ Unidades::legible(abs($diferencia), $insumo->base_unit, 2) }}
                            </span>
                            <span class="fw6 t-red">@plata($item->difference_value)</span>
                        @else
                            <span class="chip chip-amber">
                                Sobra {{ Unidades::legible($diferencia, $insumo->base_unit, 2) }}
                            </span>
                            <span class="fw6 t-amber">+@plata($item->difference_value)</span>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach

        <div class="notice mt16">
            <span class="dot dot-mute"></span>
            <div>
                <div class="tt">Esta es la pantalla que paga el módulo</div>
                <div class="ds">
                    La diferencia en pesos es lo que detecta robo, desperdicio y recetas
                    mal cargadas. Al cerrar se generan ajustes: no se borra nada.
                </div>
            </div>
        </div>
    @endif
</div>

@if ($conteo)
    @php $acumulada = (int) $conteo->items->sum('difference_value'); @endphp
    <div class="dock">
        <div class="dock-inner narrow" style="width:100%">
            <div class="grow">
                <div class="fs13 t-mute">Diferencia acumulada</div>
                <div class="money m-lg mt4 {{ $acumulada < 0 ? 't-red' : ($acumulada > 0 ? 't-amber' : 't-green') }}">
                    @plata($acumulada)
                </div>
            </div>
            <form method="POST" action="{{ route('conteo.cerrar', $conteo) }}"
                  onsubmit="return confirm('Se van a ajustar los stocks contados. ¿Cerrás el conteo?')">
                @csrf
                <button class="btn btn-primary btn-lg" type="submit">Cerrar y ajustar</button>
            </form>
        </div>
    </div>
@endif
@endsection
