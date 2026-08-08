<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">

    @include('partials.tema-script')

    <title>Cocina · {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* Va en una tablet colgada en la pared: sin barra lateral y todo grande. */
        .kds-top{
            display:flex;align-items:center;gap:20px;
            padding:18px 26px;border-bottom:1px solid var(--line);flex:none;
        }
        .kds-top .ttl{font-size:22px;font-weight:700;letter-spacing:.16em}
        @media (max-width:900px){.kds-top{padding:16px 18px}.kds-top .ttl{font-size:18px}}
    </style>
</head>
<body>

{{-- Refresco cada 10 s sólo con la pestaña visible. Ver docs/02-decisiones.md · D-18. --}}
<div style="display:flex;flex-direction:column;height:100vh"
     x-data x-init="setInterval(() => { if (!document.hidden) location.reload() }, 10000)">

    <header class="kds-top">
        <span class="ttl">COCINA</span>
        <div class="grow hide-mobile" style="text-align:right">
            @if ($comandas->isNotEmpty())
                <span class="chip chip-amber chip-lg">
                    {{ $comandas->count() }} {{ $comandas->count() === 1 ? 'comanda pendiente' : 'comandas pendientes' }}
                </span>
            @endif
        </div>
        <x-ayuda />
        <x-tema />
        <span class="money m-md">{{ now()->format('H:i') }}</span>

        {{--
          Esta pantalla no tiene barra lateral: vive en una tablet colgada en
          la cocina y todo el espacio es para las comandas.

          Pero al cajero y al dueño que entran desde «Ver cocina» hay que darles
          una puerta de vuelta, o quedan atrapados y la única salida es cerrar
          la sesión.
        --}}
        @if (auth()->user()->rutaInicio() !== 'cocina')
            <a class="btn btn-sm" href="{{ route(auth()->user()->rutaInicio()) }}">&larr; Volver</a>
        @endif

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="btn btn-sm" type="submit">Salir</button>
        </form>
    </header>

    <div class="content" style="flex:1;overflow-y:auto">

        {{-- Esta vista es autónoma, no usa layouts.app: el aviso va acá. --}}
        @if (session('ok') || session('error'))
            <div class="notice mb16 {{ session('ok') ? 'notice-green' : 'notice-amber' }}"
                 x-data x-init="setTimeout(() => $el.remove(), 5000)">
                <span class="dot {{ session('ok') ? '' : 'dot-amber' }}"></span>
                <div class="ds t-white">{{ session('ok') ?? session('error') }}</div>
            </div>
        @endif

        @unless ($puedeMarcar)
            <div class="notice mb16">
                <span class="dot dot-mute"></span>
                <div>
                    <div class="tt">Estás mirando, no operando</div>
                    <div class="ds">
                        Tu rol puede ver cómo viene la cocina pero no marcar comandas listas.
                        El dueño configura quién puede en Ajustes → Cocina.
                    </div>
                </div>
            </div>
        @endunless

        @if ($comandas->isEmpty())
            <div class="col" style="align-items:center;justify-content:center;height:60vh;gap:12px">
                <div class="money m-xl t-green">Todo al día</div>
                <div class="t-dim">No hay comandas pendientes.</div>
            </div>
        @else
            <div class="kds">
                @foreach ($comandas as $orden)
                    @php
                        $minutos  = (int) $orden->created_at->diffInMinutes(now());
                        $urgencia = $minutos >= 30 ? 'late' : ($minutos >= 15 ? 'warn' : 'ok');

                        $origen = match ($orden->type) {
                            'mesa_pool', 'mesa_salon' => mb_strtoupper($orden->tableSession?->table->name ?? 'MESA'),
                            'delivery' => 'DELIVERY',
                            'retiro'   => 'RETIRA',
                            default    => 'MOSTRADOR',
                        };
                    @endphp

                    <div class="ticket {{ $urgencia }}">
                        <div class="strip"></div>

                        <div class="hd">
                            <span class="no">#{{ $orden->number }}</span>
                            <span class="chip chip-line">{{ $origen }}</span>
                            <span class="tm">{{ $minutos }} min</span>
                        </div>

                        <div class="bd">
                            @foreach ($orden->items as $item)
                                <div class="it">
                                    <span class="q">{{ $item->qty }}</span>{{ $item->product->name }}
                                    @if ($item->variant) {{ $item->variant->name }} @endif
                                    @if ($item->notes)
                                        <span class="nt">{{ $item->notes }}</span>
                                    @endif
                                </div>
                            @endforeach

                            @if ($orden->notes)
                                <div class="it"><span class="nt">{{ $orden->notes }}</span></div>
                            @endif
                        </div>

                        <div class="ft">
                            @if ($puedeMarcar)
                                <form method="POST" action="{{ route('cocina.listo', $orden) }}">
                                    @csrf
                                    <button class="btn btn-primary btn-lg btn-block" type="submit">LISTO</button>
                                </form>
                            @else
                                {{-- Ver la cocina y marcar listo son permisos distintos (R-36). --}}
                                <div class="btn btn-lg btn-block" style="opacity:.35;cursor:default">
                                    En preparación
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

{{-- Esta vista no usa layouts.app, así que el panel se incluye a mano. --}}
@include('partials.ayuda')

</body>
</html>
