@extends('layouts.app')

@section('titulo', 'Carta')

{{-- El estado vive en el layout para que los botones de la barra superior
     también queden dentro del alcance de Alpine. --}}
@section('alpine', 'carta()')

@section('topbar')
    <div>
        <h1>Carta</h1>
        <div class="sub">
            {{ $categorias->sum('products_count') }} productos · {{ $categorias->count() }} categorías
        </div>
    </div>
    <div class="topbar-actions">
        <button class="btn hide-mobile" @click="lote = true">Actualizar precios en lote</button>
        @if ($esDueno)
            <button class="btn btn-primary" @click="abrirNuevo()">+ Nuevo producto</button>
        @endif
    </div>
@endsection

@section('contenido')

    @if ($sinReceta > 0)
        <div class="notice notice-amber mb16">
            <span class="dot dot-amber"></span>
            <div>
                <div class="tt">{{ $sinReceta }} {{ $sinReceta === 1 ? 'producto controla' : 'productos controlan' }} stock pero no {{ $sinReceta === 1 ? 'tiene' : 'tienen' }} receta</div>
                <div class="ds">
                    Se venden igual, pero no descuentan insumos ni calculan margen.
                    Mientras falten recetas, el control de stock muestra menos consumo del real.
                </div>
            </div>
            <a class="btn btn-sm" href="{{ route('recetas') }}">Ver recetas</a>
        </div>
    @endif

    <div class="cat-split">

        {{-- ============ categorías ============ --}}
        <div class="card card-tight hide-mobile">
            <div class="sec" style="padding:6px 10px 8px;margin:0">Categorías</div>
            @foreach ($categorias as $cat)
                <a class="cat {{ $actual?->id === $cat->id ? 'is-on' : '' }}"
                   href="{{ route('carta', ['categoria' => $cat->id]) }}">
                    {{ $cat->name }} <span class="n">{{ $cat->products_count }}</span>
                </a>
            @endforeach
            @if ($esDueno)
                <div class="hr"></div>
                <button class="btn btn-dashed btn-block btn-sm" @click="categoria = true">+ Categoría</button>
            @endif
        </div>

        <div class="filters only-mobile">
            @foreach ($categorias as $cat)
                <a class="filter {{ $actual?->id === $cat->id ? 'is-on' : '' }}"
                   href="{{ route('carta', ['categoria' => $cat->id]) }}">
                    {{ $cat->name }} <span class="n">{{ $cat->products_count }}</span>
                </a>
            @endforeach
        </div>

        {{-- ============ productos ============ --}}
        <div>
            <div class="card card-flush">
                <div class="tbl-wrap">
                    <table class="tbl" style="min-width:{{ $esDueno ? 820 : 420 }}px">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th class="num">Precio</th>
                                @if ($esDueno)
                                    <th class="num">Costo</th>
                                    <th>Margen</th>
                                    <th>Receta</th>
                                    <th>Va a cocina</th>
                                    <th>Activo</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                        @forelse ($productos as $p)
                            @php
                                $margen    = $esDueno ? $p->margen() : null;
                                $conReceta = $esDueno && $p->recipe->isNotEmpty();

                                // Lo que carga el diálogo de edición.
                                $json = json_encode([
                                    'id'              => $p->id,
                                    'name'            => $p->name,
                                    'category_id'     => $p->category_id,
                                    'price'           => $p->price / 100,
                                    'goes_to_kitchen' => (bool) $p->goes_to_kitchen,
                                    'tracks_stock'    => (bool) $p->tracks_stock,
                                ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
                            @endphp
                            <tr @class(['off' => ! $p->active])>
                                <td class="lead">
                                    @if ($esDueno)
                                        <button type="button" class="link-editar" @click="editar({{ $json }})"
                                                title="Editar «{{ $p->name }}»">
                                            {{ $p->name }}
                                        </button>
                                    @else
                                        {{-- Sin permiso de dueño el nombre no se toca: sólo el precio. --}}
                                        <span class="fw5">{{ $p->name }}</span>
                                    @endif
                                    @if ($p->variants->isNotEmpty())
                                        <div class="fs12 t-mute mt4">{{ $p->variants->pluck('name')->join(' · ') }}</div>
                                    @endif
                                </td>

                                {{-- El precio se edita acá mismo: se escribe y se sale del campo
                                     (o Enter) y el formulario se manda solo. Sin Alpine a propósito,
                                     así sigue andando aunque el diálogo no cargue. --}}
                                <td class="num">
                                    <form class="precio-edit" method="POST" action="{{ route('carta.precio', $p) }}">
                                        @csrf
                                        <span class="signo">$</span>
                                        <input class="inp-precio" type="number" name="price"
                                               min="0" step="1" inputmode="numeric"
                                               value="{{ $p->price / 100 }}"
                                               aria-label="Precio de {{ $p->name }}"
                                               title="Escribí el precio nuevo y tocá Enter"
                                               onfocus="this.select()"
                                               onchange="this.form.requestSubmit()"
                                               onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur()}
                                                          if(event.key==='Escape'){this.value=this.defaultValue;this.blur()}">
                                    </form>
                                </td>
                                {{-- Costo, margen y receta son la ganancia del negocio: sólo el dueño (R-27). --}}
                                @if ($esDueno)
                                <td class="num t-mute">
                                    {{ $conReceta ? \App\Support\Plata::format($p->costo()) : '—' }}
                                </td>

                                <td>
                                    @if ($margen !== null)
                                        <div class="flex g10">
                                            <span class="fw6">{{ number_format($margen, 0) }}%</span>
                                            <span class="meter grow {{ $margen < 40 ? 'amber' : '' }}" style="max-width:80px">
                                                <i style="width:{{ max(0, min(100, $margen)) }}%"></i>
                                            </span>
                                        </div>
                                    @else
                                        <span class="t-mute">—</span>
                                    @endif
                                </td>

                                <td>
                                    {{-- El chip es el link: se detecta el problema acá,
                                         así que la salida tiene que estar acá. --}}
                                    @if ($conReceta)
                                        <a class="chip chip-green" href="{{ route('receta', $p) }}"
                                           title="Ver la receta de {{ $p->name }}">
                                            {{ $p->recipe->count() }} insumos
                                        </a>
                                    @elseif ($p->tracks_stock)
                                        <a class="chip chip-amber" href="{{ route('receta', $p) }}"
                                           title="Cargar la receta de {{ $p->name }}">
                                            sin receta →
                                        </a>
                                    @else
                                        <span class="chip chip-line">no controla</span>
                                    @endif
                                </td>

                                <td>
                                    <form method="POST" action="{{ route('carta.alternar', $p) }}">
                                        @csrf
                                        <input type="hidden" name="campo" value="goes_to_kitchen">
                                        <button type="submit" class="sw {{ $p->goes_to_kitchen ? 'is-on' : '' }}"
                                                style="border:none;cursor:pointer"></button>
                                    </form>
                                </td>

                                <td>
                                    <form method="POST" action="{{ route('carta.alternar', $p) }}">
                                        @csrf
                                        <input type="hidden" name="campo" value="active">
                                        <button type="submit" class="sw {{ $p->active ? 'is-on' : '' }}"
                                                style="border:none;cursor:pointer"></button>
                                    </form>
                                </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $esDueno ? 7 : 2 }}" class="t-mute">Esta categoría todavía no tiene productos.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <button class="btn btn-dashed btn-block mt16 only-mobile" @click="lote = true">
                Actualizar precios en lote
            </button>
        </div>
    </div>

    {{-- Alta y edición de productos y categorías: sólo el dueño (R-27).
         Quien tiene el permiso de precios no ve estos diálogos, y las rutas
         que están detrás le devuelven 403 igual. --}}
    @if ($esDueno)

    {{-- ============================================================
         DIÁLOGO: PRODUCTO
         ============================================================ --}}
    <div class="overlay" x-show="producto" x-cloak @click.self="producto = null" @keydown.escape.window="producto = null">
        <form class="modal" method="POST"
              :action="producto?.id
                  ? '{{ route('carta.producto.actualizar', ['producto' => '__ID__']) }}'.replace('__ID__', producto.id)
                  : '{{ route('carta.producto') }}'">
            @csrf

            <div class="modal-hd">
                <div class="grow">
                    <h2 x-text="producto?.id ? 'Editar producto' : 'Nuevo producto'"></h2>
                    <div class="sub" x-show="producto?.id" x-text="producto?.name"></div>
                </div>
                <button class="xbtn" type="button" @click="producto = null">&times;</button>
            </div>

            <div class="modal-bd">
                <div class="modal-sec">
                    <div class="field">
                        <label for="p-name">Nombre</label>
                        <input id="p-name" class="inp" name="name" x-model="producto.name" required maxlength="120">
                    </div>
                </div>

                <div class="modal-sec grid2">
                    <div class="field">
                        <label for="p-cat">Categoría</label>
                        <select id="p-cat" class="inp" name="category_id" x-model="producto.category_id">
                            @foreach ($categorias as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="p-price">Precio de venta</label>
                        <input id="p-price" class="inp" type="number" step="1" min="0"
                               name="price" x-model="producto.price" required inputmode="numeric">
                    </div>
                </div>

                <div class="modal-sec">
                    <label class="half" style="cursor:pointer">
                        <input type="checkbox" name="goes_to_kitchen" value="1"
                               x-model="producto.goes_to_kitchen"
                               style="width:18px;height:18px;accent-color:var(--green)">
                        <div class="grow">
                            <div class="fw6">Va a cocina</div>
                            <div class="fs13 t-mute">Aparece en la pantalla de cocina al cargarlo.</div>
                        </div>
                    </label>

                    <label class="half mt12" style="cursor:pointer">
                        <input type="checkbox" name="tracks_stock" value="1"
                               x-model="producto.tracks_stock"
                               style="width:18px;height:18px;accent-color:var(--green)">
                        <div class="grow">
                            <div class="fw6">Descuenta stock</div>
                            <div class="fs13 t-mute">Desactivalo en cosas que no se controlan, como el café.</div>
                        </div>
                    </label>
                </div>
            </div>

            <div class="modal-ft">
                <div class="grow"></div>
                <button class="btn" type="button" @click="producto = null">Cancelar</button>
                <button class="btn btn-primary" type="submit">Guardar</button>
            </div>
        </form>
    </div>

    {{-- ============================================================
         DIÁLOGO: CATEGORÍA
         ============================================================ --}}
    <div class="overlay" x-show="categoria" x-cloak @click.self="categoria = false" @keydown.escape.window="categoria = false">
        <form class="modal" style="max-width:460px" method="POST" action="{{ route('carta.categoria') }}">
            @csrf
            <div class="modal-hd">
                <h2 class="grow">Nueva categoría</h2>
                <button class="xbtn" type="button" @click="categoria = false">&times;</button>
            </div>
            <div class="modal-bd">
                <div class="field">
                    <label for="c-name">Nombre</label>
                    <input id="c-name" class="inp" name="name" required maxlength="60" placeholder="Vinos, Cafetería…">
                </div>
                <label class="half mt16" style="cursor:pointer">
                    <input type="checkbox" name="goes_to_kitchen" value="1"
                           style="width:18px;height:18px;accent-color:var(--green)">
                    <div class="grow">
                        <div class="fw6">Va a cocina</div>
                        <div class="fs13 t-mute">Los productos nuevos arrancan con esta opción.</div>
                    </div>
                </label>
            </div>
            <div class="modal-ft">
                <div class="grow"></div>
                <button class="btn btn-primary" type="submit">Crear</button>
            </div>
        </form>
    </div>

    @endif

    {{-- ============================================================
         DIÁLOGO: PRECIOS EN LOTE
         ============================================================ --}}
    <div class="overlay" x-show="lote" x-cloak @click.self="lote = false" @keydown.escape.window="lote = false">
        <form class="modal" style="max-width:520px" method="POST" action="{{ route('carta.precios') }}"
              @submit="return confirm('Se van a modificar los precios. ¿Seguís?')">
            @csrf
            <div class="modal-hd">
                <div class="grow">
                    <h2>Actualizar precios</h2>
                    <div class="sub">Sube o baja varios precios de una vez</div>
                </div>
                <button class="xbtn" type="button" @click="lote = false">&times;</button>
            </div>

            <div class="modal-bd">
                <div class="modal-sec grid2">
                    <div class="field">
                        <label for="l-pct">Porcentaje</label>
                        <input id="l-pct" class="inp inp-lg" type="number" step="0.5" name="porcentaje"
                               x-model.number="pct" required placeholder="15">
                        <span class="fs13 t-mute">Negativo para bajar. Ej: -10</span>
                    </div>
                    <div class="field">
                        <label for="l-red">Redondear a</label>
                        <select id="l-red" class="inp inp-lg" name="redondeo" x-model.number="redondeo">
                            <option value="100">$100</option>
                            <option value="500">$500</option>
                            <option value="50">$50</option>
                            <option value="10">$10</option>
                            <option value="0">Sin redondear</option>
                        </select>
                    </div>
                </div>

                <div class="modal-sec">
                    <div class="field">
                        <label for="l-cat">Alcance</label>
                        <select id="l-cat" class="inp" name="category_id">
                            <option value="">Toda la carta</option>
                            @foreach ($categorias as $cat)
                                <option value="{{ $cat->id }}" @selected($actual?->id === $cat->id)>
                                    Sólo {{ $cat->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @if ($productos->isNotEmpty())
                    <div class="notice notice-green mt20">
                        <span class="dot"></span>
                        <div>
                            <div class="tt">Cómo quedaría</div>
                            <div class="ds">
                                @foreach ($productos->take(3) as $p)
                                    {{ $p->name }}:
                                    @plata($p->price) →
                                    <b class="t-white" x-text="simular({{ $p->price }})"></b><br>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="modal-ft">
                <div class="grow"></div>
                <button class="btn" type="button" @click="lote = false">Cancelar</button>
                <button class="btn btn-primary" type="submit">Aplicar</button>
            </div>
        </form>
    </div>

<script>
function carta() {
    return {
        producto: null,
        categoria: false,
        lote: false,
        pct: 15,
        redondeo: 100,

        abrirNuevo() {
            this.producto = {
                id: null, name: '', price: '',
                category_id: {{ $actual?->id ?? $categorias->first()?->id ?? 'null' }},
                goes_to_kitchen: {{ $actual?->goes_to_kitchen ? 'true' : 'false' }},
                tracks_stock: true,
            };
        },

        editar(p) { this.producto = { ...p }; },

        // Misma cuenta que App\Actions\AjustarPrecios::calcular()
        simular(centavos) {
            let nuevo = centavos * (1 + (this.pct || 0) / 100);

            if (this.redondeo > 0) {
                const paso = this.redondeo * 100;
                nuevo = Math.max(paso, Math.round(nuevo / paso) * paso);
            }

            return '$' + Math.round(nuevo / 100).toLocaleString('es-AR');
        },
    };
}
</script>
@endsection
