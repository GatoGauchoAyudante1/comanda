@extends('layouts.app')

@php use App\Support\Negocio; @endphp

@section('titulo', 'Ajustes')

@section('topbar')
    <div>
        <h1>Ajustes</h1>
        <div class="sub">Configuración del negocio, tarifas, zonas y usuarios</div>
    </div>
@endsection

@section('contenido')
<div x-data="{ tarifa: null, zona: null, usuario: null, mesas: false }">

    <div class="cat-split">

        {{-- ============ navegación ============ --}}
        <div class="card card-tight hide-mobile" style="position:sticky;top:0">
            <div class="sec" style="padding:6px 10px 8px;margin:0">Secciones</div>
            <a class="cat" href="#negocio">Datos del negocio</a>
            <a class="cat" href="#modulos">Módulos</a>
            <a class="cat" href="#cocina">Cocina</a>
            <a class="cat" href="#carta">Carta</a>
            <a class="cat" href="#ticket">Ticket</a>
            @if (Negocio::modulo('salon') || Negocio::modulo('pool'))
                <a class="cat" href="#mesas">Mesas</a>
            @endif
            @if (Negocio::modulo('pool'))
                <a class="cat" href="#tarifas">Tarifas de pool</a>
            @endif
            @if (Negocio::modulo('delivery'))
                <a class="cat" href="#zonas">Zonas de envío</a>
            @endif
            <a class="cat" href="#usuarios">Usuarios</a>
        </div>

        <div>

            {{-- ============ datos del negocio ============ --}}
            <div class="card" id="negocio">
                <div class="sec">Datos del negocio</div>
                <form method="POST" action="{{ route('configuracion.negocio') }}">
                    @csrf
                    <div class="grid2">
                        <div class="field">
                            <label for="nombre">Nombre</label>
                            <input id="nombre" class="inp" name="nombre" required maxlength="60"
                                   value="{{ Negocio::nombre() }}">
                            <span class="fs13 t-mute">Es el nombre del negocio: aparece en el encabezado del ticket.</span>
                        </div>
                        <div class="field">
                            <label for="punto_venta">Punto de venta</label>
                            <input id="punto_venta" class="inp" name="punto_venta" required maxlength="8"
                                   value="{{ Negocio::puntoDeVenta() }}">
                            <span class="fs13 t-mute">Prefijo del comprobante: {{ Negocio::puntoDeVenta() }}-00042</span>
                        </div>
                    </div>
                    <button class="btn btn-primary mt16" type="submit">Guardar</button>
                </form>

                <div class="notice mt16">
                    <span class="dot dot-mute"></span>
                    <div class="ds">
                        Si cambiás el nombre, regenerá el ícono de la app con
                        <b class="t-white">php artisan negocio:iconos</b>.
                    </div>
                </div>
            </div>

            {{-- ============ módulos ============ --}}
            <div class="card mt16" id="modulos">
                <div class="sec">
                    Módulos
                    <span class="meta">Qué partes del sistema se muestran</span>
                </div>

                <p class="t-dim fs14 mb16">
                    Es lo que diferencia un bar con pool de un negocio de delivery.
                    El valor inicial viene del <b class="t-white">.env</b>; si lo cambiás acá,
                    manda lo que elijas hasta que lo restablezcas.
                </p>

                @foreach ($modulos as $clave => [$nombre, $detalle])
                    @php
                        $activo       = Negocio::modulo($clave);
                        $personalizado = Negocio::moduloEsPersonalizado($clave);
                        $env          = Negocio::moduloSegunEnv($clave);
                    @endphp

                    <div class="row">
                        <div class="grow">
                            <div class="nm">{{ $nombre }}</div>
                            <div class="sb">
                                {{ $detalle }}
                                @if ($personalizado)
                                    · <span class="t-amber">cambiado acá</span>
                                    <span class="t-mute">(el .env dice {{ $env ? 'activado' : 'desactivado' }})</span>
                                @else
                                    · <span class="t-mute">según el .env</span>
                                @endif
                            </div>
                        </div>

                        @if ($personalizado)
                            <form method="POST" action="{{ route('configuracion.modulo.restablecer') }}">
                                @csrf
                                <input type="hidden" name="modulo" value="{{ $clave }}">
                                <button class="btn btn-sm" type="submit" title="Volver al valor del .env">
                                    Usar el .env
                                </button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('configuracion.modulo') }}">
                            @csrf
                            <input type="hidden" name="modulo" value="{{ $clave }}">
                            <button type="submit" class="sw {{ $activo ? 'is-on' : '' }}"
                                    style="border:none;cursor:pointer"></button>
                        </form>
                    </div>
                @endforeach

                <div class="notice notice-amber mt16">
                    <span class="dot dot-amber"></span>
                    <div>
                        <div class="tt">Apagar un módulo no borra nada</div>
                        <div class="ds">
                            Deja de mostrarse, pero las mesas, pedidos y datos siguen guardados.
                            Si lo volvés a prender, aparece todo igual que antes.
                        </div>
                    </div>
                </div>
            </div>

            {{-- ============ cocina ============ --}}
            <div class="card mt16" id="cocina">
                <div class="sec">
                    Cocina
                    <span class="meta">Quién puede marcar comandas como listas</span>
                </div>

                <p class="t-dim fs14 mb16">
                    Marcar «listo» saca el plato del tablero y lo habilita a salir.
                    Si alguien lo toca de más, el pedido se despacha sin estar hecho.
                    Por eso, de fábrica <b class="t-white">sólo cocina puede</b>.
                </p>

                <form method="POST" action="{{ route('configuracion.cocina') }}">
                    @csrf

                    <div class="half mb12" style="opacity:.6">
                        <span class="sw is-on" style="flex:none"></span>
                        <div class="grow">
                            <div class="fw6">Cocina</div>
                            <div class="fs13 t-mute">Siempre puede. No se puede quitar.</div>
                        </div>
                    </div>

                    @foreach ($rolesCocina as $clave => [$nombre, $detalle])
                        <label class="half mb12" style="cursor:pointer">
                            <input type="checkbox" name="roles[]" value="{{ $clave }}"
                                   @checked(in_array($clave, $marcanListo, true))
                                   style="width:18px;height:18px;accent-color:var(--green)">
                            <div class="grow">
                                <div class="fw6">{{ $nombre }}</div>
                                <div class="fs13 t-mute">{{ $detalle }}</div>
                            </div>
                        </label>
                    @endforeach

                    <button class="btn btn-primary mt12" type="submit">Guardar</button>
                </form>

                <div class="notice mt16">
                    <span class="dot dot-mute"></span>
                    <div class="ds">
                        Ver la pantalla de cocina y marcar listo son permisos distintos.
                        Los que no pueden marcar igual la ven, para saber cómo viene el turno.
                    </div>
                </div>
            </div>

            {{-- ============ carta ============ --}}
            <div class="card mt16" id="carta">
                <div class="sec">
                    Carta
                    <span class="meta">La carta que ven los clientes</span>
                </div>

                <p class="t-dim fs14 mb16">
                    Publicar la carta le da a cualquiera un link para verla desde el celular,
                    <b class="t-white">sin usuario ni contraseña</b>. Muestra sólo los productos
                    activos con su precio y su foto: nunca costos, márgenes, recetas ni stock.
                    Los cambios de precio se ven al instante, no hay nada que volver a publicar.
                </p>

                <div class="row">
                    <div class="grow">
                        <div class="nm">Carta pública</div>
                        <div class="sb">
                            @if ($cartaPublica)
                                <span class="t-green">publicada</span> · cualquiera con el link la ve
                            @else
                                <span class="t-mute">apagada</span> · el link devuelve «no existe»
                            @endif
                        </div>
                    </div>

                    <form method="POST" action="{{ route('configuracion.carta.publicar') }}">
                        @csrf
                        <button type="submit" class="sw {{ $cartaPublica ? 'is-on' : '' }}"
                                style="border:none;cursor:pointer"></button>
                    </form>
                </div>

                @if ($cartaPublica)

                    <div class="flex g18 wrap mt16" style="align-items:flex-start">

                        <div class="grow" style="min-width:260px">
                            <div class="opt-lbl">Link para los clientes</div>

                            <div class="flex g8 wrap mt4">
                                <a class="chip chip-green" href="{{ $cartaUrl }}" target="_blank" rel="noopener">
                                    {{ $cartaUrl }}
                                </a>
                                {{-- Se comparte por WhatsApp mucho más de lo que se escanea. --}}
                                <button class="btn btn-sm" type="button"
                                        @click="navigator.clipboard.writeText('{{ $cartaUrl }}');
                                                $el.textContent = 'Copiado'">
                                    Copiar
                                </button>
                            </div>

                            <form class="mt16" method="POST" action="{{ route('configuracion.carta') }}">
                                @csrf
                                <div class="field">
                                    <label for="carta-mensaje">Mensaje bajo el título</label>
                                    <input id="carta-mensaje" class="inp" name="mensaje" maxlength="160"
                                           value="{{ $cartaMensaje }}"
                                           placeholder="Todos los días de 19 a 1 · Pedidos al 11 2345-6789">
                                    <span class="fs13 t-mute">Opcional. Horarios, teléfono o lo que quieras que lea el cliente.</span>
                                </div>
                                <button class="btn btn-primary mt12" type="submit">Guardar</button>
                            </form>
                        </div>

                        {{-- El QR chico es para reconocerlo de un vistazo; el que se
                             pega en la mesa sale de «Imprimir», en su propia hoja. --}}
                        <div style="text-align:center">
                            <div class="opt-lbl">Código QR</div>
                            <div style="background:#fff;padding:10px;border-radius:var(--r-sm);line-height:0;display:inline-block">
                                {!! $cartaQr !!}
                            </div>
                            <a class="btn btn-block btn-sm mt12" href="{{ route('configuracion.carta.qr') }}" target="_blank">
                                Imprimir para la mesa
                            </a>
                        </div>
                    </div>

                    <div class="notice notice-amber mt16">
                        <span class="dot dot-amber"></span>
                        <div>
                            <div class="tt">Los precios quedan a la vista de todos</div>
                            <div class="ds">
                                Incluida la competencia. Un producto que no querés mostrar,
                                desactivalo en la <a class="t-green" href="{{ route('carta') }}">carta</a>:
                                sale del link público y del sistema a la vez.
                            </div>
                        </div>
                    </div>

                @else

                    <div class="notice mt16">
                        <span class="dot dot-mute"></span>
                        <div class="ds">
                            Al prenderla queda publicada en <b class="t-white">{{ $cartaUrl }}</b>
                            y aparece el código QR para imprimir y pegar en las mesas.
                        </div>
                    </div>

                @endif
            </div>

            {{-- ============ ticket ============ --}}
            <div class="card mt16" id="ticket">
                <div class="sec">
                    Ticket
                    <span class="meta">Qué se lee en el comprobante que se entrega</span>
                </div>

                <p class="t-dim fs14 mb16">
                    De fábrica el ticket sale con el <b class="t-white">mismo detalle que la comanda</b>:
                    cada consumo con su importe. Prendiendo esto, al cobrar aparece la opción de
                    reemplazar esa lista por un texto —«Almuerzo», «Consumos mesa»— antes de imprimir.
                    Lo piden los clientes que rinden gastos: necesitan el comprobante,
                    pero no que en la empresa se lea qué consumieron.
                </p>

                <div class="row">
                    <div class="grow">
                        <div class="nm">Poder cambiar el detalle antes de imprimir</div>
                        <div class="sb">
                            @if ($ticketDetalle)
                                <span class="t-green">activado</span> · el cajero elige el texto en el ticket
                            @else
                                <span class="t-mute">apagado</span> · el ticket sale siempre con el detalle completo
                            @endif
                        </div>
                    </div>

                    <form method="POST" action="{{ route('configuracion.ticket') }}">
                        @csrf
                        <button type="submit" class="sw {{ $ticketDetalle ? 'is-on' : '' }}"
                                style="border:none;cursor:pointer"></button>
                    </form>
                </div>

                @if ($ticketDetalle)

                    <form class="mt16" method="POST" action="{{ route('configuracion.ticket.textos') }}">
                        @csrf
                        <div class="field">
                            <label for="ticket-textos">Textos frecuentes</label>
                            <textarea id="ticket-textos" class="inp" name="textos" rows="5"
                                      style="resize:vertical;font-family:inherit"
                                      placeholder="Consumos mesa&#10;Almuerzo&#10;Cena">{{ implode(PHP_EOL, $ticketPlantillas) }}</textarea>
                            <span class="fs13 t-mute">
                                Uno por línea, hasta diez. Aparecen como botones en el ticket para
                                elegirlos de un toque; el cajero igual puede escribir otro.
                                Si los borrás todos, lo escribe siempre a mano.
                            </span>
                        </div>
                        <button class="btn btn-primary mt12" type="submit">Guardar</button>
                    </form>

                    <div class="notice notice-amber mt16">
                        <span class="dot dot-amber"></span>
                        <div>
                            <div class="tt">El importe no se toca</div>
                            <div class="ds">
                                Cambia lo que se lee en el papel, nada más: el total, la forma de pago,
                                el consumo cargado, la caja y los reportes quedan igual. Cada ticket
                                que sale con otro detalle queda en el
                                <a class="t-green" href="{{ route('historial') }}">historial</a>,
                                con quién lo hizo y a qué hora.
                            </div>
                        </div>
                    </div>

                @else

                    <div class="notice mt16">
                        <span class="dot dot-mute"></span>
                        <div class="ds">
                            Apagado no cambia nada de lo que ya funciona: al cobrar, el ticket
                            se imprime solo, igual que hoy.
                        </div>
                    </div>

                @endif
            </div>

            {{-- ============ mesas ============ --}}
            @if (Negocio::modulo('salon') || Negocio::modulo('pool'))
                <div class="card mt16" id="mesas">
                    <div class="sec">
                        Mesas
                        <span class="meta">{{ $mesas->flatten()->where('active', true)->count() }} activas</span>
                    </div>

                    @foreach (['pool' => 'Mesas de pool', 'salon' => 'Mesas de salón'] as $tipo => $titulo)
                        @if (Negocio::modulo($tipo === 'pool' ? 'pool' : 'salon'))
                            <div class="opt-lbl {{ $loop->first ? '' : 'mt16' }}">{{ $titulo }}</div>

                            <div class="flex g8 wrap mb12">
                                @forelse ($mesas->get($tipo, collect()) as $m)
                                    <form method="POST" action="{{ route('configuracion.mesa.alternar', $m) }}">
                                        @csrf
                                        <button type="submit"
                                                class="chip chip-lg {{ $m->active ? 'chip-green' : 'chip-line' }}"
                                                style="cursor:pointer;border:none"
                                                title="{{ $m->active ? 'Desactivar' : 'Activar' }}">
                                            {{ $m->name }}
                                        </button>
                                    </form>
                                @empty
                                    <span class="t-mute fs14">Todavía no hay.</span>
                                @endforelse
                            </div>
                        @endif
                    @endforeach

                    <button class="btn btn-dashed btn-block mt12" @click="mesas = true">+ Agregar mesas</button>

                    <div class="notice mt16">
                        <span class="dot dot-mute"></span>
                        <div class="ds">
                            Tocá una mesa para activarla o desactivarla. Desactivarla la saca del
                            panel pero conserva todo su historial.
                        </div>
                    </div>
                </div>
            @endif

            {{-- ============ tarifas de pool ============ --}}
            @if (Negocio::modulo('pool'))
                <div class="card mt16" id="tarifas">
                    <div class="sec">
                        Tarifas de pool
                        <span class="meta">{{ $tarifas->count() }} cargadas</span>
                    </div>

                    @foreach ($tarifas as $t)
                        @php
                            $json = json_encode([
                                'id' => $t->id, 'name' => $t->name,
                                'price_per_hour' => $t->price_per_hour / 100,
                                'rounding_minutes' => $t->rounding_minutes,
                                'is_default' => (bool) $t->is_default,
                            ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
                        @endphp
                        <div class="row">
                            <div class="grow">
                                <button type="button" class="link-editar" @click="tarifa = {{ $json }}">
                                    {{ $t->name }}
                                </button>
                                <div class="sb">
                                    Fracción de {{ $t->rounding_minutes }} min
                                    @if ($t->is_default) · <span class="t-green">por defecto</span> @endif
                                </div>
                            </div>
                            <span class="pr">@plata($t->price_per_hour) <span class="fs13 t-mute">/hora</span></span>
                        </div>
                    @endforeach

                    <button class="btn btn-dashed btn-block mt16"
                            @click="tarifa = { id: null, name: '', price_per_hour: '', rounding_minutes: 30, is_default: false }">
                        + Nueva tarifa
                    </button>
                </div>
            @endif

            {{-- ============ zonas ============ --}}
            @if (Negocio::modulo('delivery'))
                <div class="card mt16" id="zonas">
                    <div class="sec">
                        Zonas de envío
                        <span class="meta">{{ $zonas->count() }} cargadas</span>
                    </div>

                    @foreach ($zonas as $z)
                        @php
                            $json = json_encode(['id' => $z->id, 'name' => $z->name,
                                'delivery_fee' => $z->delivery_fee / 100],
                                JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
                        @endphp
                        <div class="row">
                            <div class="grow">
                                <button type="button" class="link-editar" @click="zona = {{ $json }}">
                                    {{ $z->name }}
                                </button>
                            </div>
                            <span class="pr">@plata($z->delivery_fee)</span>
                        </div>
                    @endforeach

                    <button class="btn btn-dashed btn-block mt16"
                            @click="zona = { id: null, name: '', delivery_fee: '' }">
                        + Nueva zona
                    </button>
                </div>
            @endif

            {{-- ============ usuarios ============ --}}
            <div class="card mt16" id="usuarios">
                <div class="sec">
                    Usuarios
                    <span class="meta">{{ $usuarios->where('active', true)->count() }} activos</span>
                </div>

                @foreach ($usuarios as $u)
                    @php
                        $json = json_encode(['id' => $u->id, 'name' => $u->name,
                            'email' => $u->email, 'role' => $u->role,
                            'precios' => (bool) $u->can_edit_prices,
                            'borrable' => (bool) $u->borrable, 'yo' => $u->id === auth()->id()],
                            JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
                    @endphp
                    <div class="row" @class(['off' => ! $u->active])>
                        <div class="grow">
                            <button type="button" class="link-editar" @click="usuario = {{ $json }}">
                                {{ $u->name }}
                            </button>
                            <div class="sb">
                                {{ $u->email }}
                                @unless ($u->active) · <span class="t-amber">sin acceso</span> @endunless
                            </div>
                        </div>
                        {{-- El permiso de precios se ve de un vistazo: es el único
                             que se delega fuera del rol (R-39). --}}
                        @if ($u->can_edit_prices && ! $u->esDueno())
                            <span class="chip chip-amber" title="Puede cambiar precios de la carta">Precios</span>
                        @endif
                        <span class="chip chip-line">{{ ucfirst($u->role) }}</span>
                        <form method="POST" action="{{ route('configuracion.usuario.alternar', $u) }}">
                            @csrf
                            <button type="submit" class="sw {{ $u->active ? 'is-on' : '' }}"
                                    style="border:none;cursor:pointer"
                                    title="{{ $u->active ? 'Quitarle el acceso' : 'Devolverle el acceso' }}"></button>
                        </form>
                    </div>
                @endforeach

                <button class="btn btn-dashed btn-block mt16"
                        @click="usuario = { id: null, name: '', email: '', role: 'mozo', precios: false, borrable: false, yo: false }">
                    + Nuevo usuario
                </button>

                <div class="notice mt16">
                    <span class="dot dot-mute"></span>
                    <div>
                        <div class="tt">Quitar el acceso no es lo mismo que borrar</div>
                        <div class="ds">
                            El interruptor <b class="t-white">le corta el acceso al instante</b>, incluso
                            si está usando el sistema en ese momento, y conserva todo su historial.
                            Es lo que corresponde cuando alguien deja de trabajar.
                            Borrar sólo se puede si nunca operó.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================
         DIÁLOGOS
         ============================================================ --}}

    <div class="overlay" x-show="tarifa" x-cloak @click.self="tarifa = null" @keydown.escape.window="tarifa = null">
        <form class="modal" style="max-width:480px" method="POST"
              :action="tarifa?.id
                  ? '{{ route('configuracion.tarifa.actualizar', ['tarifa' => '__ID__']) }}'.replace('__ID__', tarifa.id)
                  : '{{ route('configuracion.tarifa') }}'">
            @csrf
            <div class="modal-hd">
                <h2 class="grow" x-text="tarifa?.id ? 'Editar tarifa' : 'Nueva tarifa'"></h2>
                <button class="xbtn" type="button" @click="tarifa = null">&times;</button>
            </div>
            <div class="modal-bd">
                <div class="field mb16">
                    <label for="t-name">Nombre</label>
                    <input id="t-name" class="inp" name="name" x-model="tarifa.name" required maxlength="60"
                           placeholder="Hora normal, Happy hour…">
                </div>
                <div class="grid2">
                    <div class="field">
                        <label for="t-precio">Precio por hora</label>
                        <input id="t-precio" class="inp" type="number" step="1" min="0"
                               name="price_per_hour" x-model="tarifa.price_per_hour" required>
                    </div>
                    <div class="field">
                        <label for="t-frac">Fracción</label>
                        <select id="t-frac" class="inp" name="rounding_minutes" x-model.number="tarifa.rounding_minutes">
                            <option value="30">Media hora</option>
                            <option value="15">15 minutos</option>
                            <option value="10">10 minutos</option>
                            <option value="60">Hora completa</option>
                            <option value="1">Al minuto</option>
                        </select>
                    </div>
                </div>
                <label class="half mt16" style="cursor:pointer">
                    <input type="checkbox" name="is_default" value="1" x-model="tarifa.is_default"
                           style="width:18px;height:18px;accent-color:var(--green)">
                    <div class="grow">
                        <div class="fw6">Usar por defecto</div>
                        <div class="fs13 t-mute">Viene preseleccionada al abrir una mesa.</div>
                    </div>
                </label>
                <div class="notice mt16">
                    <span class="dot dot-mute"></span>
                    <div class="ds">
                        Cambiar una tarifa no afecta a las mesas ya abiertas: cada una
                        guarda la suya al abrirse.
                    </div>
                </div>
            </div>
            <div class="modal-ft">
                <div class="grow"></div>
                <button class="btn" type="button" @click="tarifa = null">Cancelar</button>
                <button class="btn btn-primary" type="submit">Guardar</button>
            </div>
        </form>
    </div>

    <div class="overlay" x-show="mesas" x-cloak @click.self="mesas = false" @keydown.escape.window="mesas = false">
        <form class="modal" style="max-width:480px" method="POST" action="{{ route('configuracion.mesas') }}">
            @csrf
            <div class="modal-hd">
                <div class="grow">
                    <h2>Agregar mesas</h2>
                    <div class="sub">Se crean numeradas, en tanda</div>
                </div>
                <button class="xbtn" type="button" @click="mesas = false">&times;</button>
            </div>
            <div class="modal-bd">
                <div class="modal-sec grid2">
                    <div class="field">
                        <label for="m-tipo">Tipo</label>
                        <select id="m-tipo" class="inp" name="type">
                            @if (Negocio::modulo('pool'))
                                <option value="pool">Mesa de pool</option>
                            @endif
                            @if (Negocio::modulo('salon'))
                                <option value="salon">Mesa de salón</option>
                            @endif
                        </select>
                    </div>
                    <div class="field">
                        <label for="m-prefijo">Se llaman</label>
                        <input id="m-prefijo" class="inp" name="prefijo" required maxlength="20"
                               value="Pool" placeholder="Pool, Mesa, Barra…">
                    </div>
                </div>

                <div class="modal-sec grid2">
                    <div class="field">
                        <label for="m-desde">Desde el número</label>
                        <input id="m-desde" class="inp" type="number" min="1" max="200" name="desde" value="1" required>
                    </div>
                    <div class="field">
                        <label for="m-hasta">Hasta el número</label>
                        <input id="m-hasta" class="inp" type="number" min="1" max="200" name="hasta" value="8" required>
                    </div>
                </div>

                <div class="notice mt16">
                    <span class="dot dot-mute"></span>
                    <div class="ds">
                        Con «Pool» del 1 al 8 se crean Pool 1, Pool 2… Pool 8.
                        Las que ya existan se saltean.
                    </div>
                </div>
            </div>
            <div class="modal-ft">
                <div class="grow"></div>
                <button class="btn" type="button" @click="mesas = false">Cancelar</button>
                <button class="btn btn-primary" type="submit">Crear</button>
            </div>
        </form>
    </div>

    <div class="overlay" x-show="zona" x-cloak @click.self="zona = null" @keydown.escape.window="zona = null">
        <form class="modal" style="max-width:440px" method="POST"
              :action="zona?.id
                  ? '{{ route('configuracion.zona.actualizar', ['zona' => '__ID__']) }}'.replace('__ID__', zona.id)
                  : '{{ route('configuracion.zona') }}'">
            @csrf
            <div class="modal-hd">
                <h2 class="grow" x-text="zona?.id ? 'Editar zona' : 'Nueva zona'"></h2>
                <button class="xbtn" type="button" @click="zona = null">&times;</button>
            </div>
            <div class="modal-bd">
                <div class="field mb16">
                    <label for="z-name">Nombre</label>
                    <input id="z-name" class="inp" name="name" x-model="zona.name" required maxlength="60"
                           placeholder="Centro, Barrio Norte…">
                </div>
                <div class="field">
                    <label for="z-fee">Costo de envío</label>
                    <input id="z-fee" class="inp" type="number" step="1" min="0"
                           name="delivery_fee" x-model="zona.delivery_fee" required>
                </div>
            </div>
            <div class="modal-ft">
                <div class="grow"></div>
                <button class="btn" type="button" @click="zona = null">Cancelar</button>
                <button class="btn btn-primary" type="submit">Guardar</button>
            </div>
        </form>
    </div>

    <div class="overlay" x-show="usuario" x-cloak @click.self="usuario = null" @keydown.escape.window="usuario = null">
        <form class="modal" style="max-width:480px" method="POST"
              :action="usuario?.id
                  ? '{{ route('configuracion.usuario.actualizar', ['usuario' => '__ID__']) }}'.replace('__ID__', usuario.id)
                  : '{{ route('configuracion.usuario') }}'">
            @csrf
            <div class="modal-hd">
                <h2 class="grow" x-text="usuario?.id ? 'Editar usuario' : 'Nuevo usuario'"></h2>
                <button class="xbtn" type="button" @click="usuario = null">&times;</button>
            </div>
            <div class="modal-bd">
                <div class="grid2 mb16">
                    <div class="field">
                        <label for="u-name">Nombre</label>
                        <input id="u-name" class="inp" name="name" x-model="usuario.name" required maxlength="60">
                    </div>
                    <div class="field">
                        <label for="u-role">Rol</label>
                        <select id="u-role" class="inp" name="role" x-model="usuario.role">
                            @foreach ($roles as $rol)
                                <option value="{{ $rol }}">{{ ucfirst($rol) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="field mb16">
                    <label for="u-email">Usuario (correo)</label>
                    <input id="u-email" class="inp" type="email" name="email" x-model="usuario.email" required>
                </div>
                <div class="field">
                    <label for="u-pass">Clave</label>
                    <input id="u-pass" class="inp" type="password" name="password" minlength="8"
                           :required="!usuario?.id"
                           :placeholder="usuario?.id ? 'Dejala vacía para no cambiarla' : 'Mínimo 8 caracteres'">
                </div>

                {{-- Permiso delegado, no rol (R-39). Al dueño no se le ofrece:
                     puede siempre, y una casilla apagada mentiría. --}}
                <template x-if="usuario?.role !== 'dueno'">
                    <label class="half mt16" style="cursor:pointer">
                        <input type="checkbox" name="can_edit_prices" value="1" x-model="usuario.precios"
                               style="width:18px;height:18px;accent-color:var(--green)">
                        <div class="grow">
                            <div class="fw6">Puede cambiar precios</div>
                            <div class="fs13 t-mute">
                                Entra a la Carta y edita precios, de a uno o en lote.
                                No ve costos ni márgenes, y no puede crear ni renombrar productos.
                            </div>
                        </div>
                    </label>
                </template>

                <template x-if="usuario?.role === 'dueno'">
                    <div class="notice mt16">
                        <span class="dot dot-mute"></span>
                        <div class="ds">El dueño puede cambiar precios siempre.</div>
                    </div>
                </template>
            </div>
            <div class="modal-ft">
                {{-- Borrar sólo aparece si el usuario nunca operó (R-37). --}}
                <div class="grow">
                    <template x-if="usuario?.id && usuario?.borrable && !usuario?.yo">
                        <button class="btn btn-danger" type="submit" form="borrar-usuario"
                                @click="return confirm('Se borra definitivamente. ¿Seguís?')">
                            Borrar
                        </button>
                    </template>
                    <template x-if="usuario?.id && !usuario?.borrable">
                        <span class="fs13 t-mute">Ya operó: sólo se le puede quitar el acceso.</span>
                    </template>
                </div>
                <button class="btn" type="button" @click="usuario = null">Cancelar</button>
                <button class="btn btn-primary" type="submit">Guardar</button>
            </div>
        </form>
    </div>

    {{-- Formulario aparte: no puede anidarse dentro del de edición. --}}
    <form id="borrar-usuario" method="POST" x-show="false"
          :action="usuario?.id
              ? '{{ route('configuracion.usuario.eliminar', ['usuario' => '__ID__']) }}'.replace('__ID__', usuario.id)
              : ''">
        @csrf
        @method('DELETE')
    </form>
</div>
@endsection
