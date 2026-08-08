@props(['activo' => null])

@php
    /*
     | La barra lateral se arma con dos filtros:
     |   1. módulos activos del negocio  (config/negocio.php · D-04)
     |   2. rol del usuario              (docs/06-reglas-negocio.md · R-27 a R-29)
     |
     | Los roles de cada ítem deben coincidir con el middleware de routes/web.php.
     | Al dueño se le muestra todo.
     */
    $modulos = \App\Support\Negocio::modulos();
    $usuario = auth()->user();
    $rol     = $usuario?->role;

    $items = collect([
        ['id' => 'mesas',    'label' => 'Mesas',    'ruta' => 'panel',    'roles' => ['cajero', 'mozo'],   'modulo' => $modulos['salon'] || $modulos['pool']],
        ['id' => 'pedidos',  'label' => 'Pedidos',  'ruta' => 'pedidos',  'roles' => ['cajero', 'mozo'],   'modulo' => $modulos['delivery']],
        // Los roles de acá deben coincidir con el middleware de routes/web.php.
        // Cajero y mozo pueden entrar a la cocina, así que también la ven.
        ['id' => 'cocina',   'label' => 'Cocina',   'ruta' => 'cocina',   'roles' => ['cocina', 'cajero', 'mozo'], 'modulo' => true],
        // `exclusivo`: pantalla personal. Ni siquiera el dueño la ve en el menú,
        // porque le mostraría SUS envíos, que siempre están vacíos.
        ['id' => 'pedidos',  'label' => 'Envíos',   'ruta' => 'envios',   'roles' => ['repartidor'],       'modulo' => $modulos['delivery'], 'exclusivo' => true],
        ['id' => 'caja',     'label' => 'Caja',     'ruta' => 'caja',     'roles' => ['cajero'],           'modulo' => true],
        // `aparte`: permiso delegado por usuario, no por rol. Quien puede
        // cambiar precios entra a la Carta aunque su rol no la tenga (R-39).
        ['id' => 'carta',    'label' => 'Carta',    'ruta' => 'carta',    'roles' => [],                   'modulo' => true, 'aparte' => (bool) $usuario?->puedeEditarPrecios()],
        ['id' => 'stock',    'label' => 'Stock',    'ruta' => 'stock',    'roles' => [],                   'modulo' => $modulos['stock']],
        // Sólo el dueño: las recetas son el costo del negocio (R-27).
        ['id' => 'recetas',  'label' => 'Recetas',  'ruta' => 'recetas',  'roles' => [],                   'modulo' => $modulos['stock']],
        ['id' => 'historial','label' => 'Historial','ruta' => 'historial','roles' => ['cajero'],           'modulo' => true],
        ['id' => 'reportes', 'label' => 'Reportes', 'ruta' => 'reportes', 'roles' => [],                   'modulo' => true],
        ['id' => 'config',   'label' => 'Ajustes',  'ruta' => 'configuracion', 'roles' => [],              'modulo' => true],
    ])->filter(function ($item) use ($rol) {
        if (! $item['modulo']) {
            return false;
        }

        // Las pantallas exclusivas sólo las ve su rol, dueño incluido.
        if ($item['exclusivo'] ?? false) {
            return in_array($rol, $item['roles'], true);
        }

        return $rol === 'dueno'
            || in_array($rol, $item['roles'], true)
            || ($item['aparte'] ?? false);
    });

    // Las subpantallas marcan su módulo: estando en una receta o en un
    // conteo, el rail tiene que seguir señalando de dónde se entró.
    $padres     = ['receta' => 'recetas', 'conteo' => 'stock'];
    $rutaActual = $activo ?? request()->route()?->getName();
    $rutaActual = $padres[$rutaActual] ?? $rutaActual;
@endphp

<aside class="rail">

    <a class="brand" href="{{ route($usuario?->rutaInicio() ?? 'panel') }}">
        {{ mb_substr(config('negocio.nombre'), 0, 1) }}
    </a>

    <nav class="rail-nav">
        @foreach ($items as $item)
            <a class="rail-item {{ $rutaActual === $item['ruta'] ? 'is-on' : '' }}"
               href="{{ route($item['ruta']) }}">
                <x-icono :nombre="$item['id']" />
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    {{-- Aparece sólo cuando quedan ítems abajo. Ver marcarDesbordeDelRail() --}}
    <span class="rail-mas" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 9l6 6 6-6"/>
        </svg>
    </span>

    <form method="POST" action="{{ route('logout') }}" class="rail-user">
        @csrf
        <button type="submit" class="rail-item" style="width:60px"
                title="Salir · {{ ucfirst($usuario?->role) }}">
            <x-icono nombre="user" />
            <span>{{ $usuario?->name ?? 'Salir' }}</span>
        </button>
    </form>

</aside>
