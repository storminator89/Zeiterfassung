<?php

namespace App\Policies;

use App\Auth\LegacyUser;

final class LegacyUserPolicy
{
    public function view(LegacyUser $actor, LegacyUser $subject): bool
    {
        return (int) $actor->getKey() === (int) $subject->getKey()
            || $actor->role === 'admin'
            || ($actor->role === 'supervisor' && (int) $subject->supervisor_id === (int) $actor->getKey());
    }

    public function reviewCorrection(LegacyUser $actor, LegacyUser $subject): bool
    {
        // No global admin `before` bypass: even admins cannot approve themselves.
        return (int) $actor->getKey() !== (int) $subject->getKey()
            && ($actor->role === 'admin'
                || ($actor->role === 'supervisor' && (int) $subject->supervisor_id === (int) $actor->getKey()));
    }
}
