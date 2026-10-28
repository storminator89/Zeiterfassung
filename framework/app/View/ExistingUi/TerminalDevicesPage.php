<?php

namespace App\View\ExistingUi;

/** Prepared read-only display only; no raw pairing/credential or authorization data. */
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
    ) {}
}
