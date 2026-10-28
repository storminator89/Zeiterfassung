<?php

namespace App\View\ExistingUi;

use InvalidArgumentException;
use LogicException;

/** Bounded fixed-code result only; the caller must establish the actual commit. */
final readonly class LdapSyncOutcome
{
    private const REASONS = ['ambiguous_identity', 'invalid_identity_dn', 'invalid_manager_dn',
        'unknown_manager', 'ambiguous_manager', 'self_manager', 'cyclic_manager'];

    private function __construct(public int $conflictCount, public array $conflicts) {}

    /** Only the trusted synchronize() return value, never query/request/session raw plans. */
    public static function fromCommittedPlan(#[\SensitiveParameter] array $plan): self
    {
        if (count($plan) !== 2 || array_diff(array_keys($plan), ['assignments', 'conflicts']) !== []
            || ! is_array($plan['assignments'] ?? null) || ! is_array($plan['conflicts'] ?? null)
            || count($plan['assignments']) > 10000 || count($plan['conflicts']) > 10000) {
            throw new InvalidArgumentException('Invalid bounded LDAP sync outcome.');
        }
        foreach ($plan['assignments'] as $id => $manager) {
            if (! is_int($id) || $id < 1 || ($manager !== null && (! is_int($manager) || $manager < 1))) {
                throw new InvalidArgumentException('Invalid bounded LDAP sync outcome.');
            }
        }
        $details = [];
        foreach ($plan['conflicts'] as $id => $reason) {
            if (! is_int($id) || $id < 1 || ! in_array($reason, self::REASONS, true)) {
                throw new InvalidArgumentException('Invalid bounded LDAP sync outcome.');
            }
            if (count($details) < 20) {
                $details[] = ['id' => $id, 'reason' => $reason];
            }
        }

        return new self(count($plan['conflicts']), $details);
    }

    /** Explicit safe array for an actor-bound encrypted one-use result flash. */
    public function toDisplay(): array
    {
        return ['conflict_count' => $this->conflictCount, 'conflicts' => $this->conflicts];
    }

    /** Parse only that fixed display shape; this does not prove origin or a commit. */
    public static function fromDisplay(#[\SensitiveParameter] array $display): self
    {
        if (count($display) !== 2 || array_diff(array_keys($display), ['conflict_count', 'conflicts']) !== []
            || ! is_int($display['conflict_count'] ?? null) || $display['conflict_count'] < 0 || $display['conflict_count'] > 10000
            || ! is_array($display['conflicts'] ?? null) || ! array_is_list($display['conflicts'])
            || count($display['conflicts']) !== min(20, $display['conflict_count'])) {
            throw new InvalidArgumentException('Invalid bounded LDAP sync outcome.');
        }
        $seen = [];
        foreach ($display['conflicts'] as $detail) {
            if (! is_array($detail) || count($detail) !== 2 || array_diff(array_keys($detail), ['id', 'reason']) !== []
                || ! is_int($detail['id'] ?? null) || $detail['id'] < 1 || isset($seen[$detail['id']])
                || ! in_array($detail['reason'] ?? null, self::REASONS, true)) {
                throw new InvalidArgumentException('Invalid bounded LDAP sync outcome.');
            }
            $seen[$detail['id']] = true;
        }

        return new self($display['conflict_count'], $display['conflicts']);
    }

    public function hasConflicts(): bool
    {
        return $this->conflictCount > 0;
    }

    public function __debugInfo(): array
    {
        return ['conflict_count' => $this->conflictCount, 'displayed_conflicts' => count($this->conflicts)];
    }

    public function __serialize(): array
    {
        throw new LogicException('Use the explicit bounded display array, not object serialization.');
    }
}
