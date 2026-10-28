<?php

namespace App\Chrono;

/** Only the existing revoke_device form; never pairing, module or credential commands. */
interface LocalWebTerminalDeviceRevocations
{
    public const COMMAND_FIELDS = ['action', 'id'];

    public function revoke(array $command): void;
}
