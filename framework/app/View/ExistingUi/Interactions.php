<?php

namespace App\View\ExistingUi;

/** Trusted composition-only display capability, never request data or authorization. */
final readonly class Interactions
{
    public function __construct(
        public bool $login = false,
        public bool $attendance = false,
        public bool $logout = false,
        public bool $corrections = false,
        public bool $home = false,
        public bool $absences = false,
        public bool $workModels = false,
        public bool $history = false,
        public bool $supervisor = false,
        public bool $dashboard = false,
        public bool $adminPauseSettings = false,
        public bool $terminalSites = false,
        public bool $terminalCredentialRevocations = false,
        public bool $correctionWithdrawals = false,
        public bool $terminalDeviceRevocations = false,
        public bool $ldapSessionRevocations = false,
        public bool $terminalModuleSettings = false,
        public bool $terminalDevicePairings = false,
    ) {}
}
