<?php

use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);

        // T26 - RNF-27: cabeceras defensivas en todas las respuestas.
        $middleware->append(SecurityHeaders::class);

        // T26 - RNF-27: sólo se confía en los proxies declarados. Sin la
        // variable no se confía en ninguno (valor seguro por defecto).
        $proxies = env('TRUSTED_PROXIES');
        $middleware->trustProxies(
            at: $proxies === null || $proxies === '' ? null : explode(',', (string) $proxies),
        );

        // El invitado termina en la pantalla de inicio de sesión (spec 003).
        // Las peticiones JSON reciben 401; la navegación normal, 302 a /login.
        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
