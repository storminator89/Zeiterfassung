<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// One coarse destructive proof on freshly generated SYNTHETIC private files only.
// Not a route, application bootstrap, CI job, or arbitrary existing-DB test runner.
umask(0077);
$root = dirname(__DIR__, 2);
require $root.'/framework/vendor/autoload.php';
$app = require $root.'/framework/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\LegacyDatabase\AttendanceWriter;
use App\LegacyDatabase\AuditContext;
use App\LegacyDatabase\AuditFunctions;
use App\LegacyDatabase\OfflineFiles;
use App\LegacyDatabase\SourceSnapshot;
use Illuminate\Database\Connectors\SQLiteConnector;

function proofRequire(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function proofRefused(callable $operation, string $class): Throwable
{
    try {
        $operation();
    } catch (Throwable $error) {
        proofRequire($error instanceof $class, 'Unexpected failure type: '.$error::class);
        return $error;
    }
    throw new RuntimeException('Expected critical protection did not refuse.');
}

function proofWait(callable $condition): void
{
    $deadline = microtime(true) + 20;
    while (! $condition()) {
        proofRequire(microtime(true) < $deadline, 'Synthetic worker coordination timed out.');
        usleep(20000);
    }
}

function proofOpen(string $path): array
{
    global $root;
    $files = new OfflineFiles([$root, $root.'/framework/public']);
    $config = $files->readWriteConfiguration($path, $files->snapshot($path));
    $config['name'] = 'chrono_synthetic_write_proof';
    return [(new SQLiteConnector)->connect($config), $config];
}

function proofWriter(string $path): array
{
    global $app;
    [$pdo, $config] = proofOpen($path);
    return [$pdo, new AttendanceWriter($pdo, $config, $app->make(AuditContext::class), $app->make(AuditFunctions::class))];
}

function proofSpawn(string $directory, string $name, string $event): array
{
    $process = proc_open([PHP_BINARY, '-n', '-d', 'error_reporting=E_ALL', __FILE__, '--worker', $directory, $name, $event],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    proofRequire(is_resource($process), 'Synthetic worker did not start.');
    fclose($pipes[0]);
    return [$process, $pipes];
}

function proofJoin(array $worker): array
{
    [$process, $pipes] = $worker;
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proofRequire(proc_close($process) === 0 && $errors === '', 'Synthetic worker failed or emitted warnings.');
    return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
}

$phase = 'synthetic source generation';
try {
    if (($argv[1] ?? '') === '--worker') {
        [$directory, $name, $event] = array_slice($argv, 2);
        proofRequire(str_starts_with($directory, sys_get_temp_dir().'/chrono-sqlite-proof-')
            && is_file($directory.'/SYNTHETIC-ONLY'), 'Worker accepts only this proof scratch directory.');
        [$pdo, $writer] = proofWriter($directory.'/target.sqlite');
        file_put_contents($directory.'/'.$name.'.ready', 'ready');
        proofWait(fn () => is_file($directory.'/'.$name.'.go'));
        $started = microtime(true);
        try {
            $response = $writer->transition($name === 'busy' ? 2 : 1, 'proof:web', $event, 'start');
            $result = ['outcome' => 'success', 'response' => $response];
        } catch (AttendanceError $error) {
            $result = ['outcome' => 'conflict', 'status' => $error->status];
        } catch (PDOException $error) {
            proofRequire((int) ($error->errorInfo[1] ?? 0) === 5, 'Unexpected SQLite failure.');
            $result = ['outcome' => 'busy', 'retry' => 'same source/event id and payload'];
        }
        proofRequire(! $pdo->inTransaction(), 'Worker retained a partial transaction.');
        echo json_encode($result + ['elapsed_seconds' => microtime(true) - $started], JSON_THROW_ON_ERROR), "\n";
        exit;
    }

    proofRequire(count($argv) === 1, 'This proof accepts no database path or production arguments.');
    $directory = sys_get_temp_dir().'/chrono-sqlite-proof-'.bin2hex(random_bytes(8));
    proofRequire(mkdir($directory, 0700), 'Fresh private fixture directory was not created.');
    file_put_contents($directory.'/SYNTHETIC-ONLY', 'No production data or credentials.');
    $mapping = json_decode(file_get_contents($root.'/docs/framework/schema-map.json'), true, 512, JSON_THROW_ON_ERROR);
    $source = (new SQLiteConnector)->connect(['database' => ':memory:', 'busy_timeout' => 5000]);
    $build = require $root.'/framework/database/fixtures/legacy_v2.php';
    $build($source, $mapping, true);
    $source->exec('VACUUM INTO '.$source->quote($directory.'/original.sqlite'));
    $source = null;
    proofRequire(copy($directory.'/original.sqlite', $directory.'/target.sqlite')
        && copy($directory.'/original.sqlite', $directory.'/backup.sqlite'), 'Stopped synthetic copies were not created.');
    $backupHash = hash_file('sha256', $directory.'/backup.sqlite');
    $path = $directory.'/target.sqlite';

    $phase = 'known-v2 offline prerequisite';
    $status = $app->make(Illuminate\Contracts\Console\Kernel::class)->call('chrono:legacy-v2', [
        'action' => 'adopt', '--target' => $path, '--backup' => $directory.'/backup.sqlite', '--offline' => true,
    ]);
    proofRequire($status === 0, 'Known-v2 synthetic adoption did not pass.');

    $phase = 'two contending real processes';
    [$holder] = proofOpen($path);
    $events = ['synthetic-race-start-0001', 'synthetic-race-start-0002'];
    $workers = [proofSpawn($directory, 'race1', $events[0]), proofSpawn($directory, 'race2', $events[1])];
    proofWait(fn () => is_file($directory.'/race1.ready') && is_file($directory.'/race2.ready'));
    $holder->exec('BEGIN IMMEDIATE');
    file_put_contents($directory.'/race1.go', 'go');
    file_put_contents($directory.'/race2.go', 'go');
    usleep(250000); // Both workers must contend for the existing SQLite writer lock.
    $holder->exec('COMMIT');
    $holder = null;
    $race = array_map('proofJoin', $workers);
    proofRequire(array_count_values(array_column($race, 'outcome')) === ['success' => 1, 'conflict' => 1]
        || array_count_values(array_column($race, 'outcome')) === ['conflict' => 1, 'success' => 1], 'Concurrent starts did not produce one success/one conflict.');
    $winner = $race[0]['outcome'] === 'success' ? 0 : 1;
    proofRequire($race[1 - $winner]['status'] === 409, 'The losing start did not have the existing conflict contract.');
    [$pdo, $writer] = proofWriter($path);
    proofRequire((int) $pdo->query('SELECT count(*) FROM work_sessions WHERE ended_at IS NULL AND user_id=1')->fetchColumn() === 1
        && (int) $pdo->query('SELECT count(*) FROM attendance_events')->fetchColumn() === 1
        && (int) $pdo->query("SELECT count(*) FROM audit_log WHERE actor='proof:web:user:1'")->fetchColumn() === 2,
        'Concurrent start lost/duplicated a session, event, projection or audit.');

    $phase = 'lost response and idempotent payload';
    $before = SourceSnapshot::capture($pdo, $mapping);
    $replayed = $writer->transition(1, 'proof:web', $events[$winner], 'start');
    proofRequire($replayed === $race[$winner]['response'], 'Lost response replay was not identical.');
    $stored = $pdo->query('SELECT response FROM attendance_events')->fetchColumn();
    proofRequire(json_encode($replayed) === $stored, 'Stored response bytes were not retained.');
    $mismatch = proofRefused(fn () => $writer->transition(1, 'proof:web', $events[$winner], 'pause_start'), AttendanceError::class);
    proofRequire($mismatch->status === 409 && SourceSnapshot::capture($pdo, $mapping) === $before, 'Payload conflict or replay changed history.');
    unset($writer, $pdo);

    $phase = 'busy budget before any domain execution';
    [$holder] = proofOpen($path);
    $busy = proofSpawn($directory, 'busy', 'synthetic-busy-start-0001');
    proofWait(fn () => is_file($directory.'/busy.ready'));
    $before = SourceSnapshot::capture($holder, $mapping);
    $holder->exec('BEGIN IMMEDIATE');
    file_put_contents($directory.'/busy.go', 'go');
    $busyResult = proofJoin($busy);
    $holder->exec('ROLLBACK');
    proofRequire($busyResult['outcome'] === 'busy' && $busyResult['elapsed_seconds'] >= 4
        && SourceSnapshot::capture($holder, $mapping) === $before, 'Busy budget duplicated domain work or left a partial mutation.');
    $holder = null;
    [$pdo, $writer] = proofWriter($path);
    $retry = $writer->transition(2, 'proof:web', 'synthetic-busy-start-0001', 'start');
    proofRequire($retry['success'] && $retry === $writer->transition(2, 'proof:web', 'synthetic-busy-start-0001', 'start'), 'Same-id retry after busy did not persist once.');

    $phase = 'event/audit failure rolls back the entire original';
    foreach (['audit_log' => 'BEFORE', 'attendance_events' => 'AFTER'] as $table => $timing) {
        $before = SourceSnapshot::capture($pdo, $mapping);
        $pdo->exec("CREATE TEMP TRIGGER proof_abort {$timing} INSERT ON {$table} BEGIN SELECT RAISE(ABORT,'Synthetic injected failure'); END");
        proofRefused(fn () => $writer->transition(2, 'proof:web', 'synthetic-rollback-pause-0001', 'pause_start'), PDOException::class);
        $pdo->exec('DROP TRIGGER proof_abort');
        proofRequire(! $pdo->inTransaction() && SourceSnapshot::capture($pdo, $mapping) === $before, 'Mutation, event, audit or sequence survived a failed operation.');
    }

    $phase = 'busy commit uncertainty disables the adapter; fresh scope verifies stable replay';
    // A separate reader permits IMMEDIATE mutation but prevents COMMIT in the
    // preserved rollback-journal mode. This is not a mocked commit exception.
    unset($writer, $pdo); // Fresh cold admission before opening the reader.
    [$reader] = proofOpen($path);
    [$pdo, $writer] = proofWriter($path);
    $before = SourceSnapshot::capture($pdo, $mapping);
    $reader->exec('BEGIN');
    $reader->query('SELECT count(*) FROM users')->fetchColumn();
    $commitStarted = microtime(true);
    $commitFailure = proofRefused(fn () => $writer->transition(2, 'proof:web', 'synthetic-commit-pause-0001', 'pause_start'), RuntimeException::class);
    proofRequire($commitFailure::class === RuntimeException::class && $commitFailure->getPrevious() === null
        && $commitFailure->getMessage() === 'Writer outcome could not be confirmed. Do not retry automatically.'
        && microtime(true) - $commitStarted >= 4 && ! $pdo->inTransaction()
        && SourceSnapshot::capture($pdo, $mapping) === $before, 'Real busy COMMIT did not preserve the unknown-outcome boundary and exact source.');
    proofRefused(fn () => $writer->transition(2, 'proof:web', 'synthetic-commit-pause-0001', 'pause_start'), LogicException::class);
    proofRequire(SourceSnapshot::capture($pdo, $mapping) === $before, 'A disabled commit-uncertain adapter performed work.');
    $reader->exec('ROLLBACK');
    unset($reader, $writer, $pdo);
    // Explicit controlled proof step after observing this isolated fixture's
    // rolled-back source, never an automatic application retry or writer reuse.
    [$pdo, $writer] = proofWriter($path);
    $pause = $writer->transition(2, 'proof:web', 'synthetic-commit-pause-0001', 'pause_start');
    proofRequire($pause['state'] === 'paused', 'Fresh admitted scope could not resolve the same stable event.');
    $writer->transition(2, 'proof:web', 'synthetic-pause-end-00001', 'pause_end');
    $writer->transition(2, 'proof:web', 'synthetic-session-end-0001', 'end');

    $phase = 'immutable originals, history and final decisions';
    $context = $app->make(AuditContext::class);
    $context->run('proof:decision', 'Synthetic final decision fixture', function () use ($pdo): void {
        $pdo->exec("INSERT INTO time_adjustments(user_id,record_id,proposed_start,proposed_end,proposed_pause,reason,created_at) VALUES(1,42,1,2,0,'Synthetic decision fixture',2)");
        $pdo->exec("UPDATE time_adjustments SET status='rejected',reviewer_id=2,reviewed_at=3,review_reason='Synthetic final decision' WHERE id=1");
    });
    $before = SourceSnapshot::capture($pdo, $mapping);
    $context->run('proof:forbidden', 'Synthetic critical protection probe', function () use ($pdo): void {
        foreach ([
            'UPDATE work_sessions SET ended_at=ended_at WHERE user_id=2',
            'UPDATE break_intervals SET ended_at=ended_at',
            'DELETE FROM work_sessions WHERE user_id=2',
            'UPDATE attendance_events SET response=response',
            'DELETE FROM audit_log',
            "UPDATE time_adjustments SET status='pending' WHERE id=1",
        ] as $sql) {
            proofRefused(fn () => $pdo->exec($sql), PDOException::class);
        }
    });
    proofRequire(SourceSnapshot::capture($pdo, $mapping) === $before, 'Immutable original or final decision changed.');

    $phase = 'single writer, context isolation and unsafe replacement';
    $pdo->exec('BEGIN IMMEDIATE');
    proofRefused(fn () => $writer->transition(1, 'proof:web', 'synthetic-nested-end-0001', 'end'), LogicException::class);
    $pdo->exec('ROLLBACK');
    proofRefused(fn () => $pdo->query('SELECT audit_actor()'), LogicException::class);
    proofRefused(fn () => $pdo->exec('UPDATE zeiterfassung SET pause=pause WHERE id=42'), LogicException::class);
    proofRequire(SourceSnapshot::capture($pdo, $mapping) === $before, 'Nested/missing-context refusal changed data.');
    unset($writer, $pdo);
    $app->forgetScopedInstances();
    [$pdo, $writer] = proofWriter($path); // New actual PDO, fresh job/request scope.
    $GLOBALS['audit_actor'] = null;
    $GLOBALS['audit_reason'] = 'proof:previous-global';
    $writer->transition(1, 'proof:next-job', 'synthetic-next-scope-end-0001', 'end');
    proofRequire(array_key_exists('audit_actor', $GLOBALS) && $GLOBALS['audit_actor'] === null
        && $GLOBALS['audit_reason'] === 'proof:previous-global', 'Legacy audit globals leaked between jobs.');
    proofRequire($pdo->query('SELECT actor FROM audit_log ORDER BY id DESC LIMIT 1')->fetchColumn() === 'proof:next-job:user:1', 'Fresh writer did not install scoped PHP8.5 UDFs.');
    proofRefused(fn () => $pdo->query('SELECT audit_reason()'), LogicException::class);
    unset($GLOBALS['audit_actor'], $GLOBALS['audit_reason'], $writer, $pdo);

    // Fault injection only: bypass private visibility to model a future unsafe
    // reconnect implementation. No production hook is added for this proof.
    [$pdo, $config] = proofOpen($path);
    [$replacement] = proofOpen($path);
    $before = SourceSnapshot::capture($pdo, $mapping);
    $mutateConnection = null;
    $functions = new AuditFunctions(function () use (&$mutateConnection, $replacement, $app): AuditContext {
        if ($mutateConnection !== null) {
            $mutateConnection->setPdo($replacement);
            $mutateConnection = null;
        }
        return $app->make(AuditContext::class);
    });
    $writer = new AttendanceWriter($pdo, $config, $app->make(AuditContext::class), $functions);
    $mutateConnection = (new ReflectionProperty($writer, 'connection'))->getValue($writer);
    $replacementFailure = proofRefused(fn () => $writer->transition(2, 'proof:unsafe', 'synthetic-reconnect-start-0001', 'start'), RuntimeException::class);
    proofRequire($replacementFailure::class === RuntimeException::class && $replacementFailure->getPrevious() === null
        && $replacementFailure->getMessage() === 'Writer outcome could not be confirmed. Do not retry automatically.',
        'Unsafe replacement did not retain the fixed cleanup-uncertainty result.');
    proofRequire(! $pdo->inTransaction() && SourceSnapshot::capture($pdo, $mapping) === $before
        && SourceSnapshot::capture($replacement, $mapping) === $before, 'Unsafe replacement committed or abandoned the original mutation.');
    proofRefused(fn () => $writer->transition(2, 'proof:unsafe', 'synthetic-reconnect-start-0001', 'start'), LogicException::class);
    unset($writer, $pdo, $replacement, $functions);

    $phase = 'persisted post-close verification';
    [$pdo, $writer] = proofWriter($path);
    proofRequire($pdo->query('PRAGMA quick_check')->fetchColumn() === 'ok'
        && (int) $pdo->query('SELECT count(*) FROM work_sessions WHERE ended_at IS NULL')->fetchColumn() === 0
        && (int) $pdo->query('SELECT count(*) FROM framework_migrations')->fetchColumn() === 1
        && hash_file('sha256', $directory.'/backup.sqlite') === $backupHash
        && hash_file('sha256', $directory.'/original.sqlite') === $backupHash, 'Post-close persisted verification or pristine backup failed.');
    $phase = 'current-data backup and ledger-only backout after new confirmed bookings';
    $afterBookings = SourceSnapshot::capture($pdo, $mapping);
    $storedReceipt = $pdo->query("SELECT response FROM attendance_events WHERE source='proof:web' AND event_id='synthetic-session-end-0001'")->fetchColumn();
    proofRequire(is_string($storedReceipt), 'The confirmed post-adoption receipt is missing.');
    unset($writer, $pdo);
    $files = new OfflineFiles([$root, $root.'/framework/public']);
    $coldAfterBookings = $files->snapshot($path); // Never copy a live SQLite handle.
    $staleBackout = $app->make(Illuminate\Contracts\Console\Kernel::class)->call('chrono:legacy-v2', [
        'action' => 'rollback-ledger', '--target' => $path, '--backup' => $directory.'/backup.sqlite', '--offline' => true,
    ]);
    proofRequire($staleBackout !== 0 && $files->snapshot($path) === $coldAfterBookings
        && hash_file('sha256', $directory.'/backup.sqlite') === $backupHash,
        'A stale pre-booking backup was accepted or changed current data.');
    proofRequire(copy($path, $directory.'/current-backup.sqlite'), 'Current stopped backup was not created.');
    $currentBackupHash = hash_file('sha256', $directory.'/current-backup.sqlite');
    foreach (['rollback-ledger', 'rollback-ledger'] as $action) {
        $status = $app->make(Illuminate\Contracts\Console\Kernel::class)->call('chrono:legacy-v2', [
            'action' => $action, '--target' => $path, '--backup' => $directory.'/current-backup.sqlite', '--offline' => true,
        ]);
        proofRequire($status === 0, 'Current-backup ledger-only backout or repetition failed.');
        $read = $files->openReadOnly($path);
        proofRequire(SourceSnapshot::capture($read, $mapping) === $afterBookings
            && $read->query("SELECT 1 FROM sqlite_schema WHERE type='table' AND name='framework_migrations'")->fetchColumn() === false
            && hash_file('sha256', $directory.'/current-backup.sqlite') === $currentBackupHash,
            'Ledger backout changed confirmed business data, sequences, receipt or backup.');
        $read = null;
    }
    // A fresh backup of the now ledger-free CURRENT data, not the pristine
    // pre-booking backup, is required by the unchanged adoption contract.
    $files->snapshot($path);
    proofRequire(copy($path, $directory.'/backout-backup.sqlite'), 'Ledger-free current backup was not created.');
    $backoutBackupHash = hash_file('sha256', $directory.'/backout-backup.sqlite');
    foreach (['adopt', 'adopt'] as $action) {
        $status = $app->make(Illuminate\Contracts\Console\Kernel::class)->call('chrono:legacy-v2', [
            'action' => $action, '--target' => $path, '--backup' => $directory.'/backout-backup.sqlite', '--offline' => true,
        ]);
        proofRequire($status === 0, 'Current-source readoption or repetition failed.');
    }
    [$pdo, $writer] = proofWriter($path);
    $resolvedReceipt = $writer->transition(2, 'proof:web', 'synthetic-session-end-0001', 'end');
    proofRequire(SourceSnapshot::capture($pdo, $mapping) === $afterBookings
        && json_encode($resolvedReceipt) === $storedReceipt
        && (int) $pdo->query('SELECT count(*) FROM framework_migrations')->fetchColumn() === 1
        && hash_file('sha256', $directory.'/current-backup.sqlite') === $currentBackupHash
        && hash_file('sha256', $directory.'/backout-backup.sqlite') === $backoutBackupHash,
        'Ledger backout/readoption lost or duplicated post-adoption business history.');

    $result = [
        'php' => PHP_VERSION, 'laravel' => $app->version(), 'sqlite' => $pdo->query('SELECT sqlite_version()')->fetchColumn(),
        'pdo_class' => $pdo::class, 'scope' => 'fresh synthetic stopped known-v2 private file copies; no runtime cutover',
        'journal_mode' => $pdo->query('PRAGMA journal_mode')->fetchColumn(),
        'busy_timeout' => (int) $pdo->query('PRAGMA busy_timeout')->fetchColumn(),
        'foreign_keys_observed_unchanged' => (int) $pdo->query('PRAGMA foreign_keys')->fetchColumn(),
        'two_process_start_one_session_one_event_two_audit_rows' => true,
        'stored_response_byte_identical_replay_payload_conflict' => true,
        'busy_begin_single_attempt_then_same_event_retry' => true,
        'audit_event_and_real_busy_commit_failures_fully_rolled_back' => true,
        'busy_commit_generic_uncertainty_and_adapter_reuse_refused' => true,
        'same_stable_event_explicitly_resolved_in_fresh_admitted_scope' => true,
        'new_confirmed_bookings_and_receipt_survive_ledger_backout_repetition_readoption' => true,
        'current_stopped_backups_preserved' => true,
        'stale_pre_booking_backup_refused_before_backout' => true,
        'closed_intervals_append_only_history_and_final_decisions_retained' => true,
        'fresh_pdo_udfs_scopes_globals_and_missing_context_checked' => true,
        'nested_transaction_and_unsafe_pdo_replacement_fail_closed' => true,
        'pristine_original_and_backup_unchanged' => true,
        'final_counts' => SourceSnapshot::capture($pdo, $mapping)['counts'],
        'private_fixture_directory' => $directory,
        'remaining' => ['NEW V5 installation has its separate producer/proof', 'general pre-v2 upgrade and WAL admission', 'complete runtime/browser/container/restore and operating cutover acceptance'],
    ];
    unset($writer, $pdo);
    foreach (['-wal', '-shm', '-journal'] as $suffix) {
        proofRequire(! file_exists($path.$suffix), 'A SQLite sidecar remained after close.');
    }
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
} catch (Throwable $error) {
    // Neither source rows, native SQL/bindings, paths nor exception messages leak.
    fwrite(STDERR, 'Synthetic write proof failed at '.$phase.' ('.$error::class.").\n");
    exit(1);
}
