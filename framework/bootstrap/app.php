<?php

use App\Terminal\LegacyTerminalHttpIngress;
use App\Http\Middleware\CorrelateFrameworkRequest;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Ordinary global/web/API behavior stays intact. Only the exact native
        // terminal URL has a separate default-off raw public-protocol ingress.
        // Keep the terminal's dedicated raw-protocol short circuit outermost.
        // Ordinary Laravel requests receive an opaque, request-local support ID.
        $middleware->prepend([LegacyTerminalHttpIngress::class, CorrelateFrameworkRequest::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->dontFlash([
            'current_password', 'password', 'password_confirmation',
            'token', 'secret', 'pin', 'ldap_pass', 'nfc_identifier', 'setup_token',
        ]);

        // Do not log exception messages, SQL/bindings, URLs, headers or bodies.
        // Diagnostics report only the safe type and opaque request ID. Unlike
        // ::class, get_debug_type() omits an anonymous class's source filepath.
        $exceptions->report(function (Throwable $exception): void {
            $request = app()->bound('request') ? app('request') : null;
            $id = $request instanceof Request ? CorrelateFrameworkRequest::id($request) : null;
            $context = ['exception_type' => get_debug_type($exception)];
            if ($id !== null) {
                $context['request_id'] = $id;
            }
            Log::error('Framework request failed.', $context);
        })->stop();

        // The outer HTTP kernel may render an exception after the middleware
        // unwound; retain its ID without replacing Laravel's response contract.
        $exceptions->respond(fn (Response $response, Throwable $exception, Request $request): Response =>
            CorrelateFrameworkRequest::addResponseId($response, $request));
    })->create();
