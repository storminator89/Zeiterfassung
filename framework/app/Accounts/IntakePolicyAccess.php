<?php

namespace App\Accounts;

use App\Auth\LegacyUser;
use App\Auth\AccountLifecycleWebIdentity;
use App\LegacyDatabase\AttendanceWriter;
use Closure;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\SQLiteConnection;
use InvalidArgumentException;
use LogicException;
use PDO;
use RuntimeException;
use Throwable;

/** Explicit immutable V4 composition; never a policy default inferred from a request or missing row. */
final readonly class IntakePolicyAccess
{
    /** @param Closure(): AttendanceWriter $writer Existing trusted operation-scope resolver. */
    public function __construct(
        private bool $v4,
        private Factory $auth,
        private DatabaseManager $database,
        private IntakePolicyStore $policies,
        private Closure $writer,
        private ?AccountLifecycleWebIdentity $lifecycle = null,
    ) {
        if ($v4 && $this->hasGenerationBoundAccountAuthority()) { throw new LogicException('V5 intake must not be aliased into V4.'); }
    }

    public function isV4(): bool
    {
        return $this->v4;
    }

    public function isV5(): bool
    {
        return $this->hasGenerationBoundAccountAuthority() && !$this->policies->isVacationWorkflowV7();
    }

    public function hasGenerationBoundAccountAuthority(): bool
    {
        return $this->lifecycle?->requiresGeneration() ?? false;
    }

    private function selected(): bool
    {
        return $this->v4 || $this->hasGenerationBoundAccountAuthority();
    }

    /** Legacy profiles have no policy card, not a fabricated permitted V4 policy. */
    public function current(): ?IntakePolicy
    {
        return $this->selected() ? $this->state($this->localAdmin())['policy'] : null;
    }

    /** Only service-internal guard context; never forward this array to a view or request. */
    public function manualContext(): ?array
    {
        if (! $this->selected()) {
            return null;
        }
        $state = $this->state($this->localAdmin());
        $this->requireAllowed($state['policy'], IntakeMethod::Manual);

        $context = ['user' => $state['user'], 'version' => $state['version'], 'revision' => $state['policy']->revision];
        if ($state['authority'] !== null) { $context['lifecycle_authority'] = $state['authority']; }
        return $context;
    }

    /** Public registration learns only availability/revision, never Admin/audit/other policy metadata. */
    public function registrationRevision(): ?int
    {
        if (! $this->selected()) {
            return null;
        }
        $policy = $this->state()['policy'];
        $this->requireAllowed($policy, IntakeMethod::Registration);

        return $policy->revision;
    }

    public function change(array $form): void
    {
        if (! $this->selected()) {
            throw new InvalidArgumentException('Aufnahmeauswahl ist in diesem Anwendungsprofil nicht verfügbar.');
        }
        $authenticated = $this->localAdmin();
        $change = IntakePolicyChange::fromForm($form);
        $state = $this->state($authenticated);
        if ($state['policy']->revision !== $change->expectedRevision) {
            throw new DomainException('Die Aufnahmewege wurden geändert. Bitte neu laden und erneut bewusst wählen.');
        }
        try {
            $writer = ($this->writer)();
            if (! $writer instanceof AttendanceWriter) {
                throw new LogicException;
            }
            $writer->changeIntakePolicy($state['user'], $state['version'], $change, $state['authority']);
        } catch (\AttendanceError $error) {
            if ($error->status === 401) {
                throw new AuthenticationException('Unauthenticated.', ['web']);
            }
            throw new RuntimeException('Die Änderung der Aufnahmewege konnte nicht bestätigt werden.');
        } catch (AuthenticationException|AuthorizationException|InvalidArgumentException|DomainException $error) {
            throw $error;
        } catch (Throwable) {
            throw new RuntimeException('Die Änderung der Aufnahmewege konnte nicht bestätigt werden.');
        }
    }

    private function localAdmin(): LegacyUser
    {
        try {
            $actor = $this->auth->guard('web')->user();
        } catch (Throwable) {
            throw new RuntimeException('Die Verwaltungssitzung konnte nicht bestätigt werden.');
        }
        if (! $actor instanceof LegacyUser || (int) $actor->getKey() < 1 || $actor->auth_source !== 'local'
            || ! is_string($actor->getAuthPassword()) || $actor->getAuthPassword() === '') {
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }
        if ($actor->role !== 'admin') {
            throw new AuthorizationException('Nur die lokale Verwaltung darf Aufnahmewege verwalten.');
        }

        return $actor;
    }

    /** One idle admitted named read snapshot; no network, key, writer or path resolution. */
    private function state(#[\SensitiveParameter] ?LegacyUser $authenticated = null): array
    {
        $authority = $authenticated === null ? null : $this->lifecycle?->capture($authenticated);
        try {
            $connection = $this->database->connection('chrono');
            if (! $connection instanceof SQLiteConnection || $connection->transactionLevel() !== 0) {
                throw new LogicException;
            }
            $pdo = $connection->getPdo();
            if ($pdo->inTransaction()) {
                throw new LogicException;
            }
            $user = $authenticated === null ? null : (int) $authenticated->getKey();
            $version = $authenticated === null ? null : hash('sha256', $authenticated->getAuthPassword());

            return $connection->transaction(function () use ($connection, $pdo, $user, $version, $authority): array {
                if ($connection->getRawPdo() !== $pdo) {
                    throw new LogicException;
                }
                if ($user !== null) {
                    $statement = $pdo->prepare($this->hasGenerationBoundAccountAuthority()
                        ? 'SELECT id,username,role,auth_source,password,account_status,auth_generation FROM users WHERE id=?'
                        : 'SELECT role,auth_source,password FROM users WHERE id=?');
                    $statement->execute([$user]);
                    $actor = $statement->fetch(PDO::FETCH_ASSOC);
                    $statement->closeCursor();
                    if ($actor === false || $actor['auth_source'] !== 'local' || ! is_string($actor['password'])
                        || $actor['password'] === '' || ! hash_equals($version, hash('sha256', $actor['password']))
                        || ($this->hasGenerationBoundAccountAuthority() && ($authority === null || ! $authority->matches($actor)))) {
                        throw new AuthenticationException('Unauthenticated.', ['web']);
                    }
                    if ($actor['role'] !== 'admin') {
                        throw new AuthorizationException('Nur die aktuelle lokale Verwaltung darf Aufnahmewege verwalten.');
                    }
                    if ($this->hasGenerationBoundAccountAuthority() && ! AccountLifecycleAuthority::locallyLoginEligibleAdmin($actor)) {
                        throw new AuthenticationException('Unauthenticated.', ['web']);
                    }
                }
                $policy = $this->policies->readForAccountAuthority($pdo, $this->hasGenerationBoundAccountAuthority());
                if ($connection->getRawPdo() !== $pdo) {
                    throw new LogicException;
                }

                return compact('policy', 'user', 'version', 'authority');
            }, 1);
        } catch (AuthenticationException|AuthorizationException $error) {
            throw $error;
        } catch (Throwable) {
            throw new RuntimeException('Die Aufnahmewege konnten nicht bestätigt werden.');
        }
    }

    private function requireAllowed(IntakePolicy $policy, IntakeMethod $method): void
    {
        if (! $policy->allows($method)) {
            throw new AuthorizationException('Dieser Aufnahmeweg ist deaktiviert.');
        }
    }
}
