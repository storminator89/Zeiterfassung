<?php

namespace App\Terminal;

use App\Http\Controllers\Terminal\LegacyTerminalController;
use App\Http\Middleware\CorrelateFrameworkRequest;
use AttendanceError;
use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use LogicException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use WeakMap;

/** Dedicated native-public terminal ingress only; never the ordinary web stack. */
final class LegacyTerminalHttpIngress
{
    // Object identity created by source, not a header/server/attribute flag.
    private static ?WeakMap $captured = null;

    public function __construct(
        private readonly Container $container,
        private readonly Repository $config,
        private readonly Router $router,
    ) {}

    public static function matchesRawPath(array $server): bool
    {
        $uri = $server['REQUEST_URI'] ?? null;

        return is_string($uri) && explode('?', $uri, 2)[0] === '/terminal_api.php';
    }

    /** Stock Request constructor: no eager JSON, request_parse_body or body read. */
    public static function capture(array $server): Request
    {
        if (! self::matchesRawPath($server)) {
            throw new LogicException('Only the exact terminal ingress can use raw capture.');
        }
        // Native terminal_api.php consults no query/form/cookie/file input bag.
        // Preserve the original server bag and leave content deferred (null).
        $request = new Request([], [], [], [], [], $server);
        $request->headers->remove('X-HTTP-METHOD-OVERRIDE');
        self::$captured ??= new WeakMap;
        self::$captured[$request] = true;

        return $request;
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! self::matchesRawPath($request->server->all())) {
            return $next($request);
        }

        try {
            if (! $this->enabled()) {
                return $this->router->prepareResponse($request, self::closed());
            }
            $this->requireCaptured($request);
        } catch (Throwable) {
            return $this->router->prepareResponse($request, $this->headers(
                LegacyTerminalResponse::unconfirmed(), $request,
            ));
        }

        // Only the already enabled, source-captured raw branch enters telemetry.
        // Closed defaults and early admission refusals keep their original wire.
        return (new CorrelateFrameworkRequest)->handle($request,
            fn (Request $request): Response => $this->handleAdmitted($request));
    }

    private function handleAdmitted(Request $request): Response
    {
        // Maintenance is the stock operational outer gate, before module/method
        // checks. Do not catch/rewrite its status, headers, templates or exception
        // rendering as a terminal protocol failure, and never bypass artisan down.
        return $this->container->make(PreventRequestsDuringMaintenance::class)->handle($request, function (Request $request): Response {
            try {
                // Unknown verbs reach the existing module-before-method check,
                // not Laravel's route-method rejection or a CORS OPTIONS shortcut.
                if ($request->server('REQUEST_METHOD') !== 'POST') {
                    return $this->router->prepareResponse($request, $this($request));
                }

                // Exactly one maintained route, without web/API groups. Only
                // this admitted terminal branch avoids JSON/post-size/CORS;
                // other URLs keep the untouched global stack, including down.
                return $this->router->dispatch($request);
            } catch (Throwable) {
                return $this->router->prepareResponse($request, $this->headers(
                    LegacyTerminalResponse::unconfirmed(), $request,
                ));
            }
        });
    }

    /** Runtime gate also protects stale route caches and normalized path aliases. */
    public function __invoke(Request $request): Response
    {
        if (! self::matchesRawPath($request->server->all())) {
            return self::closed();
        }

        try {
            if (! $this->enabled()) {
                return self::closed();
            }
            $this->requireCaptured($request);
            if ($this->container->bound(AdmittedTerminalConfiguration::class)) {
                $this->container->make(AdmittedTerminalConfiguration::class)->assertCurrent($this->container);
            }
            // Resolve only after HTTP admission. The backend in turn requires
            // explicit existing-key sources before resolving its one writer.
            $response = ($this->container->make(LegacyTerminalController::class))($request);
        } catch (AttendanceError $error) {
            $response = LegacyTerminalResponse::error($error->getMessage(), $error->status);
        } catch (Throwable) {
            // No exception text, headers, body or credential material is logged.
            $response = LegacyTerminalResponse::unconfirmed();
        }

        return $this->headers($response, $request);
    }

    private function enabled(): bool
    {
        $enabled = $this->config->get('chrono_terminal.http_enabled', false);
        if (! is_bool($enabled)) {
            throw new LogicException('Terminal HTTP ingress requires an explicit boolean opt-in.');
        }

        return $enabled;
    }

    private function requireCaptured(Request $request): void
    {
        if (self::$captured === null || ! isset(self::$captured[$request])) {
            throw new LogicException('The source-created terminal raw request is required.');
        }
    }

    private static function closed(): Response
    {
        return new Response('', 404);
    }

    private function headers(Response $response, Request $request): Response
    {
        // Match native security.php's terminal response policy only. No CORS,
        // cookies, proxy-derived peer identity, web/session/CSRF or host changes.
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Content-Security-Policy', "base-uri 'self'; object-src 'none'; frame-ancestors 'none'");
        $response->headers->set('Cache-Control', 'no-store');
        $https = $request->server('HTTPS');
        if ((! empty($https) && $https !== 'off') || getenv('APP_HTTPS') === '1') {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
