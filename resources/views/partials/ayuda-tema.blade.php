@php
    /*
     | Un tema de la ayuda. <details> nativo y no un acordeón hecho a mano:
     | funciona sin JS, lo abre el teclado y el buscador del navegador
     | (Ctrl+F) encuentra el texto de adentro aunque esté cerrado.
     |
     | `data-buscar` junta título, dónde y sinónimos en una sola cadena: es lo
     | que filtra el campo de búsqueda del panel.
     */
    // El botón «Ir» no se dibuja si ya estás en esa pantalla: mandar a alguien
    // adonde ya está es la forma más rápida de que deje de creerle a la ayuda.
    $destino = ($tema['ruta'] ?? null) && request()->route()?->getName() !== $tema['ruta']
        ? route($tema['ruta']) . ($tema['ancla'] ?? '')
        : null;

    // El botón nombra sólo la pantalla, no el camino entero: «Ir a Mesas →»
    // y no «Ir a Mesas → la mesa → + Agregar consumo →», que ya es una frase.
    $pantalla = trim(explode('→', $tema['donde'])[0]);

    // El grupo queda afuera a propósito: metiéndolo, buscar «recetas» devuelve
    // los cinco temas de «Insumos y recetas» y el que se busca se pierde.
    $buscar = mb_strtolower(implode(' ', [
        $tema['titulo'],
        $tema['donde'],
        $tema['alias'] ?? '',
    ]));
@endphp

<details class="ayuda-tema" data-ayuda-tema data-buscar="{{ $buscar }}" @if ($abierto ?? false) open @endif>
    <summary>
        <span class="tt">{{ $tema['titulo'] }}</span>
        <span class="donde">{{ $tema['donde'] }}</span>
    </summary>

    <ol class="ayuda-pasos">
        @foreach ($tema['pasos'] as $paso)
            <li>{{ $paso }}</li>
        @endforeach
    </ol>

    @isset($tema['nota'])
        <p class="ayuda-nota">{{ $tema['nota'] }}</p>
    @endisset

    @if ($destino)
        <a class="btn btn-sm ayuda-ir" href="{{ $destino }}">Ir a {{ $pantalla }} &rarr;</a>
    @endif
</details>
