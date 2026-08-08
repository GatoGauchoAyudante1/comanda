<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deja pasar a quien puede tocar los precios de la carta: el dueño, y los
 * usuarios que él habilitó de a uno en Ajustes → Usuarios.
 *
 *   Route::post(...)->middleware('precios');
 *
 * Ver docs/06-reglas-negocio.md · R-39.
 */
class VerificarPrecios extends VerificaAcceso
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->usuarioVigente($request);

        if ($user instanceof Response) {
            return $user;
        }

        if ($user->puedeEditarPrecios()) {
            return $next($request);
        }

        abort(403, 'No tenés permiso para cambiar precios.');
    }
}
