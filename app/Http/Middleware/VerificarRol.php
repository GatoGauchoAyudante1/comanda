<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe una ruta a ciertos roles.
 *
 *   Route::get(...)->middleware('rol:dueno,cajero');
 *
 * Ver docs/06-reglas-negocio.md · R-27 a R-29.
 */
class VerificarRol
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        /*
         | Si al usuario le revocaron el acceso mientras estaba adentro, se le
         | cierra la sesión y se lo manda al login.
         |
         | Con un 403 pelado quedaba encerrado: todas las pantallas le daban
         | 403, no podía llegar al login (el middleware `guest` lo rebotaba por
         | seguir autenticado) y tampoco desloguearse, porque para eso necesita
         | el token de una página que no cargaba. La única salida era borrar
         | las cookies del navegador.
        */
        if (! $user->active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('error', 'Tu usuario ya no está habilitado. Hablá con el encargado.');
        }

        // El dueño entra a todos lados.
        if ($user->role === 'dueno' || in_array($user->role, $roles, true)) {
            return $next($request);
        }

        abort(403, 'No tenés permiso para entrar acá.');
    }
}
