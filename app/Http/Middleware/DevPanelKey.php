<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puerta del panel del desarrollador (/dev-panel).
 *
 * No usa el guard de la app: el panel es del desarrollador, no del cliente.
 * Un dueño logueado no entra sin la clave, y el desarrollador entra sin estar
 * logueado como usuario del sistema.
 *
 * La clave vive sólo en el .env del servidor. Como esto es Blade y no una SPA,
 * se valida una vez en el login y queda en la sesión; acá se vuelve a comparar
 * contra el .env en cada request. Eso tiene una ventaja concreta: si cambio
 * DEV_PANEL_KEY, todas las sesiones abiertas quedan invalidadas solas.
 */
class DevPanelKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $clave = (string) config('license.dev_key');

        if ($clave === '') {
            abort(403, 'El panel de desarrollo no está configurado. Definí DEV_PANEL_KEY en el .env del servidor.');
        }

        // hash_equals y no ===: la comparación no depende del contenido, así
        // el tiempo de respuesta no filtra cuántos caracteres acertó el que prueba.
        if (! hash_equals($clave, (string) $request->session()->get('dev_panel_key'))) {
            // Al login del panel, no al del sistema: son sesiones distintas.
            return redirect()->route('dev-panel.index');
        }

        return $next($request);
    }
}
