<?php

namespace App\Auth;

use App\LegacyDatabase\AttendanceWriter;
use App\Setup\FirstAdminSetupFiles;
use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Hashing\Hasher;
use LogicException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** First admin via an explicit NEW local-install policy or the separate operator-token profile. */
final class FirstAdminSetup
{
    /** @param Closure(): AttendanceWriter $writer Explicit admitted operation resolver. */
    public function __construct(private Repository $config, private Hasher $hasher, private Closure $writer) {}

    /** No DB/session/user count is exposed; local mode rechecks private nonsecret metadata. */
    public function assertConfigured(): void
    {
        if ($this->localBrowser()) { return; }
        $this->tokenDigest();
    }

    /** Display only after the same configuration gate; never supplied by a request. */
    public function requiresOperatorToken(): bool { return ! $this->localBrowser(); }

    public function create(#[\SensitiveParameter] array $form): void
    {
        if ($this->localBrowser()) {
            FirstAdminSetupFiles::createBrowserAdministrator($this->config->get('chrono_setup.instance_directory'),
                $this->config->get('chrono_setup.origin'), $this->hasher, $form);
            return;
        }
        $digest = $this->tokenDigest();
        $token = $form['setup_token'] ?? null;
        if (! is_string($token) || strlen($token) < 32 || strlen($token) > 256
            || preg_match('/[^\x21-\x7e]/', $token)
            || ! hash_equals($digest, hash('sha256', $token))) {
            throw new AccessDeniedHttpException;
        }
        [$username, $email, $password] = FirstAdminSetupFields::validate(array_diff_key($form, ['setup_token' => true]));

        // Invalid/unprovisioned tokens never hash passwords or resolve a writer.
        $hash = $this->hasher->make($password);
        $writer = ($this->writer)();
        if (! $writer instanceof AttendanceWriter) {
            throw new LogicException('An admitted operation-scoped setup writer is required.');
        }
        // Existing/nonempty and newly initialized have the same generic PRG.
        $writer->firstLocalAdministrator($username, $email, $hash);
    }

    private function localBrowser(): bool
    {
        if ($this->config->get('chrono_setup.local_first_user', false) !== true) { return false; }
        if ($this->config->get('chrono_setup.enabled') !== true || $this->config->get('chrono_setup.token_sha256') !== null
            || $this->config->get('app.debug', false) !== false || $this->config->get('hashing.driver') !== 'bcrypt'
            || $this->config->get('auth.providers.chrono_users') !== ['driver' => 'eloquent', 'model' => LegacyUser::class]
            || ! is_string($this->config->get('chrono_setup.instance_directory'))
            || ! is_string($this->config->get('chrono_setup.origin'))) { throw new NotFoundHttpException; }
        try {
            if (FirstAdminSetupFiles::localBrowserConfiguration($this->config->get('chrono_setup.instance_directory'),
                $this->config->get('chrono_setup.origin')) !== $this->config->get('chrono_setup')) { throw new NotFoundHttpException; }
        } catch (\Throwable) { throw new NotFoundHttpException; }

        return true;
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
