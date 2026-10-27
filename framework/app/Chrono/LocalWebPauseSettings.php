<?php

namespace App\Chrono;

/** The single supported raw Admin command, not a general administration API. */
interface LocalWebPauseSettings
{
    public const COMMAND_FIELDS = ['update_pause_settings', 'hours_threshold', 'minimum_pause'];

    public function apply(array $command): void;
}
