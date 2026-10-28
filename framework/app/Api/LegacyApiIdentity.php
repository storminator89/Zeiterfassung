<?php

namespace App\Api;

use App\Auth\LegacyUser;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use LogicException;

/** Legacy JWT verification plus current account binding, never local-web login. */
final class LegacyApiIdentity
{
    public function __construct(
        private AdmittedApiSigningKey $key,
        private Repository $config,
        private LegacyIssuedTokenVerifier $tokens,
        private ?AccountLifecycleApiIdentity $lifecycle = null,
    ) {}

    public function authenticate(Request $request): LegacyUser
    {
        if (! preg_match('/^Bearer ([A-Za-z0-9_.-]+)$/D', $request->header('Authorization', ''), $matches)) {
            throw new HttpResponseException(LegacyApiResponse::text('Invalid bearer token.', 401));
        }

        // Resolve only on a syntactically valid bearer request. There is no
        // shipped key binding, automatic metadata-path read or generated key.
        $payload = $this->tokens->verify($matches[1], $this->key->secret());
        if (! $payload) {
            throw new HttpResponseException(LegacyApiResponse::text('Invalid or expired token.', 401));
        }

        // LegacyUser uses only the named chrono connection, never the scratch
        // default. A real connection admission is still a separate #74/#75 gate.
        if (! $this->config->has('database.connections.chrono')) {
            throw new LogicException('An explicitly admitted named chrono connection is required.');
        }
        $user = LegacyUser::query()->find($payload['user_id']);
        if (! $user instanceof LegacyUser || ! $user->password
            || ! hash_equals(hash('sha256', $user->password), $payload['ver'])
            || ($this->lifecycle !== null && ! $this->lifecycle->accepts($user, $payload))) {
            throw new HttpResponseException(LegacyApiResponse::text('Account deleted or credentials changed.', 401));
        }

        // Existing API tokens do not require auth_source=local or a known role.
        // Login issuance remains local-only and is NOT implemented here.
        return $user;
    }
}
