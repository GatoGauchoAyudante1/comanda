@extends('dev-panel.layout')

@section('titulo', 'Acceso restringido')

@section('contenido')
    <div style="min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px">
        <div class="card" style="width:100%; max-width:360px; text-align:center">

            <div style="font-size:30px; line-height:1">🔒</div>

            <h1 style="margin:12px 0 2px; font-size:19px; font-weight:600">Acceso restringido</h1>
            <div style="color:var(--txt-2); font-size:13.5px">Ingresá la clave de desarrollador</div>

            @if (session('error'))
                <div class="notice notice-red" style="margin:16px 0 0; text-align:left">{{ session('error') }}</div>
            @endif

            <form method="POST" action="{{ route('dev-panel.login') }}" style="margin-top:18px">
                @csrf

                {{-- autocomplete off: no es la contraseña del usuario, no va al llavero del navegador. --}}
                <input type="password" name="key" autocomplete="off" autofocus
                       style="text-align:center; letter-spacing:.12em">

                <button type="submit" class="btn btn-primary" style="width:100%; margin-top:12px">Ingresar</button>
            </form>

        </div>
    </div>
@endsection
