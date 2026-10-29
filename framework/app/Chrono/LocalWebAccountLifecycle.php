<?php

namespace App\Chrono;

use App\Accounts\AccountLifecycleTransitionResult;

/** Dormant explicit V5 Admin flow. No default binding, route or runtime capability. */
interface LocalWebAccountLifecycle
{
    public const COMMAND_FIELDS = ['change_account_lifecycle', 'user_id', 'confirmation', 'reason'];

    /** Current display state and action-bound confirmations for the existing ten-row page. */
    public function forUsers(array $identifiers): array;

    /** Only a confirmed COMMIT returns a result. Never retry an uncertain outcome. */
    public function change(#[\SensitiveParameter] array $command): AccountLifecycleTransitionResult;
}
