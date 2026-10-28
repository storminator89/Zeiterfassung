<?php

namespace App\Chrono;

/** Explicit local session invalidation only, never account deactivation. */
interface LocalWebLdapSessionRevocations
{
    public const COMMAND_FIELDS = ['revoke_ldap_sessions', 'user_id', 'confirmation', 'confirm'];

    /** Opaque confirmation tickets for at most the ten already-authorized table rows. */
    public function confirmations(array $identifiers): array;

    public function revoke(#[\SensitiveParameter] array $command): void;
}
