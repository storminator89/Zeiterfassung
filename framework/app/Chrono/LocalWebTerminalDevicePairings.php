<?php

namespace App\Chrono;

/** Only the native new-device enrollment; not device-secret derivation or protocol pairing. */
interface LocalWebTerminalDevicePairings
{
    public const COMMAND_FIELDS = ['action', 'name', 'site_id'];

    public function create(array $command): TerminalDevicePairing;
}
