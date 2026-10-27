<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Laravel security/CSRF defaults remain intact; no legacy session bridge.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->dontFlash([
            'current_password', 'password', 'password_confirmation',
            'token', 'secret', 'pin', 'ldap_pass', 'nfc_identifier',
        ]);

        // Do not log exception messages, SQL/bindings, URLs, headers or bodies.
        // Diagnostics intentionally report only the type, not personal data.
        $exceptions->report(function (Throwable $exception): void {
            Log::error('Framework request failed.', ['exception_type' => $exception::class]);
        })->stop();
    })->create();
