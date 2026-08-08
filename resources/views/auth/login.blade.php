<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">

    @include('partials.tema-script')

    {{--
      Acá va el nombre del SISTEMA (APP_NAME), no el del negocio: todavía nadie
      entró, así que esta pantalla es la marca del producto. El nombre del
      cliente (Negocio::nombre) sale en el ticket. Ver docs/02-decisiones.md · D-02.
    --}}
    <title>Entrar · {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

{{-- El interruptor también acá: es donde se entra por primera vez en un equipo. --}}
<div style="position:fixed;top:20px;right:20px;z-index:10">
    <x-tema />
</div>

<div style="min-height:100vh;display:grid;place-items:center;padding:24px">
    <div style="width:100%;max-width:420px">

        <div class="col" style="align-items:center;gap:16px;margin-bottom:28px">
            <div class="brand" style="width:60px;height:60px;font-size:28px;margin:0">
                {{ mb_substr(config('app.name'), 0, 1) }}
            </div>
            <div style="text-align:center">
                <h1 style="font-size:26px;font-weight:600;letter-spacing:-.02em">{{ config('app.name') }}</h1>
                <div class="t-dim fs14 mt4">Entrá para empezar el turno</div>
            </div>
        </div>

        <form method="POST" action="{{ route('login.entrar') }}" class="card">
            @csrf

            @if ($errors->any() || session('error'))
                <div class="notice mb16" style="border-color:var(--red-line);background:var(--red-dim)">
                    <span class="dot dot-red"></span>
                    <div class="ds t-white">{{ $errors->first() ?: session('error') }}</div>
                </div>
            @endif

            <div class="field mb16">
                <label for="email">Usuario</label>
                <input id="email" class="inp" type="email" name="email"
                       value="{{ old('email') }}" required autofocus autocomplete="username"
                       placeholder="tuusuario@local.test">
            </div>

            <div class="field mb16">
                <label for="password">Clave</label>
                <input id="password" class="inp" type="password" name="password"
                       required autocomplete="current-password" placeholder="••••••••">
            </div>

            <label class="flex g10 fs14 t-dim" style="cursor:pointer">
                <input type="checkbox" name="recordarme" value="1" style="width:18px;height:18px;accent-color:var(--green)">
                No cerrar la sesión en esta computadora
            </label>

            <button class="btn btn-primary btn-lg btn-block mt20" type="submit">Entrar</button>
        </form>

        @if (app()->environment('local'))
            <div class="notice mt16">
                <span class="dot dot-mute"></span>
                <div>
                    <div class="tt fs14">Usuarios de prueba</div>
                    <div class="ds">
                        juan@local.test (dueño) · carla@local.test (cajero) ·
                        walter@local.test (mozo) · cocina@local.test · nico@local.test (repartidor)
                        <br>Clave para todos: <b class="t-white">clave1234</b>
                    </div>
                </div>
            </div>
        @endif

    </div>
</div>

</body>
</html>
