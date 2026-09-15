<?php

namespace App\Http\Middleware;

use App\Support\Negocio;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra una ruta si su módulo no está activo.
 *
 *   Route::get(...)->middleware('modulo:pedidos_online');
 *
 * 404 y no 403: un módulo sin contratar o apagado no existe para nadie, ni
 * para el cliente que tiene el link guardado. Ver docs/02-decisiones.md · D-04.
 */
class VerificarModulo
{
    public function handle(Request $request, Closure $next, string $modulo): Response
    {
        abort_unless(Negocio::modulo($modulo), 404);

        return $next($request);
    }
}
