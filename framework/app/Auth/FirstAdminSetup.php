<?php

namespace App\Auth;

use App\LegacyDatabase\AttendanceWriter;
use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Hashing\Hasher;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Operator-token-authorized first local admin; not public registration or enrollment. */
final class FirstAdminSetup
{
    /** @param Closure(): AttendanceWriter $writer Explicit admitted operation resolver. */
    public function __construct(private Repository $config, private Hasher $hasher, private Closure $writer) {}

    /** Pure configuration check: no source, session, user count or token is exposed. */
    public function assertConfigured(): void
    {
        $this->tokenDigest();
    }

    public function create(#[\SensitiveParameter] array $form): void
    {
        $digest = $this->tokenDigest();
        $token = $form['setup_token'] ?? null;
        if (! is_string($token) || strlen($token) < 32 || strlen($token) > 256
            || preg_match('/[^\x21-\x7e]/', $token)
            || ! hash_equals($digest, hash('sha256', $token))) {
            throw new AccessDeniedHttpException;
        }
        if (array_diff(array_keys($form), ['username', 'email', 'password', 'password_confirmation', 'setup_token']) !== []) {
            throw new InvalidArgumentException('Invalid setup fields.');
        }
        foreach (['username', 'email', 'password', 'password_confirmation'] as $field) {
            if (! is_string($form[$field] ?? null)) {
                throw new InvalidArgumentException('Invalid setup fields.');
            }
        }
        $username = trim($form['username']);
        $email = trim($form['email']);
        $password = $form['password']; // Never trim or normalize a chosen password.
        if ($username === '' || strlen($username) > 100 || ! mb_check_encoding($username, 'UTF-8')
            || preg_match('/[\x00-\x1f\x7f]/', $username)
            || strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || strlen($password) < 12 || strlen($password) > 72 || $password === 'defaultpassword'
            || preg_match('/[\x00-\x1f\x7f]/', $password)
            || strlen($form['password_confirmation']) > 72
            || ! hash_equals($password, $form['password_confirmation'])) {
            throw new InvalidArgumentException('Invalid setup fields.');
        }

        // Invalid/unprovisioned tokens never hash passwords or resolve a writer.
        $hash = $this->hasher->make($password);
        $writer = ($this->writer)();
        if (! $writer instanceof AttendanceWriter) {
            throw new LogicException('An admitted operation-scoped setup writer is required.');
        }
        // Existing/nonempty and newly initialized have the same generic PRG.
        $writer->firstLocalAdministrator($username, $email, $hash);
    }

    /** Digest supplied by trusted composition, not inherited native keys or a request. */
    private function tokenDigest(): string
    {
        $digest = $this->config->get('chrono_setup.token_sha256');
        if ($this->config->get('chrono_setup.enabled', false) !== true
            || $this->config->get('app.debug', false) !== false
            || $this->config->get('hashing.driver') !== 'bcrypt'
            || $this->config->get('auth.providers.chrono_users') !== ['driver' => 'eloquent', 'model' => LegacyUser::class]
            || ! is_string($digest) || ! preg_match('/^[a-f0-9]{64}$/D', $digest)) {
            throw new NotFoundHttpException;
        }
        return $digest;
    }
}
