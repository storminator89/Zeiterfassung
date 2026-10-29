<?php
// Bounded source/component check. Invented in-memory rows only; no bootstrap,
// listener, LDAP, credentials, session files, application keys or operating DB.
namespace ChronoLdapSessionProof;

use PDO;
use RuntimeException;
use Throwable;

require_once dirname(__DIR__).'/lib/native_ldap_session.php';
require_once dirname(__DIR__).'/lib/ldap_import.php';
require_once dirname(__DIR__).'/lib/api_tokens.php';

final class Refused extends RuntimeException {
    public function __construct(public int $status) { parent::__construct('Expected refusal'); }
}
function abort_request(int $status, string $message): never { throw new Refused($status); }
function session_destroy(): bool { return true; } // Only the cookie/file transport is substituted.
function getallheaders(): array { return $GLOBALS['proof_headers']; }
function check(bool $ok, string $name): void {
    if (!$ok) { throw new RuntimeException($name); }
    $GLOBALS['proof_checks'][] = $name;
}
function refused(callable $operation, int $status): void {
    try { $operation(); } catch (Refused $error) {
        check($error->status === $status, 'expected refusal '.$status); return;
    }
    throw new RuntimeException('Missing refusal');
}
function source_function(string $source, string $name): string {
    $tokens = token_get_all($source); $capture = false; $started = false; $depth = 0; $result = '';
    foreach ($tokens as $i => $token) {
        if (is_array($token) && $token[0] === T_FUNCTION) {
            $j = $i + 1;
            while (isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) { $j++; }
            $capture = is_array($tokens[$j] ?? null) && $tokens[$j][1] === $name;
        }
        if (!$capture) { continue; }
        $result .= is_array($token) ? $token[1] : $token;
        if ($token === '{') { $started = true; $depth++; }
        if ($token === '}' && --$depth === 0 && $started) { return $result; }
    }
    throw new RuntimeException('Source function missing: '.$name);
}

$root = dirname(__DIR__);
$config = file_get_contents($root.'/config.php');
$begin = strpos($config, '// Refresh authorization on every request');
$end = strpos($config, 'require_once __DIR__ . "/lib/attendance.php";', $begin);
if ($begin === false || $end === false) { throw new RuntimeException('Session source boundary moved'); }
$guard = substr($config, $begin, $end - $begin);
$guard = str_replace("PHP_SAPI !== 'cli' && ", '', $guard, $replaced);
check($replaced === 1, 'only CLI transport guard omitted');
$refresh = eval('namespace ChronoLdapSessionProof; return function(\\PDO $conn, array &$session): void { $_SESSION =& $session; '.$guard.' };');
$api = file_get_contents($root.'/api.php');
foreach (['generateJWT', 'validateToken'] as $name) {
    eval('namespace ChronoLdapSessionProof; '.source_function($api, $name));
}
eval('namespace ChronoLdapSessionProof; '.source_function(file_get_contents($root.'/security.php'), 'require_admin'));
define(__NAMESPACE__.'\\JWT_SECRET', bin2hex(random_bytes(32))); // Ephemeral invented signing key, never output.
$conn = PDO::connect('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ]);
$GLOBALS['conn'] = $conn;
$conn->exec("CREATE TABLE users(id INTEGER PRIMARY KEY, username TEXT NOT NULL UNIQUE, password TEXT NOT NULL, email TEXT NOT NULL UNIQUE, role TEXT NOT NULL DEFAULT 'user', auth_source TEXT NOT NULL DEFAULT 'local', department_id INTEGER, supervisor_id INTEGER);
CREATE TABLE departments(id INTEGER PRIMARY KEY, name TEXT UNIQUE);
CREATE TABLE ldap_settings(id INTEGER PRIMARY KEY, ldap_host TEXT, ldap_port TEXT, ldap_user TEXT, ldap_pass TEXT, ldap_base_dn TEXT);
CREATE TABLE audit_log(id INTEGER PRIMARY KEY AUTOINCREMENT, occurred_at INTEGER NOT NULL, actor TEXT NOT NULL, reason TEXT NOT NULL, entity TEXT NOT NULL, record_id INTEGER NOT NULL, user_id INTEGER NOT NULL, operation TEXT NOT NULL, before_json TEXT, after_json TEXT);
CREATE TABLE changes(kind TEXT);
CREATE TRIGGER user_insert AFTER INSERT ON users BEGIN INSERT INTO changes VALUES('insert'); END;
CREATE TRIGGER user_update AFTER UPDATE ON users BEGIN INSERT INTO changes VALUES('update'); END;");
// The shared import now requires its original audit sink/context even in this tiny fixture.
$conn->createFunction('audit_actor', static fn(): string => 'synthetic:ldap-session', 0);
$conn->createFunction('audit_reason', static fn(): string => 'Synthetic LDAP session proof', 0);
$session = static fn(object $user): array => ['user_id'=>$user->id, 'username'=>$user->username, 'role'=>$user->role, 'auth_version'=>hash('sha256', $user->password)];
$token = static fn(object $user): string => generateJWT(['alg'=>'HS256','typ'=>'JWT'], ['iss'=>'localhost','aud'=>'localhost','iat'=>time(),'exp'=>time()+3600,'user_id'=>(int)$user->id,'ver'=>hash('sha256',$user->password)], JWT_SECRET);
$useToken = static function(string $jwt): array { $GLOBALS['proof_headers'] = ['Authorization'=>'Bearer '.$jwt]; return validateToken(); };
$row = static function(string $username) use ($conn): object { $s=$conn->prepare('SELECT * FROM users WHERE username=?'); $s->execute([$username]); $r=$s->fetch(); $s->closeCursor(); return $r; };

$first = native_ldap_login_account($conn, 'directory-example', 'directory@example.invalid');
$a = $session($first); $oldJwt = $token($first);
$before = $conn->query('SELECT * FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$writes = $conn->query('SELECT COUNT(*) FROM changes')->fetchColumn();
$second = native_ldap_login_account($conn, 'directory-example', 'ignored-change@example.invalid');
$b = $session($second);
$refresh($conn, $a); $refresh($conn, $b);
check($a['auth_version'] === $b['auth_version'] && $first->password === $second->password, 'two independent session states retain one LDAP version');
check($conn->query('SELECT * FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $before && $conn->query('SELECT COUNT(*) FROM changes')->fetchColumn() === $writes, 'repeat LDAP login performs no row writes');
check($useToken($oldJwt)['user_id'] === $first->id, 'existing signed JWT survives unrelated second login');
check(!password_verify('invented-directory-password', $second->password) && $second->auth_source === 'ldap', 'no supplied LDAP password or local fallback stored');

$conn->exec("UPDATE users SET role='admin' WHERE username='directory-example'");
$refresh($conn, $a); require_admin();
$conn->exec("UPDATE users SET role='user' WHERE username='directory-example'");
$refresh($conn, $a); refused(static fn()=>require_admin(), 403);
$refresh($conn, $b);
check($a['role'] === 'user' && $b['role'] === 'user', 'actual role removal refreshed for both sessions');

// Deliberate credential/placeholder rotation uses the same users.password field
// as the native Admin edit. The unchanged real validators must reject old state.
$conn->prepare('UPDATE users SET password=? WHERE id=?')->execute([native_ldap_placeholder(), $first->id]);
refused(static function() use ($conn, &$a, $refresh): void { $refresh($conn, $a); }, 401);
refused(static function() use ($conn, &$b, $refresh): void { $refresh($conn, $b); }, 401);
refused(static fn()=>$useToken($oldJwt), 401);
check($a === [] && $b === [], 'real revocation clears both session states');
$again = native_ldap_login_account($conn, 'directory-example', 'directory@example.invalid');
$c = $session($again); $newJwt = $token($again); $refresh($conn, $c);
check($again->password !== $first->password && $useToken($newJwt)['user_id'] === $first->id, 'new directory login keeps new revocation generation');
refused(static fn()=>$useToken($oldJwt), 401);
$conn->prepare('DELETE FROM users WHERE id=?')->execute([$first->id]);
refused(static function() use ($conn, &$c, $refresh): void { $refresh($conn, $c); }, 401);
refused(static fn()=>$useToken($newJwt), 401);

$localHash = password_hash('invented-local-password', PASSWORD_DEFAULT);
$conn->prepare("INSERT INTO users(username,password,email,role) VALUES('convert-login',?,'convert-login@example.invalid','supervisor')")->execute([$localHash]);
$local = $row('convert-login'); $localSession = $session($local); $localJwt = $token($local);
$converted = native_ldap_login_account($conn, 'convert-login', 'ignored@example.invalid');
check($converted->id === $local->id && $converted->role === 'supervisor' && $converted->email === $local->email && $converted->password !== $localHash && !password_verify('invented-local-password', $converted->password), 'login conversion preserves identity and invalidates local password');
refused(static function() use ($conn, &$localSession, $refresh): void { $refresh($conn, $localSession); }, 401);
refused(static fn()=>$useToken($localJwt), 401);
check(native_ldap_login_account($conn, 'convert-login', 'ignored@example.invalid')->password === $converted->password, 'repeated converted login cannot rotate again');

// #28 reserves local Admin identities; #29 still applies to other conversions.
$conn->exec("INSERT INTO users(username,password,email,role) VALUES('reserved-local-admin','not-a-usable-hash','reserved-admin@example.invalid','admin')");
$recovery = $row('reserved-local-admin');
try { native_ldap_login_account($conn, 'reserved-local-admin', 'ignored@example.invalid'); throw new RuntimeException('Missing recovery refusal'); }
catch (\DomainException) { check(!$conn->inTransaction() && $row('reserved-local-admin') == $recovery, 'local Admin conversion refuses without changing identity'); }
check(native_local_recovery_admin($conn, 'reserved-local-admin') && !native_local_recovery_admin($conn, 'convert-login'), 'recovery routing is local Admin only');

$conn->prepare("INSERT INTO users(username,password,email,role) VALUES('convert-sync',?,'convert-sync@example.invalid','user')")->execute([$localHash]);
$syncLocal=$row('convert-sync'); $syncSession=$session($syncLocal); $syncJwt=$token($syncLocal);
$entry=['uid'=>['convert-sync'],'mail'=>['convert-sync@example.invalid'],'ou'=>['Example'],'dn'=>'uid=convert-sync,dc=example,dc=invalid'];
$sync = static function() use ($conn,$entry): void {
    $conn->beginTransaction();
    try { chrono_ldap_import_locked($conn, [$entry], 'synthetic.invalid', '636', 'inert', 'not-ciphertext', 'dc=example,dc=invalid'); $conn->commit(); }
    catch (Throwable $e) { if ($conn->inTransaction()) { $conn->rollBack(); } throw $e; }
};
$sync(); $syncConverted=$row('convert-sync');
check($syncConverted->auth_source === 'ldap' && $syncConverted->password !== $localHash && !password_verify('invented-local-password',$syncConverted->password), 'actual native sync conversion rotates local placeholder');
refused(static function() use ($conn, &$syncSession, $refresh): void { $refresh($conn, $syncSession); }, 401);
refused(static fn()=>$useToken($syncJwt), 401);
$sync();
check($row('convert-sync')->password === $syncConverted->password && native_ldap_login_account($conn,'convert-sync','ignored@example.invalid')->password === $syncConverted->password, 'repeat sync and login retain LDAP version');

$before=$conn->query('SELECT * FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
try { native_ldap_login_account($conn,'email-collision','convert-login@example.invalid'); throw new RuntimeException('Missing unique refusal'); }
catch (\PDOException) { check(!$conn->inTransaction() && $conn->query('SELECT * FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)===$before,'failed creation rolls back without changing accounts'); }
$conn->beginTransaction();
try { native_ldap_login_account($conn,'convert-login','ignored@example.invalid'); throw new RuntimeException('Missing ownership refusal'); }
catch (\LogicException) { check($conn->inTransaction(), 'nested transaction refused without rolling back caller'); }
$conn->rollBack();

// The same helper's explicit V5 arm must retain its stricter collision policy.
$conn->exec("ALTER TABLE users ADD COLUMN account_status TEXT NOT NULL DEFAULT 'active'; ALTER TABLE users ADD COLUMN auth_generation INTEGER NOT NULL DEFAULT 1; CREATE TABLE app_migrations(version INTEGER PRIMARY KEY); INSERT INTO app_migrations VALUES(1),(2),(3),(4),(5);");
$v5Before=$row('convert-sync');
$conn->beginTransaction();
chrono_ldap_import_locked($conn, [$entry], 'synthetic.invalid', '636', 'inert', 'not-ciphertext', 'dc=example,dc=invalid', true);
$conn->commit();
check($row('convert-sync') == $v5Before, 'explicit V5 repeat keeps retained identity and lifecycle');
$conn->prepare("INSERT INTO users(username,password,email,role) VALUES('v5-local',?,'v5-local@example.invalid','user')")->execute([$localHash]);
$v5Rows=$conn->query('SELECT * FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$conn->beginTransaction();
try {
    chrono_ldap_import_locked($conn, [['uid'=>['v5-local'],'mail'=>['v5-local@example.invalid']]], 'synthetic.invalid', '636', 'inert', 'not-ciphertext', 'dc=example,dc=invalid', true);
    throw new RuntimeException('Missing V5 collision refusal');
} catch (\DomainException) {
    check($conn->query('SELECT * FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)===$v5Rows, 'explicit V5 still refuses local conversion');
    $conn->rollBack();
}

$login=file_get_contents($root.'/login.php');
check(substr_count($login,'native_ldap_login_account(')===1 && strpos($login,'if (@ldap_bind(')<strpos($login,'native_ldap_login_account(') && strpos($login,'if ($entries["count"] > 0)')<strpos($login,'native_ldap_login_account('), 'native login hook remains after existing directory success');
check(str_contains($login,"\$user->auth_source === 'local'") && str_contains($api,"\$user['auth_source'] === 'local'"), 'both local password fallbacks remain source-restricted');
$hashes=[]; foreach (['login.php','lib/native_ldap_session.php','lib/ldap_import.php','config.php','security.php','api.php','lib/api_tokens.php'] as $path) { $hashes[$path]=hash_file('sha256',$root.'/'.$path); }
echo json_encode(['result'=>'PASS','runtime'=>PHP_VERSION,'sqlite'=>$conn->query('SELECT sqlite_version()')->fetchColumn(),'checks'=>$GLOBALS['proof_checks'],'source_sha256'=>$hashes,'limits'=>['Two session arrays, not two actual browsers','Directory bind/TLS is not executed','Session destruction transport is substituted; authorization body and JWT functions are actual source','No listener, operating data, session files or persistent keys']], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
