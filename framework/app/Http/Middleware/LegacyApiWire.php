<?php

namespace App\Http\Middleware;

use App\Api\LegacyApiResponse;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class LegacyApiWire
{
    public function handle(Request $request, Closure $next): Response
    {
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
            }
        }
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, DELETE');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');

        return $response;
    }
}
