@extends('layouts.app')

@php
    $carta = $categorias->map(fn ($c) => [
        'id'        => $c->id,
        'nombre'    => $c->name,
        'productos' => $c->products->map(fn ($p) => [
            'id'        => $p->id,
            'nombre'    => $p->name,
            'precio'    => $p->price,
            'cocina'    => (bool) $p->goes_to_kitchen,
            'variantes' => $p->variants->map(fn ($v) => [
                'id' => $v->id, 'nombre' => $v->name, 'precio' => $p->price + $v->price_delta,
            ])->values(),
        ])->values(),
    ])->values();

    $envios = $zonas->mapWithKeys(fn ($z) => [$z->id => $z->delivery_fee]);
@endphp

@section('titulo', 'Nuevo pedido')

@section('topbar')
    <a class="back" href="{{ route('pedidos') }}"><x-icono nombre="back" /></a>
    <div>
        <h1>Nuevo pedido</h1>
        <div class="sub">Tomalo mientras hablás por teléfono</div>
    </div>
@endsection

@section('contenido')
{{--
  Mockups 05 y 15 los tenían en dos pantallas separadas. Acá van juntos:
  navegar a otra página mientras el cliente dicta el pedido pierde el carrito
  y obliga a pedirle que repita.
--}}
<div x-data="pedido()">
    <form method="POST" action="{{ route('pedidos.guardar') }}" @submit="serializar()">
        @csrf

        <div class="cols">

            {{-- ============ datos del pedido ============ --}}
            <div>

                <div class="card">
                    <div class="sec">Cliente</div>

                    <div class="grid2">
                        <div class="field">
                            <label for="tel">Teléfono</label>
                            <input id="tel" class="inp inp-lg" name="telefono" x-model="telefono"
                                   @input.debounce.400ms="buscar()" required inputmode="tel"
                                   placeholder="11 5548 2210" autofocus>
                        </div>
                        <div class="field">
                            <label for="nom">Nombre</label>
                            <input id="nom" class="inp inp-lg" name="nombre" x-model="nombre"
                                   maxlength="120" placeholder="Opcional">
                        </div>
                    </div>

                    <template x-if="conocido">
                        <div class="flex g10 mt12 wrap">
                            <span class="chip chip-green">
                                Cliente conocido · <span x-text="conocido.pedidos"></span> pedidos
                            </span>
                            <button type="button" class="chip chip-line" @click="usarDatos()"
                                    x-show="conocido.calle" style="cursor:pointer">
                                Usar <span x-text="conocido.calle"></span>
                            </button>
                        </div>
                    </template>
                </div>

                <div class="card mt16">
                    <div class="sec">Entrega</div>

                    <div class="grid2 mb16">
                        <button type="button" class="pay" style="height:64px"
                                :class="{ 'is-on': tipo === 'delivery' }" @click="tipo = 'delivery'">Delivery</button>
                        <button type="button" class="pay" style="height:64px"
                                :class="{ 'is-on': tipo === 'retiro' }" @click="tipo = 'retiro'">Retira en el local</button>
                    </div>
                    <input type="hidden" name="type" :value="tipo">

                    <template x-if="tipo === 'delivery'">
                        <div>
                            <div class="field mb12">
                                <label for="calle">Dirección</label>
                                <input id="calle" class="inp" name="calle" x-model="calle" maxlength="160"
                                       placeholder="Av. Sarmiento 1482">
                            </div>

                            <div class="grid2">
                                <div class="field">
                                    <label for="detalle">Detalle</label>
                                    <input id="detalle" class="inp" name="detalle" x-model="detalle"
                                           maxlength="120" placeholder="timbre 3B, dpto 2A…">
                                </div>
                                <div class="field">
                                    <label for="zona">Zona</label>
                                    <select id="zona" class="inp" name="zone_id" x-model.number="zona">
                                        <option value="">Sin zona</option>
                                        @foreach ($zonas as $z)
                                            <option value="{{ $z->id }}">{{ $z->name }} · @plata($z->delivery_fee)</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div class="field mt12">
                        <label for="notas">Notas de entrega</label>
                        <textarea id="notas" class="inp" name="notas" maxlength="300"
                                  placeholder="Tocar timbre, no funciona el portero…"></textarea>
                    </div>
                </div>

                {{-- ============ productos ============ --}}
                <div class="card mt16">
                    <div class="sec">
                        Productos
                        <span class="meta" x-text="lineas.reduce((n, l) => n + l.qty, 0) + ' unidades'"></span>
                    </div>

                    <p class="t-mute fs14" x-show="!lineas.length" style="padding:12px 0">
                        Todavía no cargaste nada.
                    </p>

                    <template x-for="(l, i) in lineas" :key="i">
                        <div class="row">
                            <span class="qty" x-text="l.qty"></span>
                            <div class="grow">
                                <div class="nm" x-text="l.nombre"></div>
                                <div class="sb" x-text="pesos(l.precio) + ' c/u'"></div>
                                <input class="inp inp-sm mt8" placeholder="Nota para cocina…"
                                       maxlength="120" x-model="l.notes" x-show="l.cocina">
                            </div>
                            <span class="pr" x-text="pesos(l.precio * l.qty)"></span>
                            <button type="button" class="xbtn" @click="lineas.splice(i, 1)">&times;</button>
                        </div>
                    </template>

                    <button type="button" class="btn btn-dashed btn-block mt16" @click="eligiendo = true">
                        + Agregar producto
                    </button>
                </div>
            </div>

            {{-- ============ resumen ============ --}}
            <div>
                <div class="pane">
                    <div class="pane-hd"><h3>Resumen</h3></div>
                    <div class="lv"><span class="k">Productos</span><span class="v" x-text="pesos(subtotal())"></span></div>
                    <div class="lv" x-show="tipo === 'delivery'">
                        <span class="k">Envío</span><span class="v" x-text="pesos(envio())"></span>
                    </div>
                    <div class="hr-strong"></div>
                    <div class="between">
                        <span class="t-dim">Total</span>
                        <span class="money m-xxl" x-text="pesos(total())"></span>
                    </div>
                </div>

                <div class="pane">
                    <div class="sec">Cómo paga</div>
                    <div class="pays">
                        <button type="button" class="pay" style="height:82px" :class="{ 'is-on': pago === 'cash' }"
                                @click="pago = (pago === 'cash' ? null : 'cash')"><x-icono nombre="cash" />Efectivo</button>
                        <button type="button" class="pay" style="height:82px" :class="{ 'is-on': pago === 'qr' }"
                                @click="pago = (pago === 'qr' ? null : 'qr'); pagaCon = null"><x-icono nombre="qr" />QR / Transf.</button>
                        <button type="button" class="pay" style="height:82px" :class="{ 'is-on': pago === 'debit' }"
                                @click="pago = (pago === 'debit' ? null : 'debit'); pagaCon = null"><x-icono nombre="card" />Tarjeta</button>
                    </div>
                    <input type="hidden" name="metodo_pago" :value="pago ?? ''">

                    <template x-if="pago === 'cash'">
                        <div>
                            <div class="field mt16">
                                <label for="pagacon">Paga con</label>
                                <input id="pagacon" class="inp" type="number" step="1" min="0"
                                       name="paga_con" x-model.number="pagaCon" inputmode="numeric"
                                       placeholder="50000">
                            </div>

                            <div class="notice notice-green mt12" x-show="pagaCon * 100 > total()">
                                <span class="dot"></span>
                                <div>
                                    <div class="tt">Vuelto <span x-text="pesos(pagaCon * 100 - total())"></span></div>
                                    <div class="ds">Avisale al repartidor que lleve cambio.</div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div class="notice mt12" x-show="pago === null">
                        <span class="dot dot-mute"></span>
                        <div>
                            <div class="tt">Se define al entregar</div>
                            <div class="ds">Si el cliente todavía no sabe cómo va a pagar, dejalo así: el repartidor lo confirma o lo corrige antes de cobrar.</div>
                        </div>
                    </div>
                </div>

                <button class="btn btn-primary btn-lg btn-block mt16" type="submit"
                        :disabled="!lineas.length" :style="lineas.length ? '' : 'opacity:.4'">
                    Confirmar y enviar a cocina
                </button>
            </div>
        </div>

        <div x-ref="hidden"></div>
    </form>

    {{-- ============ elegir productos ============ --}}
    <div class="overlay" x-show="eligiendo" x-cloak @click.self="eligiendo = false" @keydown.escape.window="eligiendo = false">
        <div class="modal" style="max-width:900px">
            <div class="modal-hd">
                <h2 class="grow">Agregar productos</h2>
                <button class="xbtn" type="button" @click="eligiendo = false">&times;</button>
            </div>

            <div class="modal-bd">
                <div class="filters">
                    <template x-for="c in carta" :key="c.id">
                        <button type="button" class="filter" :class="{ 'is-on': categoria === c.id }"
                                @click="categoria = c.id" x-text="c.nombre"></button>
                    </template>
                </div>

                <div class="pgrid">
                    <template x-for="p in visibles()" :key="p.id">
                        <button type="button" class="pcard" :class="{ 'is-on': cantidadDe(p) > 0 }" @click="elegir(p)">
                            <span class="cnt" x-show="cantidadDe(p) > 0" x-text="cantidadDe(p)"></span>
                            <span class="nm" x-text="p.nombre"></span>
                            <span class="pr" x-text="pesos(p.variantes.length ? p.variantes[0].precio : p.precio)"></span>
                            <span class="fs12 t-mute" x-show="p.variantes.length"
                                  x-text="p.variantes.map(v => v.nombre).join(' · ')"></span>
                        </button>
                    </template>
                </div>
            </div>

            <div class="modal-ft">
                <div class="grow">
                    <span class="t-dim fs14">Subtotal </span>
                    <span class="money m-sm" x-text="pesos(subtotal())"></span>
                </div>
                <button class="btn btn-primary" type="button" @click="eligiendo = false">Listo</button>
            </div>
        </div>
    </div>

    {{-- elegir tamaño --}}
    <div class="overlay" x-show="variantes" x-cloak @click.self="variantes = null" style="z-index:110">
        <div class="modal" style="max-width:400px">
            <div class="modal-hd"><h2 x-text="variantes?.nombre"></h2></div>
            <div class="modal-bd">
                <div class="opt-row opt-col">
                    <template x-for="v in (variantes?.variantes ?? [])" :key="v.id">
                        <button type="button" class="opt" style="height:56px" @click="agregarVariante(variantes, v)">
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
function pedido() {
    return {
        carta: @json($carta),
        envios: @json($envios),
        categoria: {{ $categorias->first()?->id ?? 0 }},

        tipo: 'delivery',
        telefono: '', nombre: '', calle: '', detalle: '', zona: '',
        pago: null, pagaCon: null,
        conocido: null,

        lineas: [],
        eligiendo: false,
        variantes: null,

        pesos(c) { return '$' + Math.round(c / 100).toLocaleString('es-AR'); },

        // R-14: el telefono identifica al cliente y trae todo lo demas.
        async buscar() {
            const limpio = this.telefono.replace(/\D/g, '');
            if (limpio.length < 8) { this.conocido = null; return; }

            const res  = await fetch(`{{ route('pedidos.cliente') }}?telefono=${limpio}`);
            const data = await res.json();

            this.conocido = data.encontrado ? data : null;

            if (this.conocido && !this.nombre) this.usarDatos();
        },

        usarDatos() {
            if (!this.conocido) return;
            this.nombre  = this.conocido.nombre ?? '';
            this.calle   = this.conocido.calle ?? '';
            this.detalle = this.conocido.detalle ?? '';
            this.zona    = this.conocido.zona_id ?? '';
        },

        visibles() { return this.carta.find(c => c.id === this.categoria)?.productos ?? []; },
        cantidadDe(p) { return this.lineas.filter(l => l.product_id === p.id).reduce((n, l) => n + l.qty, 0); },

        elegir(p) {
            if (p.variantes.length) { this.variantes = p; return; }
            this.sumar({ product_id: p.id, variant_id: null, nombre: p.nombre, precio: p.precio, cocina: p.cocina });
        },

        agregarVariante(p, v) {
            this.sumar({ product_id: p.id, variant_id: v.id,
                nombre: `${p.nombre} ${v.nombre}`, precio: v.precio, cocina: p.cocina });
            this.variantes = null;
        },

        sumar(base) {
            const igual = this.lineas.find(l =>
                l.product_id === base.product_id && l.variant_id === base.variant_id && !l.cocina);
            if (igual) { igual.qty++; return; }
            this.lineas.push({ ...base, qty: 1, notes: '' });
        },

        subtotal() { return this.lineas.reduce((n, l) => n + l.precio * l.qty, 0); },
        envio()    { return this.tipo === 'delivery' ? (this.envios[this.zona] ?? 0) : 0; },
        total()    { return this.subtotal() + this.envio(); },

        serializar() {
            const caja = this.$refs.hidden;
            caja.innerHTML = '';

            this.lineas.forEach((l, i) => {
                const campos = {
                    product_id: l.product_id, variant_id: l.variant_id ?? '',
                    qty: l.qty, notes: l.notes ?? '',
                };
                for (const [k, v] of Object.entries(campos)) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `lineas[${i}][${k}]`;
                    input.value = v;
                    caja.appendChild(input);
                }
            });
        },
    };
}
</script>
@endsection
