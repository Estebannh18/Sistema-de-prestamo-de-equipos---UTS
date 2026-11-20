<?php

use App\Http\Middleware\SincronizarMantenimientos;
use App\Http\Middleware\VerificarCuentaActiva;
use App\Http\Middleware\VerificarRol;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'rol' => VerificarRol::class,
        ]);

        // En cada petición web: una cuenta desactivada no puede seguir navegando.
        $middleware->web(append: [
            VerificarCuentaActiva::class,
        ]);

        // Antes que todo (también antes de cargar los modelos de la ruta): estados de equipos y
        // préstamos al día (mantenimientos de hoy y «No recogido»).
        $middleware->web(prepend: [
            SincronizarMantenimientos::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
