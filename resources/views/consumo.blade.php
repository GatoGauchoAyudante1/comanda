@extends('layouts.app')

@php
    // La carta viaja al navegador para que el carrito arme todo sin ir al servidor.
    $carta = $categorias->map(fn ($c) => [
        'id'        => $c->id,
        'nombre'    => $c->name,
        'productos' => $c->products->map(fn ($p) => [
            'id'        => $p->id,
            'nombre'    => $p->name,
            'precio'    => $p->price,
            'cocina'    => (bool) $p->goes_to_kitchen,
            'variantes' => $p->variants->map(fn ($v) => [
                'id'     => $v->id,
                'nombre' => $v->name,
                'precio' => $p->price + $v->price_delta,
            ])->values(),
        ])->values(),
    ])->values();
@endphp

@section('titulo', 'Agregar consumo')

@section('topbar')
    <a class="back" href="{{ route('mesa', $sesion) }}"><x-icono nombre="back" /></a>
    <div>
        <h1>Agregar consumo</h1>
        <div class="sub">{{ $sesion->table->name }} · abierta {{ $sesion->started_at->format('H:i') }}</div>
    </div>
@endsection

@section('contenido')
<div x-data="carrito()">
    <form method="POST" action="{{ route('consumo.guardar', $sesion) }}" @submit="serializar($el)">
        @csrf

        <div class="cols">

            {{-- ============ carta ============ --}}
            <div>
                <div class="filters">
                    <template x-for="c in carta" :key="c.id">
                        <button type="button" class="filter" :class="{ 'is-on': categoria === c.id }"
                                @click="categoria = c.id" x-text="c.nombre"></button>
                    </template>
                </div>

                <div class="pgrid">
                    <template x-for="p in productosVisibles()" :key="p.id">
                        <button type="button" class="pcard" :class="{ 'is-on': cantidadDe(p) > 0 }"
                                @click="agregar(p)">
                            <span class="cnt" x-show="cantidadDe(p) > 0" x-text="cantidadDe(p)"></span>
                            <span class="nm" x-text="p.nombre"></span>
                            <span class="pr" x-text="pesos(p.variantes.length ? p.variantes[0].precio : p.precio)"></span>
                            <span class="fs12 t-mute" x-show="p.variantes.length"
                                  x-text="p.variantes.map(v => v.nombre).join(' · ')"></span>
                        </button>
                    </template>
                </div>

                <div class="flex g8 mt20 fs13 t-mute hide-mobile">
                    <span class="dot dot-mute"></span>
                    Tocá el producto para sumar uno. Los que tienen tamaño preguntan cuál.
                </div>
            </div>

            {{-- ============ carrito ============ --}}
            <div>
                <div class="pane">
                    <div class="pane-hd">
                        <h3>Lo que estás cargando</h3>
                        <span class="badge" x-text="lineas.length"></span>
                    </div>

                    <p class="t-mute fs14" x-show="!lineas.length" style="padding:12px 0">
                        Todavía no elegiste nada.
                    </p>

                    <template x-for="(l, i) in lineas" :key="i">
                        <div class="row">
                            <span class="qty" x-text="l.qty"></span>
                            <div class="grow">
                                <div class="nm fs15" x-text="l.nombre"></div>
                                <div class="sb">
                                    <span x-text="pesos(l.precio) + ' c/u'"></span>
                                    <span x-show="l.cocina" class="t-amber"> · va a cocina</span>
                                </div>
                                <input class="inp inp-sm mt8" placeholder="Nota para cocina…"
                                       maxlength="120" x-model="l.notes" x-show="l.cocina">
                            </div>
                            <span class="pr fs17" x-text="pesos(l.precio * l.qty)"></span>
                            <button type="button" class="xbtn" @click="quitar(i)">&times;</button>
                        </div>
                    </template>

                    <div class="hr-strong"></div>
                    <div class="between">
                        <span class="t-dim">Subtotal</span>
                        <span class="money m-xl" x-text="pesos(total())"></span>
                    </div>

                    <button class="btn btn-primary btn-lg btn-block mt16" type="submit"
                            :disabled="!lineas.length" :style="lineas.length ? '' : 'opacity:.4'">
                        Confirmar y cargar a la mesa
                    </button>
                    <a class="btn btn-quiet btn-block mt8" href="{{ route('mesa', $sesion) }}">Cancelar</a>
                </div>
            </div>
        </div>

        <div x-ref="hidden"></div>
    </form>

    {{-- Elegir tamaño cuando el producto tiene variantes --}}
    <div class="overlay" x-show="eligiendo" x-cloak @click.self="eligiendo = null">
        <div class="modal" style="max-width:420px">
            <div class="modal-hd">
                <h2 x-text="eligiendo?.nombre"></h2>
            </div>
            <div class="modal-bd">
                <div class="opt-row opt-col">
                    <template x-for="v in (eligiendo?.variantes ?? [])" :key="v.id">
                        <button type="button" class="opt" style="height:56px"
                                @click="agregarVariante(eligiendo, v)">
                            <span x-text="v.nombre"></span>
                            <span class="fw6" x-text="pesos(v.precio)"></span>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function carrito() {
    return {
        carta: @json($carta),
        categoria: {{ $categorias->first()?->id ?? 0 }},
        lineas: [],
        eligiendo: null,

        pesos(centavos) {
            return '$' + (centavos / 100).toLocaleString('es-AR', { maximumFractionDigits: 0 });
        },

        productosVisibles() {
            return this.carta.find(c => c.id === this.categoria)?.productos ?? [];
        },

        cantidadDe(p) {
            return this.lineas.filter(l => l.product_id === p.id).reduce((n, l) => n + l.qty, 0);
        },

        agregar(p) {
            if (p.variantes.length) { this.eligiendo = p; return; }
            this.sumar({ product_id: p.id, variant_id: null, nombre: p.nombre, precio: p.precio, cocina: p.cocina });
        },

        agregarVariante(p, v) {
            this.sumar({
                product_id: p.id, variant_id: v.id,
                nombre: p.nombre + ' ' + v.nombre, precio: v.precio, cocina: p.cocina,
            });
            this.eligiendo = null;
        },

        // Los que van a cocina no se agrupan: cada uno puede llevar su propia nota.
        sumar(base) {
            const igual = this.lineas.find(l =>
                l.product_id === base.product_id && l.variant_id === base.variant_id && !l.cocina);

            if (igual) { igual.qty++; return; }

            this.lineas.push({ ...base, qty: 1, notes: '' });
        },

        quitar(i) { this.lineas.splice(i, 1); },

        total() { return this.lineas.reduce((n, l) => n + l.precio * l.qty, 0); },

        // Arma los inputs ocultos justo antes de enviar.
        serializar(form) {
            const caja = this.$refs.hidden;
            caja.innerHTML = '';

            this.lineas.forEach((l, i) => {
                const campos = {
                    product_id: l.product_id,
                    variant_id: l.variant_id ?? '',
                    qty: l.qty,
                    notes: l.notes ?? '',
                };

                for (const [clave, valor] of Object.entries(campos)) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `lineas[${i}][${clave}]`;
                    input.value = valor;
                    caja.appendChild(input);
                }
            });
        },
    };
}
</script>
@endsection
