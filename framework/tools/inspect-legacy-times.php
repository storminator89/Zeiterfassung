<?php

// Definitions-only CLI. No artisan/bootstrap/app.php, providers, auth, .env,
// config.php, security.php, automatic target discovery or writer entry point.
use App\LegacyDatabase\LegacyTimeQuality;
use App\LegacyDatabase\OfflineFiles;
use App\LegacyDatabase\V2Preflight;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Connectors\SQLiteConnector;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\SQLiteConnection;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$target = null;
$offline = false;
$limit = 100;
$valid = true;
$seen = [];
foreach (array_slice($argv, 1) as $argument) {
    $key = explode('=', $argument, 2)[0];
    if (isset($seen[$key])) {
        $valid = false;
    }
    $seen[$key] = true;
    if ($argument === '--offline') {
        $offline = true;
    } elseif (str_starts_with($argument, '--target=')) {
        $target = substr($argument, 9);
    } elseif (preg_match('/^--limit=([1-9][0-9]{0,3})$/D', $argument, $match) && (int) $match[1] <= 1000) {
        $limit = (int) $match[1];
    } else {
        $valid = false;
    }
}
if (! $valid || ! $offline || ! is_string($target) || $target === '') {
    fwrite(STDERR, "Usage: php framework/tools/inspect-legacy-times.php --target=/private/offline/existing.sqlite --offline [--limit=100]\nExit: 0 limited check without findings; 2 review needed; 1 stopped; 64 invalid arguments. No result is a complete clean bill.\n");
    exit(64);
}

$pdo = null;
$phase = 'dependencies';
$exitCode = 1;
$failurePhase = null;
$output = null;
// Convert dependency/filesystem warnings to the same redacted stop result.
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (! (error_reporting() & $severity)) {
        return false; // Respect deliberate @lstat/@fopen admission probes.
    }
    throw new ErrorException('Diagnostic stopped.', 0, $severity, $file, $line);
});
try {
    require dirname(__DIR__).'/vendor/autoload.php';
    $phase = 'private_cold_file_admission';
    $files = new OfflineFiles([dirname(__DIR__, 2), dirname(__DIR__).'/public']);
    $path = $files->validate($target);
    $before = $files->snapshot($path);
    $pdo = $files->openReadOnly($path); // OPEN_READONLY, no CREATE, plus query_only.
    $pdo->beginTransaction();

    $phase = 'known_v2_preflight';
    $mapping = json_decode(file_get_contents(dirname(__DIR__, 2).'/docs/framework/schema-map.json'), true, 512, JSON_THROW_ON_ERROR);
    // Reuse the real source fixture and V2Preflight; no second schema/FK/
    // ownership validator. All reference DDL is anonymous memory only.
    $reference = (new SQLiteConnector)->connect(['database' => ':memory:', 'busy_timeout' => 5000]);
    $build = require dirname(__DIR__).'/database/fixtures/legacy_v2.php';
    $build($reference, $mapping, false);
    $tables = array_column($mapping['tables'], null, 'name');
    $reference->exec($tables['login_attempts']['create_sql']);
    $connection = new SQLiteConnection($reference, ':memory:', '', ['name' => 'chrono_quality_reference']);
    $repository = new DatabaseMigrationRepository(new ConnectionResolver(['chrono_quality_reference' => $connection]), V2Preflight::LEDGER);
    $repository->setSource('chrono_quality_reference');
    $repository->createRepository();
    $checked = (new V2Preflight($mapping))->inspect($pdo, $reference);

    $phase = 'time_quality_snapshot';
    $result = (new LegacyTimeQuality)->inspect($pdo, $limit);
    $runtime = ['php' => PHP_VERSION, 'sqlite' => $pdo->query('SELECT sqlite_version()')->fetchColumn(), 'timezone_data' => timezone_version_get()];
    $pdo->rollBack();
    $pdo = null;
    $phase = 'original_preservation_check';
    if ($files->snapshot($path) !== $before) {
        throw new RuntimeException('The inspected original changed.');
    }
    $output = json_encode($result + [
        'known_v2_preflight' => $checked,
        'original_file_unchanged' => true,
        'runtime' => $runtime,
        'limitations' => ['No complete data-quality or migration approval.', 'No mapping, repair, event reconstruction or UI integration.', 'Offline acknowledgement does not prove all external writers are stopped.'],
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
    $exitCode = $result['needs_review'] ? 2 : 0;
} catch (Throwable) {
    // Never publish native exceptions, SQL, DSNs, paths or business row values.
    $failurePhase = $phase;
} finally {
    try {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    } catch (Throwable) {
        $failurePhase = 'read_only_cleanup';
        $exitCode = 1;
    } finally {
        restore_error_handler();
    }
}
if ($failurePhase !== null) {
    fwrite(STDERR, json_encode(['status' => 'stopped', 'phase' => $failurePhase, 'read_only' => true, 'report_complete' => false], JSON_THROW_ON_ERROR)."\n");
} else {
    echo $output;
}
exit($exitCode);
