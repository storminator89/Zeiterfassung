<?php
namespace ChronoLdapSettingsProof;

use PDO;
use RuntimeException;
use Throwable;

// Coarse controller-boundary proof with PDO-shaped fakes only. No native PDO connection,
// config.php, sessions,
// real LDAP implementation, network, server, credentials or account writes.
require_once __DIR__ . '/../languages/en.php';
const LDAP_OPT_PROTOCOL_VERSION = 17;
const LDAP_OPT_NETWORK_TIMEOUT = 20485;
const ENCRYPTION_KEY = 'unused-synthetic-marker';
const ENCRYPTION_METHOD = 'unused-synthetic-marker';

final class Fixture extends PDO
{
    public array $events = [];
    public string $saved = 'existing-configuration';
    public int $writes = 0;
    private bool $transaction = false;
    private array $snapshot = [];
    public function __construct(public array $case) {}
    public function boundary(string $stage): bool
    {
        $this->events[] = $stage;
        if (($this->case['throw'] ?? '') === $stage) { throw new RuntimeException('synthetic diagnostic must not surface'); }
        return ($this->case['fail'] ?? '') !== $stage;
    }
    public function prepare(string $sql, array $options = []): \PDOStatement|false { return new Statement($this, $sql); }
    public function query(string $sql, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false { return $this->prepare($sql); }
    public function beginTransaction(): bool
    {
        if (!$this->boundary('import-begin')) { return false; }
        $this->snapshot = [$this->saved, $this->writes];
        $this->transaction = true;
        return true;
    }
    public function commit(): bool
    {
        if (!$this->boundary('import-commit')) { return false; }
        $this->transaction = false;
        return true;
    }
    public function rollBack(): bool
    {
        if (!$this->boundary('import-rollback')) { return false; }
        [$this->saved, $this->writes] = $this->snapshot;
        $this->transaction = false;
        return true;
    }
    public function inTransaction(): bool { return $this->transaction; }
}

final class Statement extends \PDOStatement
{
    public function __construct(private Fixture $fixture, private string $sql) {}
    public function execute(?array $values = null): bool
    {
        if (str_starts_with($this->sql, 'UPDATE ldap_settings') || str_starts_with($this->sql, 'INSERT INTO ldap_settings')) {
            $this->fixture->boundary('save');
            $this->fixture->saved = 'validated-configuration';
            $this->fixture->writes++;
        } elseif (str_starts_with($this->sql, 'INSERT INTO audit_log')) {
            $this->fixture->events[] = 'audit'; // Same transaction; no setting value or credential echo.
        } elseif (!str_starts_with($this->sql, 'SELECT COUNT(*) FROM ldap_settings')
            && !str_starts_with($this->sql, 'SELECT id,ldap_host,ldap_port,')) {
            throw new RuntimeException('Unexpected write outside settings boundary');
        }
        return true;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        if (!str_starts_with($this->sql, 'SELECT id,ldap_host,ldap_port,')) { throw new RuntimeException('Unexpected read outside settings boundary'); }
        if (($this->fixture->case['existing'] ?? 1) === 0 && $this->fixture->writes === 0) { return false; }
        return ['id'=>1, 'ldap_host'=>'ldap://synthetic.invalid', 'ldap_port'=>389,
            'base_dn_present'=>1, 'bind_identity_present'=>1, 'credential_present'=>1,
            'base_dn_changed'=>0, 'bind_identity_changed'=>0];
    }
    public function closeCursor(): bool { return true; }
    public function fetchColumn(int $column = 0): mixed { return $this->fixture->case['existing'] ?? 1; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return []; }
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
// Only resolve the definitions-only import helper path for eval's test directory.
$controller = str_replace("__DIR__ . '/lib/ldap_import.php'", var_export(__DIR__ . '/../lib/ldap_import.php', true), $controller);
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
];
$mutationFailure = ['throw' => 'save', 'error' => LDAP_SYNC_FAILED];
$cleanupFailures = [
    ['throw' => 'free', 'error' => LDAP_SYNC_FAILED, 'after_encrypt' => true],
    ['throw' => 'unbind', 'error' => LDAP_SYNC_FAILED, 'after_encrypt' => true],
    ['fail' => 'free', 'error' => LDAP_SYNC_FAILED, 'after_encrypt' => true],
    ['fail' => 'unbind', 'error' => LDAP_SYNC_FAILED, 'after_encrypt' => true],
];
foreach (array_merge($failures, [$mutationFailure, ['existing' => 1], ['existing' => 0, 'ldaps' => true]], $cleanupFailures) as $case) {
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
        check($fixture->saved === 'existing-configuration' && $fixture->writes === 0 && !$fixture->inTransaction(), 'Failure must preserve settings and leave no owned transaction');
        if (($case['throw'] ?? '') === 'save') {
            check(in_array('import-begin', $events, true) && in_array('import-rollback', $events, true)
                && !in_array('import-commit', $events, true)
                && array_search('import-begin', $events, true) < array_search('save', $events, true)
                && array_search('save', $events, true) < array_search('import-rollback', $events, true), 'A database failure must roll back its owned import transaction');
        } else {
            check(!in_array('import-begin', $events, true), 'Failed preflight/encryption/cleanup must avoid import');
        }
        check(!$escaped && $error === $case['error'] && $successMessage === '', 'Failure must use the safe localized error surface');
        if (!in_array($case['throw'] ?? '', ['encrypt', 'save'], true) && !($case['after_encrypt'] ?? false)) {
            check(!in_array('encrypt', $events, true), 'Encrypt only after successful validation');
        }
    } else {
        check(!$escaped && $error === '' && $successMessage === LDAP_SYNC_SUCCESS && !$fixture->inTransaction(), 'Valid empty result must complete the existing path');
        check($fixture->writes === 1 && $fixture->saved === 'validated-configuration', 'Validated settings must be written once');
        check(array_search('entries', $events, true) < array_search('encrypt', $events, true)
            && array_search('encrypt', $events, true) < array_search('free', $events, true)
            && array_search('free', $events, true) < array_search('unbind', $events, true)
            && array_search('unbind', $events, true) < array_search('import-begin', $events, true)
            && array_search('import-begin', $events, true) < array_search('save', $events, true)
            && array_search('save', $events, true) < array_search('import-commit', $events, true), 'Validate, encrypt, finish transport, then save inside one import transaction');
        check(in_array('tls', $events, true) !== ($case['ldaps'] ?? false), 'Preserve mandatory StartTLS and existing LDAPS behavior');
    }
    $opened = in_array('connect', $events, true) && ($case['fail'] ?? '') !== 'connect';
    $hasResult = in_array('search', $events, true) && ($case['fail'] ?? '') !== 'search';
    check(count(array_filter($events, static fn($event) => $event === 'unbind')) === (int)$opened, 'Close every opened connection exactly once');
    check(count(array_filter($events, static fn($event) => $event === 'free')) === (int)$hasResult, 'Free every obtained result exactly once');
}
echo "PASS: coarse synthetic LDAP preflight/encryption/cleanup failures preserve settings; owned transaction rollback and validated update/insert order; no real LDAP, config, DB or credentials\n";
