<?php

namespace App\Http\Middleware;

use App\Api\LegacyApiResponse;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class LegacyApiWire
{
    public function handle(Request $request, Closure $next): Response
    {
        // Native api.php ignores Laravel/Symfony method overrides entirely.
        // Candidate paths match all framework verbs; only the raw method below
        // decides dispatch, and response preparation must not treat a POST as
        // HEAD because of an override header/query/body parameter.
        $rawMethod = (string) $request->server('REQUEST_METHOD', 'GET');
        $request->headers->remove('X-HTTP-METHOD-OVERRIDE');
        $request->request->remove('_method');
        $request->query->remove('_method');
        $request->setMethod($rawMethod);

        // Must precede guard/session/DB/key resolution, including unknown paths.
        if ($request->server('REQUEST_METHOD') === 'OPTIONS') {
            $response = new Response('', 200);
        } elseif (! in_array($request->server('REQUEST_METHOD'), ['GET', 'POST', 'DELETE'], true)) {
            $response = LegacyApiResponse::json(['success' => false, 'message' => 'Method Not Allowed'], 405);
        } elseif (($expectedMethod = $request->route()?->getAction('legacy_api_method')) !== null
            && ($request->server('REQUEST_METHOD') !== $expectedMethod
                || (($expectedPath = $request->route()->getAction('legacy_api_path')) !== null
                    && parse_url($request->server('REQUEST_URI', ''), PHP_URL_PATH) !== $expectedPath)
                || (($pattern = $request->route()->getAction('legacy_api_pattern')) !== null
                    && ! preg_match($pattern, parse_url($request->server('REQUEST_URI', ''), PHP_URL_PATH) ?? '')))) {
            $response = LegacyApiResponse::json(['success' => false, 'message' => 'Invalid endpoint'], 400);
        } else {
            try {
                $response = $next($request);
            } catch (HttpResponseException $error) {
                $response = $error->getResponse();
            } catch (Throwable) {
                // Scoped API failure boundary, never HTML/debug/SQL/path/key output.
                // No credential fallback, retry or conversion of uncertainty to 401/429.
                $response = LegacyApiResponse::json(['success' => false, 'message' => 'Internal server error'], 500);
            }
            // Laravel's Routing\Pipeline renders exceptions in EACH inner slice;
            // those failures return instead of reaching the outer catch above.
            // Its ResponseTrait marker preserves the original exception. Normalize
            // ONLY this API stack, never ordinary/global route rendering, and keep
            // intentional HttpResponseException responses (including Retry-After).
            if (($response instanceof IlluminateResponse || $response instanceof JsonResponse
                    || $response instanceof RedirectResponse)
                && $response->exception instanceof Throwable
                && ! $response->exception instanceof HttpResponseException) {
                $response = LegacyApiResponse::json(['success' => false, 'message' => 'Internal server error'], 500);
            }
        }
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, DELETE');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
