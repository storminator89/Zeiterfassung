<?php

namespace App\Auth;

/**
 * Unregistered directory boundary, not a bind implementation or login action.
 *
 * A separately admitted maintained connector must verify the exact uid using
 * mandatory TLS/StartTLS, LDAPv3, the existing timeout and safe DN/filter APIs,
 * then require a successful, unambiguous matching search result. It must neither
 * provision/write accounts nor fall back to a local password or anonymous bind.
 * Operational/TLS/search failures must throw, not become local-auth fallback.
 * No production implementation or container binding is supplied in this packet.
 */
interface LdapCredentialVerifier
{
    public function verify(string $username, #[\SensitiveParameter] string $password): bool;
}
