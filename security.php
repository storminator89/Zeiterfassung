<?php
// Shared security bootstrap. Load before output and before opening a session.
if (defined('CHRONO_SECURITY_LOADED')) { return; }
define('CHRONO_SECURITY_LOADED', true);
umask(0077);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
require_once __DIR__ . '/lib/http_error_response.php';
function h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
require_once __DIR__ . '/lib/language.php';
$dataDir = getenv('APP_DATA_DIR') ?: dirname(__DIR__) . '/zeiterfassung-data';
if (!is_dir($dataDir) && !mkdir($dataDir, 0700, true) && !is_dir($dataDir)) {
    abort_request(503, 'Private data directory unavailable.');
}
$dataDir = realpath($dataDir);
$webRoot = realpath(__DIR__);
if ($dataDir === $webRoot || str_starts_with($dataDir, $webRoot . DIRECTORY_SEPARATOR)) {
    abort_request(503, 'APP_DATA_DIR must be outside the application web root.');
}
define('APP_DATA_DIR', $dataDir);
function app_secret(string $name): string {
    $path = APP_DATA_DIR . '/' . $name . '.key';
    $handle = fopen($path, 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) { abort_request(503, 'Secret storage unavailable.'); }
    $secret = stream_get_contents($handle);
    if ($secret === '') {
        $secret = bin2hex(random_bytes(32));
        if (fwrite($handle, $secret) !== strlen($secret) || !fflush($handle)) {
            abort_request(503, 'Secret storage unavailable.');
        }
        chmod($path, 0600);
    }
    flock($handle, LOCK_UN);
    fclose($handle);
    if (!preg_match('/^[a-f0-9]{64}$/D', $secret)) { abort_request(503, 'Invalid secret file.'); }
    return $secret;
}
define('JWT_SECRET', app_secret('jwt'));
define('ENCRYPTION_KEY', app_secret('encryption'));
define('ENCRYPTION_METHOD', 'aes-256-cbc');
if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: base-uri 'self'; object-src 'none'; frame-ancestors 'none'");
    header('Cache-Control: no-store');
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || getenv('APP_HTTPS') === '1';
    if ($secure) { header('Strict-Transport-Security: max-age=31536000'); }
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'terminal_api.php') { return; }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params(['httponly' => true, 'secure' => $secure, 'samesite' => 'Lax', 'path' => '/']);
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    $_SESSION['lang'] = initial_language($_SESSION['lang'] ?? null, $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null);
    $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
    $endpoint = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($endpoint !== 'api.php' && !in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD', 'OPTIONS'], true)) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
            abort_request(403, 'Invalid CSRF token. Reload the page.');
        }
    }
}
function require_admin(): void {
    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        abort_request(403, 'Administrator access required.');
    }
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . h($_SESSION['csrf_token']) . '">';
}
function csv_safe($value): string {
    require_once __DIR__ . '/lib/csv_safety.php';
    return csv_safe_text($value);
}

// Counters live in SQLite, so changing cookies does not reset the login budget.
function throttle_login(PDO $conn, string $username): void {
    $conn->exec('CREATE TABLE IF NOT EXISTS login_attempts (bucket TEXT PRIMARY KEY, starts INTEGER NOT NULL, attempts INTEGER NOT NULL)');
    $now = time();
    $conn->prepare('DELETE FROM login_attempts WHERE starts < ?')->execute([$now - 600]);
    foreach (['ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'local') => 60, 'user:' . strtolower($username) => 15] as $name => $limit) {
        $key = hash('sha256', $name);
        $stmt = $conn->prepare('INSERT INTO login_attempts(bucket, starts, attempts) VALUES (?, ?, 1) ON CONFLICT(bucket) DO UPDATE SET attempts=attempts+1');
        $stmt->execute([$key, $now]);
        $stmt = $conn->prepare('SELECT attempts FROM login_attempts WHERE bucket=?');
        $stmt->execute([$key]);
        if ($stmt->fetchColumn() > $limit) {
            header('Retry-After: 600');
            abort_request(429, 'Too many login attempts. Try again later.');
        }
    }
}
