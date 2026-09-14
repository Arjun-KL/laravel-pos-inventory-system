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
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API errors are always JSON. Without this, a request that doesn't send
        // "Accept: application/json" (a browser, a bare curl) gets a validation
        // failure as a 302 redirect to "/" instead of the {message, errors} envelope.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
