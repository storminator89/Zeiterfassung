<?php

namespace App\Chrono;

use App\Api\Management\{ApiAdminBudget,ApiManagementStatus,ApiPolicyChange,ApiPolicyOperations,ApiPolicyReadSnapshot,ApiPolicyState,ManagedApiCapability};
use App\Auth\{AccountLifecycleWebIdentity,LegacyUser};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Hashing\Hasher;
use InvalidArgumentException;
use LogicException;

/** Explicit local-Admin actions only; no environment, Docker, token or password mutation. */
final readonly class ManagedLocalWebApiManagement implements LocalWebApiManagement
{
    public function __construct(private Factory $auth, private Hasher $hasher, private Repository $config,
        private AccountLifecycleWebIdentity $lifecycle, private ManagedApiCapability $capability,
        private ApiPolicyReadSnapshot $policies, private ApiAdminBudget $budget, private ApiPolicyOperations $operations) {}

    public function status(?string $notice = null, string $language = 'de'): ApiManagementStatus
    {
        $this->actor();
        $policy = $this->policies->read();
        $legacy = $this->capability->legacyDefaultEnabled();
        $status = $legacy ? 'operator' : ($this->capability->files->inspect()['status'] ?? 'blocked');
        if ($status === 'prepared') { $status = 'ready'; }
        if ($policy->keyPrepared && $status === 'missing') { $status = 'blocked'; }
        return new ApiManagementStatus($policy, $status, $legacy, $notice, $language);
    }

    public function change(#[\SensitiveParameter] array $form, string $remoteAddress): ApiPolicyState
    {
        [$user, $authority] = $this->actor();
        $change = ApiPolicyChange::fromForm($form);
        if ($this->config->get('hashing.driver') !== 'bcrypt') { throw new LogicException('Configured bcrypt verification required.'); }
        $this->budget->consume((int) $user->getKey(), $remoteAddress);
        $hash = $user->getAuthPassword();
        if (! is_string($hash) || $hash === '' || ! $this->hasher->check($form['current_password'], $hash)) {
            throw new InvalidArgumentException('API-Aktion und aktuelles Passwort prüfen.');
        }
        // The captured hash/generation/role is rechecked under the only writer
        // BEFORE entropy, file reservation or policy mutation, together with CAS.
        return $this->operations->apply($authority, $change);
    }

    private function actor(): array
    {
        $this->capability->assertCurrent();
        $guard = $this->auth->guard('web'); $user = $guard->user();
        if (! $guard instanceof SessionGuard || ! $user instanceof LegacyUser || ! $user->exists || (int) $user->getKey() < 1) {
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }
        if ($user->auth_source !== 'local' || $user->role !== 'admin') {
            throw new AuthorizationException('API-Verwaltung erfordert lokale Administration.');
        }
        if (! $this->lifecycle->requiresGeneration()) { throw new LogicException('Current V5 local authority required.'); }
        return [$user, $this->lifecycle->capture($user)];
    }
    public function __debugInfo(): array { return ['source'=>'local API administration']; }
    public function __serialize(): array { throw new LogicException('API administration must not be serialized.'); }
}
