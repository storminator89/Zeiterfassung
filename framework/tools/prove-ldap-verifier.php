<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || $argc !== 2) {
    http_response_code(404);
    exit(1);
}

// Explicit synthetic protocol probe, not an application login/DB/session action.
// Run only inside the separately authorized ephemeral lab's owned lifecycle.
use App\Auth\PhpLdapCredentialVerifier;

require dirname(__DIR__).'/app/Auth/LdapCredentialVerifier.php';
require dirname(__DIR__).'/app/Auth/PhpLdapCredentialVerifier.php';

$ensure = static function (bool $ok): void {
    if (! $ok) { throw new RuntimeException('Synthetic verifier contract failed.'); }
};
$state = $argv[1] ?? '';
try {
    $ensure($argc === 2 && is_string($state) && str_starts_with($state, '/')
        && realpath($state) === $state && basename($state) === 'state'
        && str_starts_with(basename(dirname($state)), 'zeiterfassung-ephemeral-ldap-lab-')
        && is_dir($state) && (fileperms($state) & 0777) === 0700
        && fileowner($state) === posix_geteuid() && extension_loaded('ldap')
        && ! defined('CHRONO_SECURITY_LOADED') && session_status() === PHP_SESSION_NONE);
    $secretsPath = $state.'/synthetic-credentials.json';
    $ensure(is_file($secretsPath) && realpath($secretsPath) === $secretsPath
        && fileowner($secretsPath) === posix_geteuid() && (fileperms($secretsPath) & 0077) === 0);
    $secrets = json_decode(file_get_contents($secretsPath), true, 512, JSON_THROW_ON_ERROR);
    $password = $secrets['ldap_worker'] ?? null;
    // The owned runner seeds this literal-entity password before starting slapd.
    $ensure(is_string($password) && str_contains($password, '&amp;'));
    $uri = 'ldap://127.0.0.1:1389';
    $base = 'dc=chrono,dc=test';
    $ca = $state.'/tls.crt';
    $globalBefore = [];
    foreach ([LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_CACERTFILE] as $option) {
        $value = null;
        $globalBefore[$option] = [ldap_get_option(null, $option, $value), $value];
    }
    $disabled = false;
    try { (new PhpLdapCredentialVerifier)->verify('ldap_worker', $password); }
    catch (RuntimeException) { $disabled = true; }
    $ensure($disabled);
    $verifier = new PhpLdapCredentialVerifier(true, $uri, $base, $ca);
    $ensure($verifier->verify('ldap_worker', $password));
    $ensure(! $verifier->verify('ldap_worker', str_replace('&amp;', '&', $password))
        && ! $verifier->verify('ldap_worker', ''));
    $untrusted = false;
    try {
        (new PhpLdapCredentialVerifier(true, $uri, $base, '/etc/ssl/certs/ca-certificates.crt'))
            ->verify('ldap_worker', $password);
    } catch (RuntimeException $error) {
        $ensure($error->getMessage() === 'LDAP verification is unavailable.' && $error->getPrevious() === null);
        $untrusted = true;
    }
    $ensure($untrusted && $verifier->verify('ldap_worker', $password));
    foreach ($globalBefore as $option => $before) {
        $value = null;
        $ensure([ldap_get_option(null, $option, $value), $value] === $before);
    }
    $ensure(! defined('CHRONO_SECURITY_LOADED') && session_status() === PHP_SESSION_NONE);
    unset($password, $secrets);
    echo json_encode([
        'passed' => true, 'runtime' => PHP_VERSION,
        'scope' => 'actual opt-in verifier against owned synthetic loopback StartTLS directory only',
        'enabled_real_bind_unique_uid_and_bound_dn' => true,
        'literal_entity_password_bytes_accepted_unchanged' => true,
        'changed_and_empty_passwords_refused' => true,
        'disabled_default_refused' => true,
        'untrusted_CA_refused_and_next_private_CA_still_valid' => true,
        'process_global_TLS_options_unchanged' => true,
        'app_DB_provider_guard_session_login_or_provisioning' => false,
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
} catch (Throwable) {
    // Never expose exception arguments, credentials, DNs or server diagnostics.
    fwrite(STDERR, "FAIL synthetic LDAP verifier protocol contract.\n");
    exit(1);
}
