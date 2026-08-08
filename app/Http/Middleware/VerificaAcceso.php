<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Lo que comparten los middlewares que deciden quién entra a dónde:
 * primero hay que estar adentro, y seguir estándolo.
 */
abstract class VerificaAcceso
{
    /**
     * El usuario de la request, o el rebote correspondiente.
     *
     * Si le revocaron el acceso mientras estaba adentro, se le cierra la
     * sesión y se lo manda al login.
     *
     * Con un 403 pelado quedaba encerrado: todas las pantallas le daban 403,
     * no podía llegar al login (el middleware `guest` lo rebotaba por seguir
     * autenticado) y tampoco desloguearse, porque para eso necesita el token
     * de una página que no cargaba. La única salida era borrar las cookies
     * del navegador.
     */
    protected function usuarioVigente(Request $request): User|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        if (! $user->active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('error', 'Tu usuario ya no está habilitado. Hablá con el encargado.');
        }

        return $user;
    }
}
