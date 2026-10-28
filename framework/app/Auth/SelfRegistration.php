<?php

namespace App\Auth;

use App\LegacyDatabase\AttendanceWriter;
use App\Accounts\IntakePolicyAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Hashing\Hasher;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Explicit ordinary-local-account operation; no enrollment, login or admin actor. */
final class SelfRegistration
{
    /** @param Closure(): AttendanceWriter $writer Trusted admitted operation resolver. */
    public function __construct(private Repository $config, private Hasher $hasher, private Closure $writer,
        private ?IntakePolicyAccess $intake = null) {}

    public function available(): bool
    {
        if ($this->config->get('chrono_registration.enabled', false) !== true) {
            return false;
        }
        try {
            $this->intake?->registrationRevision();
            return true;
        } catch (AuthorizationException) {
            return false;
        }
    }

    public function register(#[\SensitiveParameter] array $form): void
    {
        if ($this->config->get('chrono_registration.enabled', false) !== true) {
            throw new NotFoundHttpException;
        }
        // V4 policy is checked before hashing; the writer rechecks this revision under its lock.
        $revision = $this->intake?->registrationRevision();
        if (array_diff(array_keys($form), ['username', 'email', 'password', 'password_confirmation']) !== []) {
            throw new InvalidArgumentException('Invalid registration fields.');
        }
        foreach (['username', 'email', 'password', 'password_confirmation'] as $field) {
            if (! is_string($form[$field] ?? null)) {
                throw new InvalidArgumentException('Invalid registration fields.');
            }
        }
        $username = trim($form['username']);
        $email = trim($form['email']);
        $password = $form['password']; // Never trim/coerce a password.
        if ($username === '' || strlen($username) > 100 || ! mb_check_encoding($username, 'UTF-8')
            || preg_match('/[\x00-\x1f\x7f]/', $username)
            || strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || strlen($password) < 12 || strlen($password) > 72 || $password === 'defaultpassword'
            || preg_match('/[\x00-\x1f\x7f]/', $password)
            || strlen($form['password_confirmation']) > 72
            || ! hash_equals($password, $form['password_confirmation'])) {
            throw new InvalidArgumentException('Invalid registration fields.');
        }

        if ($this->config->get('hashing.driver') !== 'bcrypt') {
            throw new LogicException('This registration profile requires the configured bcrypt hasher.');
        }
        // Standard configured Laravel hasher, outside the SQLite writer lock.
        $hash = $this->hasher->make($password);
        $writer = ($this->writer)();
        if (! $writer instanceof AttendanceWriter) {
            throw new LogicException('An admitted operation-scoped writer is required.');
        }
        // Deliberately discard new/duplicate distinction: no availability oracle.
        $writer->registerLocalUser($username, $email, $hash, expectedPolicyRevision: $revision);
    }
}
