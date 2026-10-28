<?php

namespace App\Chrono;

/** Only the existing revoke_credential form; never PINs, cards, keys or device commands. */
interface LocalWebTerminalCredentialRevocations
{
    public const COMMAND_FIELDS = ['action', 'user_id'];

    public function revoke(array $command): void;
}
