<?php

namespace App\Api;

use App\Accounts\AccountLifecycleAuthority;
use App\Auth\LegacyUser;
use App\LegacyDatabase\RuntimeDatabase;
use Closure;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use LogicException;

/** Pure explicit V5 API rules; never infer the profile from optional claims/columns. */
final readonly class AccountLifecycleApiIdentity
{
    private function __construct(private bool $v5, private ?RuntimeDatabase $runtime,
        private ?Closure $selectedRuntime = null) {}

    /** Immutable admitted profile metadata only; never opens the still-closed V5 source. */
    public static function fromRuntime(?RuntimeDatabase $runtime): self
    {
        return new self($runtime?->isAccountLifecycleV5() ?? false, $runtime);
    }

    /** Actual provider path: an early off selection cannot survive a later binding change. */
    public static function fromApplication(Container $container): self
    {
        $selected = static function () use ($container): ?RuntimeDatabase {
            if (! $container->bound(RuntimeDatabase::class)) { return null; }
            $runtime = $container->make(RuntimeDatabase::class);
            if (! $runtime instanceof RuntimeDatabase) { throw new LogicException('The API account profile changed.'); }

            return $runtime;
        };
        $runtime = $selected();

        return new self($runtime?->isAccountLifecycleV5() ?? false, $runtime, $selected);
    }

    public function requiresGeneration(): bool
    {
        $this->assertCurrent();
        return $this->v5;
    }

    public function admits(#[\SensitiveParameter] LegacyUser $user): bool
    {
        $this->assertCurrent();
        if (! $this->v5) { return true; }
        try {
            $this->capture($user);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /** Issue only from the credential-checked current model, never request/session claims. */
    public function claims(#[\SensitiveParameter] LegacyUser $user): array
    {
        $authority = $this->capture($user);

        return $authority === null ? [] : ['auth_generation' => $authority->generation];
    }

    /** Called only AFTER the maintained verifier and current password-version check. */
    public function accepts(#[\SensitiveParameter] LegacyUser $user, #[\SensitiveParameter] array $payload): bool
    {
        $this->assertCurrent();
        return ! $this->v5 || ($this->admits($user)
            && is_int($payload['auth_generation'] ?? null) && $payload['auth_generation'] >= 1
            && $payload['auth_generation'] === $user->auth_generation);
    }

    /** Trusted verified bearer guard model only. This is not a bearer credential. */
    public function capture(#[\SensitiveParameter] LegacyUser $user): ?AccountLifecycleAuthority
    {
        $this->assertCurrent();
        if (! $this->v5) { return null; }
        if (! $user->exists) {
            throw new InvalidArgumentException('A current active V5 API account snapshot is required.');
        }

        return AccountLifecycleAuthority::capture($user->getAttributes());
    }

    private function assertCurrent(): void
    {
        if ($this->selectedRuntime !== null && ($this->selectedRuntime)() !== $this->runtime) {
            throw new LogicException('The API account profile changed.');
        }
    }

    public function __debugInfo(): array
    {
        return ['source' => 'explicit API account lifecycle profile'];
    }

    public function __serialize(): array
    {
        throw new LogicException('API account profiles must not be serialized.');
    }
}
