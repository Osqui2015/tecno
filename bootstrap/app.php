<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        App\Console\Commands\ScrapeDazProducts::class,
        App\Console\Commands\ScrapeTucProducts::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum para SPA: estado para rutas web que consuman la API
        $middleware->statefulApi();

        // CSRF: las rutas API usan Bearer tokens (Sanctum createToken),
        // no sesiones, así que NO necesitan CSRF. Lo excluimos para api/*.
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        // Alias de middleware de la app
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Devolver JSON en errores API en lugar de HTML
        $exceptions->shouldRenderJsonWhen(function (Request $request) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();
