<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'rol' => \App\Http\Middleware\VerificarRol::class,
            // Permiso delegable, no rol: ver docs/06-reglas-negocio.md · R-39.
            'precios' => \App\Http\Middleware\VerificarPrecios::class,
            // Módulo contratado y prendido: ver docs/02-decisiones.md · D-04.
            'modulo' => \App\Http\Middleware\VerificarModulo::class,
            // Panel del desarrollador: clave del .env, ajeno al guard de la app.
            'dev.key' => \App\Http\Middleware\DevPanelKey::class,
        ]);

        // Cada rol entra a lo suyo. Ver docs/06-reglas-negocio.md R-27 a R-29.
        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
