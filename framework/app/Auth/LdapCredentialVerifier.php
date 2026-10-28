<?php

namespace App\Auth;

/**
 * Unregistered credential boundary; concrete verification is not a login action.
 *
 * A separately admitted maintained connector must verify the exact uid using
 * mandatory TLS/StartTLS, LDAPv3, the existing timeout and safe DN/filter APIs,
 * then require a successful, unambiguous matching search result. It must neither
 * provision/write accounts nor fall back to a local password or anonymous bind.
 * Operational/TLS/search failures must throw, not become local-auth fallback.
 * PhpLdapCredentialVerifier is explicit opt-in; no container/provider/guard
 * binding or production configuration is supplied.
 */
interface LdapCredentialVerifier
{
    public function verify(string $username, #[\SensitiveParameter] string $password): bool;
}
