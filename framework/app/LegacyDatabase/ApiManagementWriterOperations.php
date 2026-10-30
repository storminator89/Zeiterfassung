<?php

namespace App\LegacyDatabase;

use App\Accounts\AccountLifecycleAuthority;
use App\Api\ExistingApiFileSigningKey;
use App\Api\Management\{ApiManagementFiles,ApiPolicyChange,ApiPolicyJournal,ApiPolicyState};
use Closure;
use LogicException;

/** Fixed V5 API policy operation on AttendanceWriter's existing sole transaction/audit scope. */
trait ApiManagementWriterOperations
{
    public function changeApiPolicy(AccountLifecycleAuthority $actor, ApiPolicyChange $change,
        bool $legacyDefaultEnabled, ApiManagementFiles $files, ?ExistingApiFileSigningKey $legacyKey = null): ApiPolicyState
    {
        if (! $this->hasGenerationBoundAccountAuthority() || ($legacyDefaultEnabled && $legacyKey === null)) {
            throw new LogicException('Explicit V5 API policy/source required.');
        }
        return $this->executeLocked(function () use ($actor, $change, $legacyDefaultEnabled, $files, $legacyKey): ApiPolicyState {
            $files->requireLockedWriter($this->pdo);
            $this->assertLifecyclePrincipalLocked($actor, localAdmin: true);
            $journal = new ApiPolicyJournal($this->vacationAdmission);
            // Check current source/role/hash/generation AND expected policy revision
            // under this lock, before any new file reservation or key entropy.
            $journal->requireChangeLocked($this->pdo, $actor, $change, $legacyDefaultEnabled);
            $confirmed = false;
            if ($change->enabled) {
                if ($legacyDefaultEnabled) {
                    // The original explicit admitted key source is never replaced/adopted.
                    $secret = $legacyKey->secret();
                    unset($secret);
                    $confirmed = true;
                } else {
                    $result = $files->prepareLocked($this->pdo, $actor, $change, false);
                    $confirmed = ($result['key_confirmed'] ?? null) === true;
                }
            }
            $files->requireLockedWriter($this->pdo);
            return $journal->appendLocked($this->pdo, $actor, $change, $legacyDefaultEnabled, $confirmed);
        }, fn (Closure $transaction): mixed => $this->context->run('web:'.$actor->id,
            $change->enabled ? 'API: enable' : 'API: disable', $transaction), lifecycleReady: true);
    }

    /** All V5 API mutations/replays recheck new policy under the same writer lock. */
    private function requireApiOperatingPolicyLocked(): void
    {
        if (! $this->hasGenerationBoundAccountAuthority()) { throw new LogicException('V5 API policy guard required.'); }
        // No event preserves the original already-admitted legacy API behavior.
        // Managed first enable always appends policy before a token can be issued.
        if (! (new ApiPolicyJournal($this->vacationAdmission))->read($this->pdo, true)->enabled) {
            throw new \AttendanceError('API ist deaktiviert.', 503);
        }
    }
}
