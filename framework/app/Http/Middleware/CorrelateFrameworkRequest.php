<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Request-local opaque correlation only: never request input or shared log context. */
final class CorrelateFrameworkRequest
{
    private const ATTRIBUTE = '_chrono_framework_request_id';

    public function handle(Request $request, Closure $next): Response
    {
        // Always replace any preexisting value. Client headers, cookies, query
        // values and bodies are not consulted, parsed, echoed or logged here.
        $request->attributes->set(self::ATTRIBUTE, bin2hex(random_bytes(16)));

        $response = $next($request);
        self::addResponseId($response, $request);

        // Also correlate controlled 5xx responses whose controller deliberately
        // consumed its exception. Successful/ordinary 4xx requests are not logged.
        if ($response->getStatusCode() >= 500) {
            try {
                Log::warning('Framework response failed.', [
                    'request_id' => self::id($request),
                    'status' => $response->getStatusCode(),
                ]);
            } catch (Throwable) {
                // Correlation is best-effort telemetry, never a reason to replace
                // the application's existing failure response or retry contract.
                // Do not send diagnostics to another/unapproved log destination.
            }
        }

        return $response;
    }

    public static function id(Request $request): ?string
    {
        $id = $request->attributes->get(self::ATTRIBUTE);

        return is_string($id) && preg_match('/^[a-f0-9]{32}$/D', $id) === 1 ? $id : null;
    }

    public static function addResponseId(Response $response, Request $request): Response
    {
        $id = self::id($request);
        if ($id !== null) {
            // Fixed header only: preserve body, status, cookies and wire format.
            $response->headers->set('X-Request-ID', $id);
        }

        return $response;
    }
}
