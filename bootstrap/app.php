<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // PHP-FPM is only reachable through the internal Docker network. Trust
        // the edge headers added by the two Nginx proxies so Laravel generates
        // HTTPS URLs and secure cookies for the original client request.
        $middleware->trustProxies(at: '*');
        $middleware->statefulApi();
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
