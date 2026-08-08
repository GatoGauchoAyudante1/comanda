<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('partials.tema-script')

    {{-- El título de la pestaña lleva el nombre del sistema; el del negocio va en el ticket. --}}
    <title>@yield('titulo', 'Atención') · {{ config('app.name') }}</title>

    {{-- PWA: se instala en el celular con ícono propio. Sin caché (D-05). --}}
    <link rel="manifest" href="{{ route('pwa.manifest') }}">
    <meta name="theme-color" content="#070908">
    <meta name="mobile-web-app-capable" content="yes">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>

<div class="app">

    <x-rail />

    {{--
      El alcance de Alpine abarca la barra superior Y el contenido.

      Si cada pantalla declarara su propio `x-data` adentro de @section('contenido'),
      los botones de la barra superior quedarían fuera del alcance y no harían
      nada al tocarlos. Las pantallas declaran su estado con:

          @section('alpine', 'carta()')
    --}}
    <div class="main" x-data="@yield('alpine', '{}')">

        <header class="topbar">
            @hasSection('topbar')
                @yield('topbar')
            @else
                <div>
                    <h1>@yield('titulo', 'Atención')</h1>
                    @hasSection('subtitulo')
                        <div class="sub">@yield('subtitulo')</div>
                    @endif
                </div>
            @endif

            <x-ayuda />
            <x-tema />
        </header>

        <div class="content">

            @if (session('ok') || session('error') || $errors->any())
                <div class="notice mb16 {{ session('ok') ? 'notice-green' : 'notice-amber' }}"
                     x-data x-init="setTimeout(() => $el.remove(), 6000)">
                    <span class="dot {{ session('ok') ? '' : 'dot-amber' }}"></span>
                    <div class="ds t-white">
                        {{ session('ok') ?? session('error') ?? $errors->first() }}
                    </div>
                </div>
            @endif

            @yield('contenido')
            {{ $slot ?? '' }}
        </div>

    </div>
</div>

{{-- Fuera de `.main`: es un diálogo fijo y no tiene que quedar dentro del
     x-data que declara cada pantalla. Ver partials/ayuda.blade.php. --}}
@include('partials.ayuda')

@livewireScripts

<script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('{{ route('pwa.sw') }}').catch(() => {});
    }
</script>
</body>
</html>
