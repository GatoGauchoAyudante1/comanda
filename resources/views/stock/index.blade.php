@extends('layouts.app')

@php use App\Support\Unidades; @endphp

@section('titulo', 'Stock')

{{-- El estado vive en el layout para que los botones de la barra superior
     también queden dentro del alcance de Alpine. --}}
@section('alpine', 'stock()')

@section('topbar')
    <div>
        <h1>Stock</h1>
        <div class="sub">{{ $insumos->count() }} insumos · valor @plata($valorTotal)</div>
    </div>
    <div class="topbar-actions">
        <button class="btn hide-mobile" @click="nuevo()">+ Nuevo insumo</button>
        <button class="btn hide-mobile" @click="compra = true">Registrar compra</button>
        <button class="btn hide-mobile" @click="merma = true">Registrar merma</button>
        <a class="btn btn-primary" href="{{ route('conteo') }}">Hacer conteo</a>
    </div>
@endsection

@section('contenido')

    <div class="stats stats-3 mb16">
        <div class="stat">
            <div class="label">Valor del stock</div>
            <div class="val">@plata($valorTotal)</div>
            <div class="foot">{{ $insumos->count() }} insumos activos</div>
        </div>
        <div class="stat">
            <div class="label">Bajo mínimo</div>
            <div class="val {{ $bajoMinimo->isNotEmpty() ? 't-amber' : '' }}">{{ $bajoMinimo->count() }}</div>
            <div class="foot">{{ $bajoMinimo->pluck('name')->take(3)->join(', ') ?: 'Todo en orden' }}</div>
        </div>
        <div class="stat">
            <div class="label">Mermas del mes</div>
            <div class="val {{ $mermaMes > 0 ? 't-red' : '' }}">@plata($mermaMes)</div>
            <div class="foot">Roturas, anulaciones y ajustes</div>
        </div>
    </div>

    <div class="filters">
        <a class="filter {{ ! $area ? 'is-on' : '' }}" href="{{ route('stock') }}">Todos</a>
        <a class="filter {{ $area === 'minimo' ? 'is-on' : '' }}" href="{{ route('stock', ['area' => 'minimo']) }}">
            Bajo mínimo <span class="n">{{ $bajoMinimo->count() }}</span>
        </a>
        @foreach (\App\Models\Ingredient::AREAS as $a)
            <a class="filter {{ $area === $a ? 'is-on' : '' }}" href="{{ route('stock', ['area' => $a]) }}">
                {{ ucfirst($a) }}
            </a>
        @endforeach
    </div>

    <div class="card card-flush">
        <div class="tbl-wrap">
            <table class="tbl" style="min-width:860px">
                <thead>
                    <tr>
                        <th>Insumo</th>
                        <th class="num">Stock</th>
                        <th class="num">Mínimo</th>
                        <th>Alcanza para</th>
                        <th class="num">Costo</th>
                        <th class="num">Valor</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($insumos as $i)
                    @php
                        $receta = $i->recipeItems->sortByDesc('qty')->first();
                        $rinde  = $receta && $receta->qty > 0 ? (int) floor($i->stock / $receta->qty) : null;

                        // Fuera del atributo: @json() con un array literal adentro
                        // hace que el parser de Blade se cuelgue. Ya pasó en la carta.
                        // min_stock y cost viajan en unidad base y en centavos;
                        // el diálogo los pasa a unidad comercial. Ver editar().
                        $json = json_encode(
                            $i->only(['id', 'name', 'base_unit', 'area', 'min_stock', 'cost']),
                            JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT,
                        );
                    @endphp
                    <tr @class(['warn' => $i->bajoMinimo()])>
                        <td class="lead">
                            @if ($i->bajoMinimo())
                                <span class="dot dot-amber" style="display:inline-block;margin-right:8px"></span>
                            @endif
                            {{ $i->name }}
                            <div class="fs12 t-mute mt4">{{ $i->area }}</div>
                        </td>

                        <td class="num {{ $i->bajoMinimo() ? 't-amber' : '' }}">
                            {{ Unidades::legible($i->stock, $i->base_unit, 1) }}
                        </td>
                        <td class="num t-mute">{{ Unidades::legible($i->min_stock, $i->base_unit, 1) }}</td>

                        <td>
                            @if ($rinde !== null)
                                <span class="chip {{ $rinde < 10 ? 'chip-amber' : 'chip-green' }}">
                                    {{ $rinde }} × {{ $receta->product->name }}
                                </span>
                            @else
                                <span class="fs13 t-mute">sin receta</span>
                            @endif
                        </td>

                        @php $com = Unidades::comercial($i->base_unit); @endphp
                        <td class="num t-mute">
                            {{-- Se muestra por la unidad en la que se compra, no por gramo. --}}
                            @plata((int) round($i->cost * $com['factor']))
                            <span class="fs12">/{{ $com['unidad'] }}</span>
                        </td>
                        <td class="num">@plata($i->valor())</td>

                        <td class="num">
                            <button type="button" class="btn btn-sm" @click="editar({{ $json }})">
                                Editar
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="t-mute">
                            No hay insumos en este filtro.
                            @if (! $area)
                                <button type="button" class="btn btn-sm" style="margin-left:8px" @click="nuevo()">
                                    + Cargar el primero
                                </button>
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ============ producción posible ============ --}}
    @if ($produccion->isNotEmpty())
        <div class="card mt16">
            <div class="sec">
                Producción posible
                <span class="meta">Con el stock de ahora mismo</span>
            </div>

            @foreach ($produccion->take(6) as $fila)
                <div class="row">
                    <div class="grow">
                        <div class="nm">{{ $fila['producto']->name }}</div>
                        @if ($fila['limita'])
                            <div class="sb">Te limita: <span class="t-amber">{{ $fila['limita']->name }}</span></div>
                        @endif
                    </div>
                    <a class="btn btn-sm" href="{{ route('receta', $fila['producto']) }}">Ver receta</a>
                    <span class="pr {{ $fila['unidades'] < 10 ? 't-amber' : '' }}">
                        {{ $fila['unidades'] }}
                    </span>
                </div>
            @endforeach
        </div>
    @endif

    <div class="notice notice-green mt16">
        <span class="dot"></span>
        <div>
            <div class="tt">"Alcanza para" es la columna que mira el dueño</div>
            <div class="ds">
                Traduce el inventario a producción real usando las recetas.
                Nadie tiene que calcular nada.
            </div>
        </div>
    </div>

    <div class="flex g10 mt16 only-mobile">
        <button class="btn grow" @click="nuevo()">+ Insumo</button>
        <button class="btn grow" @click="compra = true">Compra</button>
        <button class="btn grow" @click="merma = true">Merma</button>
    </div>

    {{-- ============================================================
         DIÁLOGOS
         ============================================================ --}}
    @include('stock.partials.dialogo-insumo')
    @include('stock.partials.dialogo-movimiento', ['tipo' => 'compra'])
    @include('stock.partials.dialogo-movimiento', ['tipo' => 'merma'])

<script>
function stock() {
    return {
        insumo: null, compra: false, merma: false,
        // unidades disponibles según la unidad base del insumo
        equivalencias: @json(\App\Support\Unidades::EQUIVALENCIAS),
        elegido: null,
        // unidad en la que el diálogo escribe el mínimo y el costo
        unidad: 'kg',

        insumos: @json($insumos->map->only(['id', 'name', 'base_unit'])),

        /*
         | La base guarda unidad base (g, ml) y centavos; el diálogo trabaja en
         | unidad comercial (kg, L) y en pesos, que es como se compra.
         | Es la vuelta de lo que hace StockController::guardarInsumo().
         | Espejo de Unidades::comercial().
         */
        comercial(baseUnit) {
            const opciones = this.equivalencias[baseUnit] ?? { un: 1 };
            const unidad   = Object.keys(opciones).at(-1);

            return { unidad, factor: opciones[unidad] };
        },

        editar(i) {
            const com = this.comercial(i.base_unit);

            this.unidad = com.unidad;
            this.insumo = {
                ...i,
                min_stock: this.limpio(i.min_stock / com.factor),
                cost:      this.limpio(i.cost * com.factor / 100),
            };
        },

        nuevo() {
            this.unidad = this.comercial('g').unidad;
            this.insumo = { id: null, name: '', base_unit: 'g', area: 'cocina', min_stock: '', cost: '' };
        },

        // Cambiar la unidad base cambia las opciones: la elegida ya no aplica.
        cambiarBase() { this.unidad = this.comercial(this.insumo.base_unit).unidad; },

        // Evita que 0.1+0.2 se filtre al input como 0.30000000000000004.
        limpio(n) { return Number(Number(n).toFixed(4)); },

        unidadesDe(baseUnit) { return Object.keys(this.equivalencias[baseUnit] ?? { un: 1 }); },

        unidadesDelElegido() {
            const i = this.insumos.find(x => x.id === Number(this.elegido));
            return i ? this.unidadesDe(i.base_unit) : [];
        },
    };
}
</script>
@endsection
