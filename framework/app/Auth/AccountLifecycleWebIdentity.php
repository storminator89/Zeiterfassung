<?php

namespace App\Auth;

use App\Accounts\AccountLifecycleAuthority;
use App\LegacyDatabase\RuntimeDatabase;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** Pure admitted-profile identity rules; never infers V5 from optional row fields. */
final readonly class AccountLifecycleWebIdentity
{
    public const GENERATION_MARKER = 'chrono_web_account_generation_v5';

    private function __construct(private bool $v5) {}

    /** RuntimeDatabase exposes immutable admission metadata without opening its source. */
    public static function fromRuntime(?RuntimeDatabase $runtime): self
    {
        return new self($runtime?->hasGenerationBoundAccountAuthority() ?? false);
    }

    public function requiresGeneration(): bool
    {
        return $this->v5;
    }

    public function admits(#[\SensitiveParameter] ?Authenticatable $user): bool
    {
        if (! $this->v5) {
            return true; // Existing V2/V3/V4 eligibility remains with its current provider.
        }
        if (! $user instanceof LegacyUser || ! $user->exists) {
            return false;
        }
        try {
            AccountLifecycleAuthority::capture($user->getAttributes());

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /** Trusted refreshed guard model only; the command/session cannot supply authority. */
    public function capture(#[\SensitiveParameter] LegacyUser $user): ?AccountLifecycleAuthority
    {
        if (! $this->v5) {
            return null;
        }
        if (! $user->exists) {
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }
        try {
            return AccountLifecycleAuthority::capture($user->getAttributes());
        } catch (InvalidArgumentException) {
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }
    }

    /** Login only: never called by refresh to adopt an older session's missing marker. */
    public function establishSession(Request $request, #[\SensitiveParameter] LegacyUser $user): void
    {
        $authority = $this->capture($user);
        if ($authority !== null) {
            $request->session()->put(self::GENERATION_MARKER, [
                'user_id' => $authority->id, 'generation' => $authority->generation,
            ]);
        }
    }

    public function sessionMatches(Request $request, #[\SensitiveParameter] ?Authenticatable $user): bool
    {
        if (! $this->v5) {
            return true;
        }
        if (! $this->admits($user)) {
            return false;
        }
        $marker = $request->session()->get(self::GENERATION_MARKER);

        $source = $request->session()->get(LocalSession::SOURCE_MARKER);
        $password = $request->session()->get(LocalSession::PASSWORD_MARKER);

        return $request->session()->exists(self::GENERATION_MARKER)
            && is_array($marker) && array_keys($marker) === ['user_id', 'generation']
            && is_int($marker['user_id']) && $marker['user_id'] === $user->getKey()
            && is_int($marker['generation']) && $marker['generation'] >= 1
            && $marker['generation'] === $user->auth_generation
            && $request->session()->exists(LocalSession::SOURCE_MARKER)
            && is_string($source) && $source === $user->auth_source
            && $request->session()->exists(LocalSession::PASSWORD_MARKER)
            && is_string($password) && $password !== '';
    }
}
