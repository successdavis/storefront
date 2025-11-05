<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'terminal.assigned' => \App\Http\Middleware\VerifyTerminalAssigned::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        // ✅ Disable syntax highlighting that is causing "PatternSearchException"
        $exceptions->renderable(function (\Throwable $e, $request) {
            if (app()->environment('local')) {
                return response()->make(
                    "<h2>Exception: {$e->getMessage()}</h2><pre>{$e->getTraceAsString()}</pre>",
                    500
                );
            }
        });
    })->create();
