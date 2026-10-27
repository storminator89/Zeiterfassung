<?php

namespace App\Terminal;

use Symfony\Component\HttpFoundation\Response;

/** Native attendance_json wire envelope, not a Laravel validation/redirect response. */
final class LegacyTerminalResponse
{
    public static function success(array $body): Response
    {
        // Match native success encoding, including a failed encoding becoming 503.
        return new Response(json_encode($body, JSON_THROW_ON_ERROR), 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    public static function error(string $message, int $status): Response
    {
        return new Response(json_encode(['success' => false, 'message' => $message]), $status, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    public static function unconfirmed(): Response
    {
        return self::error(
            'Buchung nicht bestätigt. Verbindung prüfen und denselben Vorgang erneut versuchen.',
            503,
        );
    }
}
