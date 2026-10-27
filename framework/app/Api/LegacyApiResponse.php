<?php

namespace App\Api;

use Symfony\Component\HttpFoundation\Response;

/** The existing mixed wire contract, not Laravel's default 422/redirect envelope. */
final class LegacyApiResponse
{
    public static function json(array $body, int $status = 200): Response
    {
        // Legacy json_encode uses options=0 (not JsonResponse's HEX defaults).
        return new Response(json_encode($body), $status, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    public static function text(string $message, int $status): Response
    {
        return new Response($message, $status, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
