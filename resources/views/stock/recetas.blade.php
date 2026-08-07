@extends('layouts.app')

@php use App\Support\Unidades; @endphp

@section('titulo', 'Recetas')

{{-- El estado vive en el layout: si no, el diálogo queda fuera del alcance. --}}
@section('alpine', 'recetas()')

@section('topbar')
    <div>
        <h1>Recetas</h1>
        <div class="sub">
            {{ $conReceta->count() }} {{ $conReceta->count() === 1 ? 'cargada' : 'cargadas' }}
            @if ($pendientes->isNotEmpty())
                · <span class="t-amber">{{ $pendientes->count() }} sin cargar</span>
            @endif
        </div>
    </div>
    <div class="topbar-actions">
        <a class="btn hide-mobile" href="{{ route('stock') }}">Ir a Stock</a>
        <a class="btn hide-mobile" href="{{ route('carta') }}">Ir a Carta</a>
    </div>
@endsection

@section('contenido')

    <div class="stats stats-3 mb16">
        <div class="stat">
            <div class="label">Con receta</div>
            <div class="val">{{ $conReceta->count() }}</div>
            <div class="foot">Descuentan stock y calculan margen</div>
        </div>
        <div class="stat">
            <div class="label">Sin receta</div>
            <div class="val {{ $pendientes->isNotEmpty() ? 't-amber' : '' }}">{{ $pendientes->count() }}</div>
            <div class="foot">
                {{ $pendientes->pluck('name')->take(3)->join(', ') ?: 'Ninguno pendiente' }}
            </div>
        </div>
        <div class="stat">
            <div class="label">Margen promedio</div>
            <div class="val">
                {{ $margenProm !== null ? number_format($margenProm, 1, ',', '.') . '%' : '—' }}
            </div>
            <div class="foot">Sobre los productos con receta cargada</div>
        </div>
    </div>

    {{-- Las dos direcciones de carga. Misma tabla, distinta forma de hablar:
         «la hamburguesa lleva 180 g de carne» o «de 1 kg de carne salen 5». --}}
    <div class="filters">
        <a class="filter {{ $vista === 'producto' ? 'is-on' : '' }}"
           href="{{ route('recetas') }}">Por producto</a>
        <a class="filter {{ $vista === 'insumo' ? 'is-on' : '' }}"
           href="{{ route('recetas', ['vista' => 'insumo']) }}">Por insumo</a>
    </div>

@if ($vista === 'insumo')

    {{-- ============================================================
         POR INSUMO — «de 1 kg de carne me salen 5 hamburguesas»
         ============================================================ --}}
    <div class="card card-flush">
        <div class="tbl-wrap">
            <table class="tbl" style="min-width:820px">
                <thead>
                    <tr>
                        <th>Insumo</th>
                        <th class="num">Costo</th>
                        <th>Entra en</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($insumos as $i)
                    @php
                        $com   = Unidades::comercial($i->base_unit);
                        $lineas = $i->recipeItems->sortByDesc('qty');
                        $json  = json_encode(
                            $i->only(['id', 'name', 'base_unit']),
                            JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT,
                        );
                    @endphp
                    <tr>
                        <td class="lead">
                            {{ $i->name }}
                            <div class="fs12 t-mute mt4">
                                {{ $i->area }} · se mide en {{ $i->base_unit }}
                            </div>
                        </td>

                        <td class="num t-mute">
                            @plata((int) round($i->cost * $com['factor']))
                            <span class="fs12">/{{ $com['unidad'] }}</span>
                        </td>

                        <td>
                            @forelse ($lineas as $linea)
                                {{-- Cada producto rinde distinto del MISMO envase, y las
                                     dos cosas son ciertas a la vez: no es un reparto. --}}
                                <div class="between fs13 mb8">
                                    <a class="grow" href="{{ route('receta', $linea->product) }}">
                                        {{ $linea->product->name }}
                                    </a>
                                    <span class="t-dim">
                                        1 {{ $com['unidad'] }} → {{ $linea->rinde($com['factor']) }} un
                                        <span class="t-mute">
                                            ({{ Unidades::legible($linea->qty, $i->base_unit, 3) }} c/u)
                                        </span>
                                    </span>
                                </div>
                            @empty
                                <span class="chip chip-amber">en ninguna receta</span>
                            @endforelse
                        </td>

                        <td class="num">
                            <button type="button" class="btn btn-sm" @click="abrir({{ $json }})">
                                + Producto
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="t-mute">
                            No hay insumos todavía.
                            <a href="{{ route('stock') }}" style="text-decoration:underline">Cargalos en Stock</a>.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="notice mt16">
        <span class="dot dot-mute"></span>
        <div>
            <div class="tt">Un mismo envase rinde distinto en cada producto</div>
            <div class="ds">
                De 1 kg de carne pueden salir 5 hamburguesas <em>y</em> 4 porciones de albóndigas:
                las dos cosas son ciertas. No se reparte el kilo entre los productos —
                cada uno declara cuánto lleva, y el stock se descuenta por lo que se vende.
            </div>
        </div>
    </div>

    @include('stock.partials.dialogo-receta')

@else

    {{-- ============ pendientes ============
         Va arriba y con acceso directo: es el trabajo que falta hacer. --}}
    @if ($pendientes->isNotEmpty())
        <div class="card mb16">
            <div class="sec">
                Falta cargar la receta
                <span class="meta">Se venden, pero no descuentan insumos ni calculan margen</span>
            </div>

            @foreach ($pendientes as $p)
                <div class="row">
                    <div class="grow">
                        <div class="nm">{{ $p->name }}</div>
                        <div class="sb">{{ $p->category->name }} · @plata($p->price)</div>
                    </div>
                    <a class="btn btn-sm btn-primary" href="{{ route('receta', $p) }}">Cargar receta</a>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ============ recetas cargadas ============ --}}
    <div class="card card-flush">
        <div class="tbl-wrap">
            <table class="tbl" style="min-width:820px">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Insumos</th>
                        <th class="num">Costo</th>
                        <th class="num">Precio</th>
                        <th class="num">Ganancia</th>
                        <th class="num">Margen</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($conReceta as $p)
                    @php
                        $costo  = $p->costo();
                        $margen = $p->margen();

                        // Por debajo del 30% el plato casi no deja nada; en negativo
                        // se vende a pérdida y hay que verlo de lejos.
                        $tono = $margen === null ? '' : ($margen < 0 ? 't-red' : ($margen < 30 ? 't-amber' : 't-green'));
                    @endphp
                    <tr @class(['warn' => $margen !== null && $margen < 0])>
                        <td class="lead">
                            {{ $p->name }}
                            <div class="fs12 t-mute mt4">{{ $p->category->name }}</div>
                        </td>

                        <td>
                            <span class="chip chip-line">{{ $p->recipe->count() }} insumos</span>
                            <div class="fs12 t-mute mt4">
                                {{ $p->recipe->pluck('ingredient.name')->take(3)->join(', ') }}{{ $p->recipe->count() > 3 ? '…' : '' }}
                            </div>
                        </td>

                        <td class="num t-mute">@plata($costo)</td>
                        <td class="num">@plata($p->price)</td>
                        <td class="num {{ $tono }}">@plata($p->price - $costo)</td>
                        <td class="num {{ $tono }}">
                            {{ $margen !== null ? number_format($margen, 1, ',', '.') . '%' : '—' }}
                        </td>

                        <td class="num">
                            <a class="btn btn-sm" href="{{ route('receta', $p) }}">Editar</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="t-mute">
                            Todavía no hay ninguna receta cargada.
                            @if ($pendientes->isNotEmpty())
                                Empezá por «{{ $pendientes->first()->name }}» acá arriba.
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ============ los que no llevan receta ============ --}}
    @if ($sueltos->isNotEmpty())
        <div class="card mt16">
            <div class="sec">
                No controlan stock
                <span class="meta">{{ $sueltos->count() }} productos · no necesitan receta</span>
            </div>
            <p class="fs13 t-mute" style="padding:12px 0">
                Están marcados así en la carta, como el café. Se venden sin descontar
                insumos y no aparecen como pendientes.
            </p>
            @foreach ($sueltos as $p)
                <a class="cat" href="{{ route('receta', $p) }}">{{ $p->name }}</a>
            @endforeach
        </div>
    @endif

    <div class="notice notice-green mt16">
        <span class="dot"></span>
        <div>
            <div class="tt">La receta es lo que convierte el inventario en plata</div>
            <div class="ds">
                Sin ella, un producto se vende igual pero el stock miente y el margen
                no se puede calcular. Cargarla una vez alcanza.
            </div>
        </div>
    </div>

@endif

    <div class="flex g10 mt16 only-mobile">
        <a class="btn grow" href="{{ route('stock') }}">Stock</a>
        <a class="btn grow" href="{{ route('carta') }}">Carta</a>
    </div>

<script>
function recetas() {
    return {
        insumo: null,          // el insumo elegido; null = diálogo cerrado
        producto: '',
        contenido: 1,
        rinde: null,
        unidad: 'kg',

        equivalencias: @json(\App\Support\Unidades::EQUIVALENCIAS),

        abrir(i) {
            this.insumo    = i;
            this.producto  = '';
            this.contenido = 1;
            this.rinde     = null;
            this.unidad    = this.comercial(i.base_unit);
        },

        // La unidad en la que se compra: de un KILO salen N, no de un gramo.
        comercial(baseUnit) {
            return Object.keys(this.equivalencias[baseUnit] ?? { un: 1 }).at(-1);
        },

        base()     { return this.insumo?.base_unit ?? 'g'; },
        unidades() { return Object.keys(this.equivalencias[this.base()] ?? { un: 1 }); },

        // Misma cuenta que RecipeItem::desdeRendimiento(). Sólo la muestra:
        // el que guarda es el servidor.
        porUnidad() {
            if (!this.contenido || !this.rinde) return 0;

            const factor = (this.equivalencias[this.base()] ?? {})[this.unidad] ?? 1;

            return (this.contenido * factor) / this.rinde;
        },

        legible(enBase) {
            const opciones = Object.entries(this.equivalencias[this.base()] ?? { un: 1 }).reverse();

            for (const [etiqueta, factor] of opciones) {
                if (Math.abs(enBase) >= factor || factor === 1) {
                    const v = (enBase / factor).toFixed(3).replace(/\.?0+$/, '');
                    return `${v.replace('.', ',')} ${etiqueta}`;
                }
            }
            return enBase;
        },
    };
}
</script>
@endsection
