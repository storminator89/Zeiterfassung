<?php

namespace App\Api\Management;

use App\Accounts\AccountLifecycleAuthority;
use App\Api\ExistingApiFileSigningKey;
use App\LegacyDatabase\AttendanceWriter;
use Closure;
use Illuminate\Auth\AuthenticationException;
use LogicException;

/** One operator→admitted-writer operation; never an alternate PDO/transaction opener. */
final readonly class AdmittedApiPolicyOperations implements ApiPolicyOperations
{
    /** Trusted current writer/key resolvers only, not request callbacks or key values. */
    public function __construct(private ManagedApiCapability $capability, private Closure $writer,
        private Closure $legacyKey) {}
    public function apply(AccountLifecycleAuthority $actor, ApiPolicyChange $change): ApiPolicyState
    {
        $this->capability->assertCurrent();
        $legacy = $this->capability->legacyDefaultEnabled();
        $key = $legacy ? ($this->legacyKey)() : null;
        if ($legacy && ! $key instanceof ExistingApiFileSigningKey) { throw new LogicException('Existing API source required.'); }
        try {
            return $this->capability->files->withLock(function () use ($actor, $change, $legacy, $key): ApiPolicyState {
                $writer = ($this->writer)();
                if (! $writer instanceof AttendanceWriter) { throw new LogicException('Admitted operation-scoped writer required.'); }
                $this->capability->assertCurrent();
                return $writer->changeApiPolicy($actor, $change, $legacy, $this->capability->files, $key);
            });
        } catch (\AttendanceError $error) {
            if ($error->status === 401) { throw new AuthenticationException('Unauthenticated.', ['web']); }
            throw $error;
        }
    }
}
