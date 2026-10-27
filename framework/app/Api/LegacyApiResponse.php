<?php

namespace App\Api;

use Symfony\Component\HttpFoundation\Response;

/** Native API JSON wire contract, not Laravel's default 422/redirect envelope. */
final class LegacyApiResponse
{
    public static function json(array $body, int $status = 200): Response
    {
        // Legacy json_encode uses options=0 (not JsonResponse's HEX defaults).
        return new Response(json_encode($body), $status, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    // Historical method name: existing controlled error callers now follow
    // native abort_request's JSON envelope without changing their decisions.
    public static function text(string $message, int $status): Response
    {
        return new Response(
            json_encode(['success' => false, 'message' => $message], JSON_INVALID_UTF8_SUBSTITUTE),
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8'],
        );
    }
}
