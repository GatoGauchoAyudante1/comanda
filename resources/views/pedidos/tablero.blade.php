@extends('layouts.app')

@php
    $activos = collect($columnas)->sum(fn ($c) => $c['pedidos']->count());
@endphp

@section('titulo', 'Pedidos')
{{-- Sin comillas dobles: esto termina adentro del atributo x-data del layout. --}}
@section('alpine')
{
    asignando: null, detalle: null, pagando: null, entregando: null,

    {{-- Viaja en la URL para sobrevivir al refresco automático. --}}
    q: {{ Js::from((string) request('q', '')) }},

    plano(t) { return t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); },

    {{-- Todas las palabras tienen que estar, en cualquier orden:
         «juan san martin» encuentra a Juan en San Martín 1234. --}}
    coincide(texto) {
        const partes = this.plano(this.q).split(/\s+/).filter(Boolean);
        const heno   = this.plano(texto);
        return partes.every(p => heno.includes(p));
    },

    {{-- No se recarga solo: recargar sube la página y te saca del pedido que
         estabas mirando. Se pregunta si cambió algo y se avisa en el botón. --}}
    firma: {{ Js::from($firma) }},
    hayCambios: false,

    vigilar() {
        setInterval(async () => {
            if (document.hidden || this.hayCambios) return;
            try {
                const res = await fetch({{ Js::from(route('pedidos.firma')) }}, { headers: { Accept: 'application/json' } });
                if (res.ok) this.hayCambios = (await res.json()).firma !== this.firma;
            } catch (e) {}
        }, 15000);
    },

    buscar() {
        const url = new URL(location.href);
        this.q.trim() ? url.searchParams.set('q', this.q.trim()) : url.searchParams.delete('q');
        history.replaceState(null, '', url);
    },
}
@endsection

@section('topbar')
    <div>
        <h1>Pedidos</h1>
        <div class="sub">{{ $activos }} en curso · turno actual</div>
    </div>
    <div class="topbar-actions">
        <input class="inp" type="search" x-model="q" @input="buscar()"
               @keydown.escape="q = ''; buscar()"
               placeholder="Buscar: nombre, teléfono, dirección, #número"
               aria-label="Buscar pedido" autocomplete="off"
               style="width:min(320px, 46vw)">
        {{-- Recarga manteniendo la búsqueda, que ya está en la URL. --}}
        <button type="button" class="btn" :class="hayCambios && 'btn-primary'"
                @click="location.reload()"
                :title="hayCambios ? 'Hay pedidos nuevos o que cambiaron' : 'Volver a cargar los pedidos'">
            <span x-text="hayCambios ? '● Hay cambios · Actualizar' : 'Actualizar'">Actualizar</span>
        </button>
        <a class="btn hide-mobile" href="{{ route('cocina') }}">Ver cocina</a>
        @if (\App\Support\Negocio::modulo('delivery'))
            <a class="btn btn-primary" href="{{ route('pedidos.nuevo') }}">+ Nuevo pedido</a>
        @endif
    </div>
@endsection

@section('contenido')
{{-- Ver vigilar(): avisa que hay cambios, no recarga (D-18). --}}
<div x-init="vigilar()">

    <div class="kanban" style="grid-template-columns:repeat(3,1fr)">
        @foreach ($columnas as $clave => $columna)
            <div x-data="{ get visibles() { q; return [...$el.querySelectorAll('[data-buscar]')].filter(c => coincide(c.dataset.buscar)).length } }">
                <div class="kcol-hd">
                    <span class="t">{{ $columna['titulo'] }}</span>
                    <span class="badge" x-text="q.trim() ? visibles : {{ $columna['pedidos']->count() }}">{{ $columna['pedidos']->count() }}</span>
                </div>

                <div class="kcol">
                    @forelse ($columna['pedidos'] as $pedido)
                        @php
                            // Mismo reloj que ordena la columna: ver Order::esperaDesde().
                            $minutos  = (int) $pedido->esperaDesde($columna['estados'])->diffInMinutes(now());
                            $urgencia = $minutos >= 30 ? 'late' : ($minutos >= 15 ? 'warn' : 'ok');
                            $entrega  = $pedido->delivery;
                            $esMesa   = $pedido->esMesa() || $pedido->type === 'mostrador';
                            $proximo  = $esMesa ? null : $avanzar->siguiente($pedido);
                            // Marcar listo se pide igual que en cocina (R-36).
                            // Sin permiso el paso no se ofrece: el pedido queda
                            // esperando a que cocina lo saque.
                            if ($proximo === 'ready' && ! $puedeMarcarListo) {
                                $proximo = null;
                            }

                            // Lo que encuentra el buscador. El teléfono va también
                            // sólo con dígitos: «1123456789» encuentra «11 2345-6789».
                            $telefono = $entrega?->telefonoCliente();
                            $buscar   = implode(' ', array_filter([
                                '#' . $pedido->number,
                                $entrega?->nombreCliente(),
                                $telefono,
                                $telefono ? preg_replace('/\D/', '', $telefono) : null,
                                $entrega?->direccionCompleta(),
                                $entrega?->zone?->name,
                                $entrega?->driver?->name,
                                $pedido->tableSession?->table?->name,
                                ['delivery' => 'delivery', 'retiro' => 'retiro retira'][$pedido->type] ?? null,
                            ]));
                        @endphp

                        <div class="kcard {{ $urgencia }}" data-buscar="{{ $buscar }}" x-show="coincide($el.dataset.buscar)">

                            <div class="between">
                                <span class="fw6 fs17">#{{ $pedido->number }}</span>
                                <span class="fw6">@plata($pedido->total)</span>
                            </div>

                            {{-- De dónde viene y a dónde va --}}
                            <x-origen :orden="$pedido" class="mt8" />

                            @if ($entrega?->nombreCliente())
                                <div class="fs13 t-dim mt4">{{ $entrega->nombreCliente() }}</div>
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

                                        @if ($entrega?->direccionCompleta())
                                            <div class="card card-tight mb16">
                                                <div class="fw6">{{ $entrega->direccionCompleta() }}</div>
                                                <div class="fs13 t-mute mt4">
                                                    {{ $entrega->nombreCliente() }}
                                                    @if ($entrega->telefonoCliente()) · {{ $entrega->telefonoCliente() }} @endif
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

                    @if ($columna['pedidos']->isNotEmpty())
                        <div class="tcard tcard--free" style="min-height:90px" x-show="q.trim() && ! visibles" x-cloak>
                            <span class="fs13 t-mute">Nada coincide acá</span>
                        </div>
                    @endif
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
