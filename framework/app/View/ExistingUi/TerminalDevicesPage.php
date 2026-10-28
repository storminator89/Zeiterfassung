<?php

namespace App\View\ExistingUi;

/** Prepared display flags plus an optional ephemeral confirmed enrollment; never key/hash data. */
final readonly class TerminalDevicesPage
{
    /** @param list<array{id: int, name: string, site: string, permission_label: string, contact_state: string, contact_label: string, last_seen_label: string}> $devices */
    public function __construct(
        public array $devices,
        public int $contactMaxAge,
        public bool $siteCreationEnabled = false,
        public ?string $error = null,
        public bool $saved = false,
        /** Selected display fields only; never raw credential rows or hashes. */
        public array $credentials = [],
        public bool $credentialRevocationsEnabled = false,
        public bool $deviceRevocationsEnabled = false,
        public bool $moduleSettingsEnabled = false,
        public bool $moduleServerEnabled = false,
        public bool $moduleEnabled = false,
        public bool $devicePairingsEnabled = false,
        /** Name-ordered nonsecret Site options; checked again by the writer. */
        public array $pairingSites = [],
        #[\SensitiveParameter] public ?\App\Chrono\TerminalDevicePairing $pairing = null,
    ) {}
}
