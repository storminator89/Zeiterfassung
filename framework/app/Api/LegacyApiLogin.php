<?php

namespace App\Api;

use App\Auth\LegacyUser;
use Firebase\JWT\JWT;
use Illuminate\Auth\AuthManager;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use LogicException;

/** Local API credentials only, no session login/rehash/account write or LDAP bind. */
final class LegacyApiLogin
{
    public function __construct(
        private AuthManager $auth,
        private Container $container,
        private Repository $config,
        private AdmittedApiSigningKey $key,
    ) {}

    public function issue(string $username, #[\SensitiveParameter] string $password, string $remoteAddress): ?string
    {
        if (! $this->container->bound(LegacyApiLoginBudget::class)
            || ! $this->config->has('database.connections.chrono')) {
            throw new LogicException('An explicitly admitted shared login budget and chrono connection are required.');
        }
        $this->container->make(LegacyApiLoginBudget::class)->consume($username, $remoteAddress);
        $provider = $this->auth->createUserProvider('chrono_users');
        if (! $provider instanceof EloquentUserProvider) {
            throw new LogicException('The existing legacy account provider is required.');
        }
        // No trimming or role allowlist. The existing stock hasher configuration
        // already has verify=false and rehash_on_login=false; do not change it.
        $user = $provider->retrieveByCredentials(['username' => $username]);
        if (! $user instanceof LegacyUser || $user->auth_source !== 'local'
            || $password === 'defaultpassword' || ! $provider->validateCredentials($user, ['password' => $password])) {
            return null;
        }
        $now = time();
        return JWT::encode([
            'iss' => 'localhost', 'aud' => 'localhost', 'iat' => $now, 'exp' => $now + 3600,
            'user_id' => (int) $user->getKey(), 'ver' => hash('sha256', $user->getAuthPassword()),
        ], $this->key->secret(), 'HS256');
    }
}
