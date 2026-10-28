<?php

namespace App\Chrono;

/** Only the native optional module switch; never host configuration or pairing. */
interface LocalWebTerminalModuleSettings
{
    public const COMMAND_FIELDS = ['action', 'enabled'];

    public function save(array $command): void;
}
