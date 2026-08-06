@extends('layouts.app')

@section('titulo', 'Abrir caja')
@section('subtitulo', 'No hay ningún turno abierto')

@section('contenido')
    <div class="narrow">

        <div class="card">
            <div class="sec">Abrir el turno</div>
            <p class="t-dim fs14 mb16">
                Sin caja abierta no se pueden abrir mesas ni cobrar. Contá el efectivo con el que
                arrancás y cargalo como fondo inicial.
            </p>

            <form method="POST" action="{{ route('caja.abrir') }}">
                @csrf

                <div class="field mb16">
                    <label for="opening_float">Fondo inicial</label>
                    <input id="opening_float" class="inp inp-lg" type="number" step="1" min="0"
                           name="opening_float" value="{{ old('opening_float', 30000) }}"
                           required autofocus inputmode="numeric">
                    <span class="fs13 t-mute">En pesos. Es la plata que hay en la caja antes de empezar a vender.</span>
                </div>

                <div class="hr"></div>

                <div class="lv"><span class="k">Abre</span><span class="v">{{ auth()->user()->name }}</span></div>
                <div class="lv"><span class="k">Fecha y hora</span><span class="v">{{ now()->format('d/m/Y H:i') }}</span></div>

                <button class="btn btn-primary btn-lg btn-block mt20" type="submit">Abrir turno</button>
            </form>
        </div>

        <div class="notice mt16">
            <span class="dot dot-mute"></span>
            <div>
                <div class="tt">El turno define el día operativo</div>
                <div class="ds">
                    Un turno que arranca a las 19:00 y cierra a las 03:00 cuenta como un solo día.
                    La numeración de comprobantes sigue ese criterio.
                </div>
            </div>
        </div>

    </div>
@endsection
