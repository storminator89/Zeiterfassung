<?php

namespace App\Chrono;

/** Own pending requests only; no reviewer, actor, status or snapshot inputs. */
interface LocalWebCorrectionWithdrawals
{
    public const COMMAND_FIELDS = ['action', 'id', 'withdraw_reason'];

    public function withdraw(array $command): void;
}
