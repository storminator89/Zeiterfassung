<?php

namespace App\Http\Controllers\Api;

use App\Api\LegacyApiLogin;
use App\Api\LegacyApiResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class LegacyApiLoginController
{
    public function __construct(private LegacyApiLogin $login) {}

    public function __invoke(Request $request): Response
    {
        // Preserve string-only legacy shape, including empty strings. Laravel's
        // required/trim/null rules would change the login/attempt contract.
        $input = json_decode($request->getContent(), true);
        if (! is_array($input) || ! is_string($input['username'] ?? null) || ! is_string($input['password'] ?? null)) {
            return LegacyApiResponse::text('Username and password required.', 400);
        }
        $token = $this->login->issue($input['username'], $input['password'], (string) ($request->server('REMOTE_ADDR') ?? 'local'));
        return $token === null
            ? LegacyApiResponse::json(['success' => false, 'message' => 'Invalid credentials'], 401)
            : LegacyApiResponse::json(['success' => true, 'token' => $token]);
    }
}
