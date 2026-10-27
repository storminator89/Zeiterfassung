<?php
namespace ChronoLdapSettingsProof;

use PDO;
use RuntimeException;
use Throwable;

// Coarse controller-boundary proof only. No config.php, sessions, PDO connection,
// real LDAP implementation, network, server, credentials or account writes.
require_once __DIR__ . '/../languages/en.php';
const LDAP_OPT_PROTOCOL_VERSION = 17;
const LDAP_OPT_NETWORK_TIMEOUT = 20485;
const ENCRYPTION_KEY = 'unused-synthetic-marker';
const ENCRYPTION_METHOD = 'unused-synthetic-marker';

final class Fixture
{
    public array $events = [];
    public string $saved = 'existing-configuration';
    public int $writes = 0;
    public function __construct(public array $case) {}
    public function boundary(string $stage): bool
    {
        $this->events[] = $stage;
        if (($this->case['throw'] ?? '') === $stage) { throw new RuntimeException('synthetic diagnostic must not surface'); }
        return ($this->case['fail'] ?? '') !== $stage;
    }
    public function prepare(string $sql): Statement { return new Statement($this, $sql); }
    public function query(string $sql): Statement { return $this->prepare($sql); }
    public function beginTransaction(): bool { return $this->boundary('import-begin'); }
    public function commit(): bool { return $this->boundary('import-commit'); }
    public function inTransaction(): bool { return false; }
}

final class Statement
{
    public function __construct(private Fixture $fixture, private string $sql) {}
    public function execute(array $values = []): bool
    {
        if (str_starts_with($this->sql, 'UPDATE ldap_settings') || str_starts_with($this->sql, 'INSERT INTO ldap_settings')) {
            $this->fixture->boundary('save');
            $this->fixture->saved = 'validated-configuration';
            $this->fixture->writes++;
        } elseif (!str_starts_with($this->sql, 'SELECT COUNT(*) FROM ldap_settings')) {
            throw new RuntimeException('Unexpected write outside settings boundary');
        }
        return true;
    }
    public function fetchColumn(): int { return $this->fixture->case['existing'] ?? 1; }
    public function fetchAll(int $mode): array { return []; }
}

// Namespaced stubs ensure that even a runtime with ext-ldap cannot contact LDAP.
function function_exists(string $name): bool
{
    global $fixture;
    return $name === 'ldap_connect' ? !($fixture->case['missing_extension'] ?? false) : \function_exists($name);
}
function ldap_connect($host, $port) { global $fixture; return $fixture->boundary('connect') ? new \stdClass() : false; }
function ldap_set_option($connection, $option, $value): bool { global $fixture; return $fixture->boundary('option'); }
function ldap_start_tls($connection): bool { global $fixture; return $fixture->boundary('tls'); }
function ldap_bind($connection, $user, $password): bool { global $fixture; return $fixture->boundary('bind'); }
function ldap_search($connection, $base, $filter, $attributes) { global $fixture; return $fixture->boundary('search') ? new \stdClass() : false; }
function ldap_parse_result($connection, $result, &$code): bool
{
    global $fixture;
    $code = ($fixture->case['partial'] ?? false) ? 4 : 0;
    return $fixture->boundary('parse');
}
function ldap_get_entries($connection, $result): array|false
{
    global $fixture;
    if (!$fixture->boundary('entries')) { return false; }
    return ($fixture->case['malformed'] ?? false) ? ['count' => 1] : ['count' => 0];
}
function ldap_free_result($result): bool { global $fixture; return $fixture->boundary('free'); }
function ldap_unbind($connection): bool { global $fixture; return $fixture->boundary('unbind'); }
function encryptData($password, $key, $method): string { global $fixture; $fixture->boundary('encrypt'); return 'synthetic-cipher-marker'; }
function abort_request($status, $message): never { throw new RuntimeException('Unexpected request abort'); }

function check(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

$source = file_get_contents($argv[1] ?? __DIR__ . '/../admin.php');
$start = strpos($source, '// LDAP-Synchronisation');
$end = strpos($source, '// Aktuellen Benutzer aus der Datenbank abrufen', $start);
check($start !== false && $end !== false, 'Native controller boundary must be present');
$controller = substr($source, $start, $end - $start);
// Only resolve the existing pure manager helper path for eval's test directory.
$controller = str_replace("__DIR__ . '/lib/ldap_manager_mapping.php'", var_export(__DIR__ . '/../lib/ldap_manager_mapping.php', true), $controller);
$controller = "namespace ChronoLdapSettingsProof; use PDO; use RuntimeException; use Throwable;\n" . $controller;

// One failure cohort proves preflight cannot change the previous saved state.
$failures = [
    ['fail' => 'tls', 'error' => LDAP_TLS_FAILED],
    ['invalid' => true, 'error' => LDAP_SETTINGS_INVALID],
    ['missing_extension' => true, 'error' => LDAP_EXTENSION_REQUIRED],
    ['fail' => 'connect', 'error' => LDAP_CONNECTION_FAILED],
    ['fail' => 'option', 'error' => LDAP_CONNECTION_FAILED],
    ['fail' => 'bind', 'error' => LDAP_BIND_FAILED],
    ['throw' => 'bind', 'error' => LDAP_BIND_FAILED],
    ['fail' => 'search', 'error' => LDAP_SEARCH_FAILED],
    ['fail' => 'parse', 'error' => LDAP_SEARCH_FAILED],
    ['partial' => true, 'error' => LDAP_SEARCH_FAILED],
    ['fail' => 'entries', 'error' => LDAP_SEARCH_FAILED],
    ['malformed' => true, 'error' => LDAP_SEARCH_FAILED],
    ['throw' => 'encrypt', 'error' => LDAP_SYNC_FAILED],
    ['throw' => 'save', 'error' => LDAP_SYNC_FAILED],
];
foreach (array_merge($failures, [['existing' => 1], ['existing' => 0, 'ldaps' => true], ['throw' => 'free'], ['throw' => 'unbind']]) as $case) {
    $fixture = new Fixture($case);
    $conn = $fixture;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    // These are non-usable fixed markers, never read from environment or files.
    $_POST = ['sync_ldap' => '1', 'ldap_host' => ($case['ldaps'] ?? false) ? 'ldaps://synthetic.invalid' : 'ldap://synthetic.invalid',
        'ldap_port' => '389', 'ldap_user' => 'synthetic-marker', 'ldap_pass' => ($case['invalid'] ?? false) ? '' : 'synthetic-marker', 'ldap_base_dn' => 'synthetic-marker'];
    $error = $successMessage = '';
    $escaped = false;
    try { eval($controller); } catch (Throwable $e) { $escaped = true; }
    $events = $fixture->events;
    if (isset($case['error'])) {
        check($fixture->saved === 'existing-configuration' && $fixture->writes === 0 && !in_array('import-begin', $events, true), 'Failed preflight must preserve settings and avoid import');
        check(!$escaped && $error === $case['error'] && $successMessage === '', 'Failure must use the safe localized error surface');
        if (!in_array($case['throw'] ?? '', ['encrypt', 'save'], true)) {
            check(!in_array('encrypt', $events, true), 'Encrypt only after successful validation');
        }
    } else {
        if (in_array($case['throw'] ?? '', ['free', 'unbind'], true)) {
            check(!$escaped && $error === LDAP_SYNC_FAILED && $successMessage === '', 'Cleanup exceptions must not expose diagnostics or skip remaining cleanup');
        } else {
            check(!$escaped && $error === '' && $successMessage === LDAP_SYNC_SUCCESS, 'Valid empty result must complete the existing path');
        }
        check($fixture->writes === 1 && $fixture->saved === 'validated-configuration', 'Validated settings must be written once');
        check(array_search('entries', $events, true) < array_search('encrypt', $events, true)
            && array_search('encrypt', $events, true) < array_search('save', $events, true)
            && array_search('save', $events, true) < array_search('import-begin', $events, true), 'Validate, encrypt, persist, then import');
        check(in_array('tls', $events, true) !== ($case['ldaps'] ?? false), 'Preserve mandatory StartTLS and existing LDAPS behavior');
    }
    $opened = in_array('connect', $events, true) && ($case['fail'] ?? '') !== 'connect';
    $hasResult = in_array('search', $events, true) && ($case['fail'] ?? '') !== 'search';
    check(count(array_filter($events, static fn($event) => $event === 'unbind')) === (int)$opened, 'Close every opened connection exactly once');
    check(count(array_filter($events, static fn($event) => $event === 'free')) === (int)$hasResult, 'Free every obtained result exactly once');
}
echo "PASS: coarse synthetic LDAP preflight failure cohort preserves settings; validated update/insert order and cleanup; no real LDAP, config, DB or credentials\n";
