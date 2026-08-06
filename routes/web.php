<?php

use App\Http\Controllers\CajaController;
use App\Http\Controllers\CartaController;
use App\Http\Controllers\CobroController;
use App\Http\Controllers\CocinaController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\ConsumoController;
use App\Http\Controllers\ConteoController;
use App\Http\Controllers\RecetaController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\RepartidorController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\HistorialController;
use App\Http\Controllers\MesaController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\SesionController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas
|--------------------------------------------------------------------------
| Mapa completo de pantallas: docs/07-pantallas.md
| Permisos por rol: docs/06-reglas-negocio.md · R-27 a R-29
|
| El middleware `rol` deja pasar siempre al dueño, así que en las rutas
| sólo se nombran los otros roles que también entran.
*/

// PWA: accesibles sin sesión, el navegador las pide antes del login.
Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/sw.js', [PwaController::class, 'serviceWorker'])->name('pwa.sw');

Route::middleware('guest')->group(function () {
    Route::get('/login', [SesionController::class, 'mostrar'])->name('login');
    Route::post('/login', [SesionController::class, 'entrar'])->name('login.entrar');
});

Route::post('/logout', [SesionController::class, 'salir'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {

    // Operación diaria
    Route::get('/', PanelController::class)
        ->middleware('rol:cajero,mozo')
        ->name('panel');

    // Mesas y consumos: los mozos también entran.
    Route::middleware('rol:cajero,mozo')->group(function () {
        Route::post('/mesas/{mesa}/abrir', [MesaController::class, 'abrir'])->name('mesa.abrir');
        Route::get('/mesas/{sesion}', [MesaController::class, 'mostrar'])->name('mesa');
        Route::post('/mesas/{sesion}/pausar', [MesaController::class, 'pausar'])->name('mesa.pausar');

        Route::get('/mesas/{sesion}/consumo', [ConsumoController::class, 'mostrar'])->name('consumo');
        Route::post('/mesas/{sesion}/consumo', [ConsumoController::class, 'guardar'])->name('consumo.guardar');
        Route::delete('/mesas/{sesion}/consumo/{item}', [ConsumoController::class, 'quitar'])->name('consumo.quitar');

        // El mozo también imprime: la precuenta es tarea suya.
        Route::get('/pedidos/{orden}/ticket', TicketController::class)->name('ticket');
    });

    // Cobro y anulación: sólo cajero y dueño. El mozo no cobra (R-28)
    // ni anula, porque anular destruye facturación (R-06).
    Route::middleware('rol:cajero')->group(function () {
        Route::post('/mesas/{sesion}/anular', [MesaController::class, 'anular'])->name('mesa.anular');

        Route::post('/pedidos/{orden}/cobrar', [CobroController::class, 'iniciar'])->name('cobro.iniciar');
        Route::get('/pedidos/{orden}/cobrar', [CobroController::class, 'mostrar'])->name('cobro');
        Route::post('/pedidos/{orden}/pagos', [CobroController::class, 'agregarPago'])->name('cobro.pago');
        Route::delete('/pedidos/{orden}/pagos/{pago}', [CobroController::class, 'quitarPago'])->name('cobro.pago.quitar');
        Route::post('/pedidos/{orden}/confirmar', [CobroController::class, 'confirmar'])->name('cobro.confirmar');
        Route::post('/pedidos/{orden}/cancelar-cobro', [CobroController::class, 'cancelar'])->name('cobro.cancelar');
    });

    // Delivery
    Route::middleware('rol:cajero,mozo')->group(function () {
        Route::get('/pedidos', [PedidoController::class, 'tablero'])->name('pedidos');
        Route::get('/pedidos/nuevo', [PedidoController::class, 'nuevo'])->name('pedidos.nuevo');
        Route::get('/pedidos/cliente', [PedidoController::class, 'buscarCliente'])->name('pedidos.cliente');
        Route::post('/pedidos', [PedidoController::class, 'guardar'])->name('pedidos.guardar');
        Route::post('/pedidos/{orden}/avanzar', [PedidoController::class, 'avanzar'])->name('pedidos.avanzar');
        Route::post('/pedidos/{orden}/servido', [PedidoController::class, 'servido'])->name('pedidos.servido');
    });

    // Cocina
    Route::middleware('rol:cocina,cajero,mozo')->group(function () {
        Route::get('/cocina', [CocinaController::class, 'index'])->name('cocina');
        Route::post('/cocina/{orden}/listo', [CocinaController::class, 'listo'])->name('cocina.listo');
    });

    // Repartidor: sólo sus envíos y su rendición (R-29).
    Route::middleware('rol:repartidor')->group(function () {
        Route::get('/mis-envios', [RepartidorController::class, 'index'])->name('envios');
        Route::post('/mis-envios/{orden}/entregar', [RepartidorController::class, 'entregar'])->name('envios.entregar');
        Route::post('/mis-envios/rendir', [RepartidorController::class, 'rendir'])->name('envios.rendir');
    });

    // Caja: sólo cajero y dueño. El mozo no cobra (R-28).
    Route::middleware('rol:cajero')->group(function () {
        Route::get('/caja', [CajaController::class, 'mostrar'])->name('caja');
        Route::post('/caja/abrir', [CajaController::class, 'abrirTurno'])->name('caja.abrir');
        Route::post('/caja/movimiento', [CajaController::class, 'registrarMovimiento'])->name('caja.movimiento');
        Route::post('/caja/cerrar', [CajaController::class, 'cerrarTurno'])->name('caja.cerrar');
        Route::get('/caja/cierre/{caja}', [CajaController::class, 'verCierre'])->name('caja.cierre');
    });

    // Administración: sólo el dueño (R-27).
    Route::middleware('rol:dueno')->group(function () {
        Route::get('/carta', [CartaController::class, 'index'])->name('carta');
        Route::post('/carta/productos', [CartaController::class, 'guardarProducto'])->name('carta.producto');
        Route::post('/carta/productos/{producto}', [CartaController::class, 'guardarProducto'])->name('carta.producto.actualizar');
        Route::post('/carta/productos/{producto}/alternar', [CartaController::class, 'alternar'])->name('carta.alternar');
        Route::post('/carta/categorias', [CartaController::class, 'guardarCategoria'])->name('carta.categoria');
        Route::post('/carta/precios', [CartaController::class, 'ajustarPrecios'])->name('carta.precios');
    });

    // Stock: insumos, recetas y conteo. El cajero también cuenta (es tarea de turno).
    Route::middleware('rol:dueno,cajero')->group(function () {
        Route::get('/stock', [StockController::class, 'index'])->name('stock');
        Route::post('/stock/insumos', [StockController::class, 'guardarInsumo'])->name('stock.insumo');
        Route::post('/stock/insumos/{insumo}', [StockController::class, 'guardarInsumo'])->name('stock.insumo.actualizar');
        Route::post('/stock/merma', [StockController::class, 'registrarMerma'])->name('stock.merma');
        Route::post('/stock/compra', [StockController::class, 'registrarCompra'])->name('stock.compra');

        Route::get('/stock/conteo', [ConteoController::class, 'mostrar'])->name('conteo');
        Route::post('/stock/conteo', [ConteoController::class, 'abrir'])->name('conteo.abrir');
        Route::post('/stock/conteo/item/{item}', [ConteoController::class, 'guardarItem'])->name('conteo.item');
        Route::post('/stock/conteo/{conteo}/cerrar', [ConteoController::class, 'cerrar'])->name('conteo.cerrar');
    });

    // Las recetas definen costos: sólo el dueño (R-27).
    Route::middleware('rol:dueno')->group(function () {
        Route::get('/stock/recetas/{producto}', [RecetaController::class, 'mostrar'])->name('receta');
        Route::post('/stock/recetas/{producto}', [RecetaController::class, 'guardarLinea'])->name('receta.linea');
        Route::delete('/stock/recetas/{producto}/{linea}', [RecetaController::class, 'borrarLinea'])->name('receta.linea.borrar');
    });

    // Historial: el cajero tambien lo necesita, los reclamos aparecen en el mostrador.
    Route::get('/historial', HistorialController::class)
        ->middleware('rol:cajero')
        ->name('historial');

    Route::get('/reportes', ReporteController::class)
        ->middleware('rol:dueno')
        ->name('reportes');

    // Ajustes: sólo el dueño. Define módulos, tarifas, zonas y usuarios.
    Route::middleware('rol:dueno')->group(function () {
        Route::get('/ajustes', [ConfiguracionController::class, 'index'])->name('configuracion');
        Route::post('/ajustes/negocio', [ConfiguracionController::class, 'guardarNegocio'])->name('configuracion.negocio');
        Route::post('/ajustes/modulo', [ConfiguracionController::class, 'alternarModulo'])->name('configuracion.modulo');
        Route::post('/ajustes/modulo/restablecer', [ConfiguracionController::class, 'restablecerModulo'])->name('configuracion.modulo.restablecer');

        Route::post('/ajustes/cocina', [ConfiguracionController::class, 'guardarCocina'])->name('configuracion.cocina');

        Route::post('/ajustes/mesas', [ConfiguracionController::class, 'crearMesas'])->name('configuracion.mesas');
        Route::post('/ajustes/mesas/{mesa}/alternar', [ConfiguracionController::class, 'alternarMesa'])->name('configuracion.mesa.alternar');

        Route::post('/ajustes/tarifas', [ConfiguracionController::class, 'guardarTarifa'])->name('configuracion.tarifa');
        Route::post('/ajustes/tarifas/{tarifa}', [ConfiguracionController::class, 'guardarTarifa'])->name('configuracion.tarifa.actualizar');

        Route::post('/ajustes/zonas', [ConfiguracionController::class, 'guardarZona'])->name('configuracion.zona');
        Route::post('/ajustes/zonas/{zona}', [ConfiguracionController::class, 'guardarZona'])->name('configuracion.zona.actualizar');

        Route::post('/ajustes/usuarios', [ConfiguracionController::class, 'guardarUsuario'])->name('configuracion.usuario');
        Route::post('/ajustes/usuarios/{usuario}', [ConfiguracionController::class, 'guardarUsuario'])->name('configuracion.usuario.actualizar');
        Route::post('/ajustes/usuarios/{usuario}/alternar', [ConfiguracionController::class, 'alternarUsuario'])->name('configuracion.usuario.alternar');
        Route::delete('/ajustes/usuarios/{usuario}', [ConfiguracionController::class, 'eliminarUsuario'])->name('configuracion.usuario.eliminar');
    });
});
