<?php

namespace App\Chrono;

/** One existing site-create form only; no terminal module, device or credentials. */
interface LocalWebTerminalSites
{
    public const COMMAND_FIELDS = ['action', 'name'];

    public function create(array $command): void;
}
