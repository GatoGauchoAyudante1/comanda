<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Manifiesto y service worker de la PWA.
 *
 * Se sirven por ruta y no como archivos estáticos porque el nombre, el color
 * y la pantalla de inicio dependen de la configuración de la instalación (D-02).
 *
 * IMPORTANTE: el service worker NO cachea nada de la aplicación. Se limita a
 * lo mínimo para que el navegador la ofrezca como instalable. Cachear
 * pantallas de un POS sería mostrar mesas y stock desactualizados, que es peor
 * que no mostrar nada. Ver docs/02-decisiones.md · D-05.
 */
class PwaController extends Controller
{
    public function manifest(): JsonResponse
    {
        // El ícono que queda en el celular es el del sistema, no el del cliente:
        // el mozo instala "Comandas", no el nombre del bar.
        $nombre = config('app.name');

        return response()->json([
            'name'             => $nombre,
            'short_name'       => mb_substr($nombre, 0, 12),
            'description'      => 'Gestión de salón, pool, delivery, caja y stock',
            'start_url'        => '/',
            'scope'            => '/',
            'display'          => 'standalone',
            'orientation'      => 'portrait-primary',
            'background_color' => '#070908',
            'theme_color'      => '#070908',
            'lang'             => 'es-AR',
            'icons'            => [
                ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ])->header('Content-Type', 'application/manifest+json');
    }

    public function serviceWorker(): Response
    {
        $js = <<<'JS'
        /*
         | Service worker mínimo, a propósito.
         |
         | No cachea la aplicación: un POS que muestra mesas o stock viejos es
         | peor que uno que no abre. Sólo existe para que el navegador ofrezca
         | instalar la app en el celular. Ver docs/02-decisiones.md D-05 y D-18.
         */
        self.addEventListener('install', () => self.skipWaiting());
        self.addEventListener('activate', event => event.waitUntil(clients.claim()));

        self.addEventListener('fetch', event => {
            // Todo va a la red. Si no hay conexión, el navegador avisa como
            // siempre: no se inventan datos guardados.
            event.respondWith(fetch(event.request));
        });
        JS;

        return response($js)
            ->header('Content-Type', 'application/javascript')
            ->header('Service-Worker-Allowed', '/');
    }
}
