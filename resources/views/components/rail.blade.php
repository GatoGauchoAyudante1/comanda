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
        ['id' => 'pedidos',  'label' => 'Cocina',   'ruta' => 'cocina',   'roles' => ['cocina'],           'modulo' => true],
        ['id' => 'pedidos',  'label' => 'Envíos',   'ruta' => 'envios',   'roles' => ['repartidor'],       'modulo' => $modulos['delivery']],
        ['id' => 'caja',     'label' => 'Caja',     'ruta' => 'caja',     'roles' => ['cajero'],           'modulo' => true],
        ['id' => 'carta',    'label' => 'Carta',    'ruta' => 'carta',    'roles' => [],                   'modulo' => true],
        ['id' => 'stock',    'label' => 'Stock',    'ruta' => 'stock',    'roles' => [],                   'modulo' => $modulos['stock']],
        ['id' => 'reportes', 'label' => 'Reportes', 'ruta' => 'reportes', 'roles' => [],                   'modulo' => true],
        ['id' => 'config',   'label' => 'Ajustes',  'ruta' => 'configuracion', 'roles' => [],              'modulo' => true],
    ])->filter(function ($item) use ($rol) {
        if (! $item['modulo']) {
            return false;
        }

        return $rol === 'dueno' || in_array($rol, $item['roles'], true);
    });

    $rutaActual = $activo ?? request()->route()?->getName();
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

    <form method="POST" action="{{ route('logout') }}" class="rail-user">
        @csrf
        <button type="submit" class="rail-item" style="width:60px"
                title="Salir · {{ ucfirst($usuario?->role) }}">
            <x-icono nombre="user" />
            <span>{{ $usuario?->name ?? 'Salir' }}</span>
        </button>
    </form>

</aside>
