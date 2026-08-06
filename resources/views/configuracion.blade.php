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
<div x-data="{ tarifa: null, zona: null, usuario: null }">

    <div class="cat-split">

        {{-- ============ navegación ============ --}}
        <div class="card card-tight hide-mobile" style="position:sticky;top:0">
            <div class="sec" style="padding:6px 10px 8px;margin:0">Secciones</div>
            <a class="cat" href="#negocio">Datos del negocio</a>
            <a class="cat" href="#modulos">Módulos</a>
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
                            <span class="fs13 t-mute">Aparece en el ticket, el título y el ícono de la app.</span>
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
                            'email' => $u->email, 'role' => $u->role],
                            JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
                    @endphp
                    <div class="row" @class(['off' => ! $u->active])>
                        <div class="grow">
                            <button type="button" class="link-editar" @click="usuario = {{ $json }}">
                                {{ $u->name }}
                            </button>
                            <div class="sb">{{ $u->email }}</div>
                        </div>
                        <span class="chip chip-line">{{ ucfirst($u->role) }}</span>
                        <form method="POST" action="{{ route('configuracion.usuario.alternar', $u) }}">
                            @csrf
                            <button type="submit" class="sw {{ $u->active ? 'is-on' : '' }}"
                                    style="border:none;cursor:pointer"></button>
                        </form>
                    </div>
                @endforeach

                <button class="btn btn-dashed btn-block mt16"
                        @click="usuario = { id: null, name: '', email: '', role: 'mozo' }">
                    + Nuevo usuario
                </button>
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
            </div>
            <div class="modal-ft">
                <div class="grow"></div>
                <button class="btn" type="button" @click="usuario = null">Cancelar</button>
                <button class="btn btn-primary" type="submit">Guardar</button>
            </div>
        </form>
    </div>
</div>
@endsection
