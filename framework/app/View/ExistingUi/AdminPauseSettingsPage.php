<?php

namespace App\View\ExistingUi;

/** Authorized current Admin display rows only; never an account model or writer. */
final readonly class AdminPauseSettingsPage
{
    /**
     * @param list<array{id: int, hours_threshold: mixed, minimum_pause: mixed}> $rules Native values, unescaped.
     * @param list<array{id: int, name: mixed}> $departments Native values/order, unescaped.
     * @param array $users Native ten-row page projection; explicit UI-only columns.
     * @param list<array{id: int, username: mixed}> $supervisors Native complete create options.
     * @param array{username: ?string, email: ?string, regelarbeitszeit: ?string, ueberstunden: ?string}|null $createUserDraft Known required-field refusal only; never credentials or assignments.
     */
    public function __construct(
        public array $rules,
        public ?string $error = null,
        public bool $enabled = false,
        public array $departments = [],
        public array $users = [],
        public array $supervisors = [],
        public ?string $success = null,
        public ?array $intakePolicy = null,
        public ?LdapSyncForm $ldapSyncForm = null,
        public ?LdapSyncOutcome $ldapSyncOutcome = null,
        public ?string $ldapSyncNotice = null,
        public array $ldapSessionConfirmations = [],
        public ?string $ldapSessionNotice = null,
        public array $lifecycleUsers = [],
        public ?string $lifecycleNotice = null,
        public bool $accountLifecycleV5 = false,
        public ?array $createUserDraft = null,
    ) {}
}
