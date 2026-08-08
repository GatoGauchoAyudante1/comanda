@php
    /*
     | El panel de ayuda. Se incluye una sola vez por página, al final del
     | <body>, y lo abre el botón <x-ayuda /> de la barra superior.
     |
     | Sin Alpine a propósito: el estado de Alpine lo declara cada pantalla en
     | @section('alpine'), así que un panel que viviera ahí adentro se rompería
     | en la primera pantalla que no lo declare. Además la cocina no usa este
     | layout. Un atributo `hidden` y tres líneas de JS delegado aguantan todo.
     |
     | El contenido sale de App\Support\Ayuda, ya filtrado por rol y por módulo.
     */
    $ayuda = \App\Support\Ayuda::para(auth()->user(), request()->route()?->getName());
@endphp

<div class="overlay" id="ayuda-panel" hidden data-ayuda-panel>
    <div class="modal ayuda" role="dialog" aria-modal="true" aria-labelledby="ayuda-titulo">

        <div class="modal-hd">
            <div class="grow">
                <h2 id="ayuda-titulo">Ayuda</h2>
                <div class="sub">Cómo se hace cada cosa, y dónde</div>
            </div>
            <button class="xbtn" type="button" data-ayuda-cerrar aria-label="Cerrar la ayuda">&times;</button>
        </div>

        <div class="modal-bd">

            <div class="ayuda-buscar">
                <input class="inp" type="search" data-ayuda-buscar
                       placeholder="Buscar: mozos, insumos, delivery, conteo…"
                       aria-label="Buscar en la ayuda" autocomplete="off">
            </div>

            {{-- Lo de la pantalla en la que está parado, primero y ya abierto:
                 es lo que vino a buscar nueve de cada diez veces. --}}
            @if ($ayuda['aqui'])
                <div class="ayuda-grupo" data-ayuda-grupo>
                    <div class="opt-lbl">En esta pantalla</div>
                    @foreach ($ayuda['aqui'] as $tema)
                        @include('partials.ayuda-tema', ['tema' => $tema, 'abierto' => $loop->first])
                    @endforeach
                </div>
            @endif

            @foreach ($ayuda['grupos'] as $grupo)
                <div class="ayuda-grupo" data-ayuda-grupo>
                    <div class="opt-lbl">{{ $grupo['titulo'] }}</div>
                    @foreach ($grupo['temas'] as $tema)
                        @include('partials.ayuda-tema', ['tema' => $tema, 'abierto' => false])
                    @endforeach
                </div>
            @endforeach

            <p class="ayuda-nada t-mute" hidden data-ayuda-nada>
                No hay nada con esas palabras. Probá con una sola: «mesa», «insumo», «cobrar».
            </p>

        </div>

        <div class="modal-ft">
            <div class="grow fs13 t-mute">
                ¿Falta algo acá? Decílo y se agrega.
            </div>
            <button class="btn" type="button" data-ayuda-cerrar>Cerrar</button>
        </div>

    </div>
</div>
