<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe una ruta a ciertos roles.
 *
 *   Route::get(...)->middleware('rol:dueno,cajero');
 *
 * Ver docs/06-reglas-negocio.md · R-27 a R-29.
 */
class VerificarRol extends VerificaAcceso
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $this->usuarioVigente($request);

        if ($user instanceof Response) {
            return $user;
        }

        // El dueño entra a todos lados.
        if ($user->role === 'dueno' || in_array($user->role, $roles, true)) {
            return $next($request);
        }

        abort(403, 'No tenés permiso para entrar acá.');
    }
}
