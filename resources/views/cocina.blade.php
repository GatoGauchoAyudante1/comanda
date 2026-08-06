<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Cocina · {{ \App\Support\Negocio::nombre() }}</title>

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
        <span class="money m-md">{{ now()->format('H:i') }}</span>
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
                            <form method="POST" action="{{ route('cocina.listo', $orden) }}">
                                @csrf
                                <button class="btn btn-primary btn-lg btn-block" type="submit">LISTO</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

</body>
</html>
