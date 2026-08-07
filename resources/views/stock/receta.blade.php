@extends('layouts.app')

@php
    use App\Support\Plata;
    use App\Support\Unidades;

    $costo  = $producto->costo();
    $margen = $producto->margen();
@endphp

@section('titulo', 'Receta')

@section('topbar')
    <a class="back" href="{{ route('recetas') }}"><x-icono nombre="back" /></a>
    <div>
        <h1>Receta · {{ $producto->name }}</h1>
        <div class="sub">
            Precio @plata($producto->price) · categoría {{ $producto->category->name }}
        </div>
    </div>
@endsection

@section('contenido')
<div x-data="receta()">
    <div class="cols">

        {{-- ============ carga ============ --}}
        <div>
            <form method="POST" action="{{ route('receta.linea', $producto) }}">
                @csrf

                <div class="seg mb16">
                    <button type="button" :class="{ 'is-on': modo === 'rendimiento' }"
                            @click="modo = 'rendimiento'">Cargar por rendimiento</button>
                    <button type="button" :class="{ 'is-on': modo === 'cantidad' }"
                            @click="modo = 'cantidad'">Cargar por cantidad</button>
                </div>
                <input type="hidden" name="modo" :value="modo">

                <div class="phrase">
                    <div class="line">
                        <span class="t-dim">Insumo</span>
                        <select class="inp" name="ingredient_id" x-model="insumo" required style="min-width:260px">
                            <option value="">Elegí uno…</option>
                            @foreach ($insumos as $i)
                                <option value="{{ $i->id }}">{{ $i->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- modo rendimiento: como lo dice el dueño --}}
                    <template x-if="modo === 'rendimiento'">
                        <div>
                            <div class="line">
                                <span class="t-dim">De un envase de</span>
                                <input class="inp" type="number" step="0.001" min="0.001" name="contenido"
                                       x-model.number="contenido" inputmode="decimal" style="width:110px">
                                <select class="inp" name="unidad" x-model="unidad" style="width:auto">
                                    <template x-for="u in unidades()" :key="u">
                                        <option :value="u" x-text="u"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="line">
                                <span class="t-dim">me salen</span>
                                <input class="inp" type="number" step="1" min="1" name="rinde"
                                       x-model.number="rinde" inputmode="numeric" style="width:96px">
                                <span class="t-dim">unidades de</span>
                                <span class="fw6">{{ $producto->name }}</span>
                            </div>
                        </div>
                    </template>

                    {{-- modo cantidad: para quien piensa en gramos --}}
                    <template x-if="modo === 'cantidad'">
                        <div class="line">
                            <span class="t-dim">Cada unidad lleva</span>
                            <input class="inp" type="number" step="0.001" min="0.001" name="cantidad"
                                   x-model.number="cantidad" inputmode="decimal" style="width:120px">
                            <select class="inp" name="unidad" x-model="unidad" style="width:auto">
                                <template x-for="u in unidades()" :key="u">
                                    <option :value="u" x-text="u"></option>
                                </template>
                            </select>
                        </div>
                    </template>

                    <div class="hr" style="background:var(--green-line);opacity:.4"></div>

                    <div class="flex g10 wrap fs17 t-green fw6" x-show="porUnidad() > 0">
                        <span>↳ Equivale a <span x-text="legible(porUnidad())"></span> por unidad</span>
                    </div>
                    <div class="fs13 t-mute" x-show="porUnidad() <= 0">
                        Completá los datos y te muestro cuánto lleva cada unidad.
                    </div>

                    <label class="half mt16" style="cursor:pointer">
                        <input type="checkbox" name="only_for_delivery" value="1"
                               style="width:18px;height:18px;accent-color:var(--green)">
                        <div class="grow">
                            <div class="fw6">Sólo en delivery y retiro</div>
                            <div class="fs13 t-mute">Para la caja de pizza, bolsas, descartables.</div>
                        </div>
                    </label>

                    <button class="btn btn-primary btn-lg btn-block mt16" type="submit">Agregar a la receta</button>
                </div>
            </form>

            {{-- ============ insumos cargados ============ --}}
            <div class="card mt16">
                <div class="sec">
                    Insumos de la receta
                    <span class="meta">{{ $producto->recipe->count() }} insumos</span>
                </div>

                @forelse ($producto->recipe as $linea)
                    <div class="row">
                        <div class="grow">
                            <div class="nm">{{ $linea->ingredient->name }}</div>
                            @php $com = Unidades::comercial($linea->ingredient->base_unit); @endphp
                            <div class="sb">
                                @plata((int) round($linea->ingredient->cost * $com['factor'])) por {{ $com['unidad'] }}
                                @if ($linea->only_for_delivery)
                                    · <span class="t-amber">sólo delivery</span>
                                @endif
                            </div>
                        </div>
                        <span class="chip chip-line">
                            {{ Unidades::legible($linea->qty, $linea->ingredient->base_unit, 3) }}
                        </span>
                        <span class="pr fs17">
                            @plata((int) round($linea->qty * $linea->ingredient->cost))
                        </span>
                        <form method="POST" action="{{ route('receta.linea.borrar', [$producto, $linea]) }}">
                            @csrf
                            @method('DELETE')
                            <button class="xbtn" type="submit">&times;</button>
                        </form>
                    </div>
                @empty
                    <p class="t-mute fs14" style="padding:12px 0">
                        Sin receta. Este producto se vende pero no descuenta stock ni calcula margen.
                    </p>
                @endforelse
            </div>

            <div class="notice mt16">
                <span class="dot dot-mute"></span>
                <div>
                    <div class="tt">Los dos modos guardan lo mismo</div>
                    <div class="ds">
                        Internamente siempre se guarda la cantidad por unidad. El rendimiento
                        es sólo una forma más cómoda de cargarlo.
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ costo y margen ============ --}}
        <div>
            <div class="pane">
                <div class="pane-hd"><h3>Costo y margen</h3></div>

                @if ($margen !== null)
                    @php $dash = (int) round(415 * min(100, max(0, $margen)) / 100); @endphp
                    <svg width="168" height="168" viewBox="0 0 168 168" style="display:block;margin:0 auto">
                        <circle cx="84" cy="84" r="66" fill="none" style="stroke:var(--panel-3)" stroke-width="12"/>
                        <circle cx="84" cy="84" r="66" fill="none" style="stroke:var(--green)" stroke-width="12"
                                stroke-linecap="round" stroke-dasharray="{{ $dash }} 415"
                                transform="rotate(-90 84 84)"/>
                        <text x="84" y="80" text-anchor="middle" style="fill:var(--txt)"
                              font-size="34" font-weight="700" font-family="Outfit,sans-serif">
                            {{ number_format($margen, 1, ',', '.') }}%
                        </text>
                        <text x="84" y="104" text-anchor="middle" style="fill:var(--txt-2)"
                              font-size="14" font-family="Outfit,sans-serif">de margen</text>
                    </svg>
                    <div class="hr"></div>
                @endif

                <div class="lv"><span class="k">Costo de insumos</span><span class="v">@plata($costo)</span></div>
                <div class="lv"><span class="k">Precio de venta</span><span class="v">@plata($producto->price)</span></div>
                <div class="lv">
                    <span class="k">Ganancia por unidad</span>
                    <span class="v t-green">@plata($producto->price - $costo)</span>
                </div>
            </div>

            <div class="pane">
                <div class="pane-hd"><h3>Producción posible</h3></div>
                <div class="money m-xl">{{ $produccion['unidades'] }}</div>
                <div class="fs13 t-mute mt8">Con el stock que hay ahora mismo</div>

                @if ($producto->recipe->isNotEmpty())
                    <div class="hr"></div>
                    @foreach ($producto->recipe as $linea)
                        @php
                            $posibles = $linea->qty > 0 ? (int) floor($linea->ingredient->stock / $linea->qty) : 0;
                            $limita   = $produccion['limita']?->id === $linea->ingredient_id;
                            $ancho    = $produccion['unidades'] > 0
                                ? min(100, (int) round($posibles / max(1, $produccion['unidades'] * 4) * 100))
                                : 0;
                        @endphp
                        <div class="mb12">
                            <div class="between fs13 mb8">
                                <span class="{{ $limita ? 't-amber fw6' : 't-dim' }}">{{ $linea->ingredient->name }}</span>
                                <span class="{{ $limita ? 't-amber' : 't-dim' }}">
                                    {{ $posibles }}{{ $limita ? ' · te limita' : '' }}
                                </span>
                            </div>
                            <div class="meter {{ $limita ? 'amber' : '' }}">
                                <i style="width:{{ $limita ? 16 : max(20, $ancho) }}%"></i>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>

            @if ($produccion['limita'])
                <div class="callout mt16">
                    <div class="fw6 t-amber mb8">Te limita: {{ $produccion['limita']->name }}</div>
                    <div class="fs13 t-dim">
                        Quedan {{ Unidades::legible($produccion['limita']->stock, $produccion['limita']->base_unit, 1) }}
                        y el mínimo son {{ Unidades::legible($produccion['limita']->min_stock, $produccion['limita']->base_unit, 1) }}.
                    </div>
                </div>
            @endif

            @if ($sinReceta->isNotEmpty())
                <div class="pane mt16">
                    <div class="pane-hd">
                        <h3>Sin receta</h3>
                        <span class="badge" style="background:var(--amber-dim);color:var(--amber)">{{ $sinReceta->count() }}</span>
                    </div>
                    <p class="fs13 t-mute mb12">Estos se venden pero no descuentan stock.</p>
                    @foreach ($sinReceta->take(6) as $p)
                        <a class="cat" href="{{ route('receta', $p) }}">{{ $p->name }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

<script>
function receta() {
    return {
        modo: 'rendimiento',
        insumo: '',
        contenido: null,
        rinde: null,
        cantidad: null,
        unidad: 'g',

        equivalencias: @json(\App\Support\Unidades::EQUIVALENCIAS),
        insumos: @json($insumos->map->only(['id', 'name', 'base_unit'])),

        base() {
            return this.insumos.find(i => i.id === Number(this.insumo))?.base_unit ?? 'g';
        },

        unidades() { return Object.keys(this.equivalencias[this.base()] ?? { un: 1 }); },

        // Misma cuenta que RecipeItem::desdeRendimiento() y Unidades::aBase().
        porUnidad() {
            const factor = (this.equivalencias[this.base()] ?? {})[this.unidad] ?? 1;

            if (this.modo === 'rendimiento') {
                if (!this.contenido || !this.rinde) return 0;
                return (this.contenido * factor) / this.rinde;
            }

            return (this.cantidad ?? 0) * factor;
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
