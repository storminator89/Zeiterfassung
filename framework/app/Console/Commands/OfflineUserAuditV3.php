<?php

namespace App\Console\Commands;

use App\LegacyDatabase\AuditContext;
use App\LegacyDatabase\AuditFunctions;
use App\LegacyDatabase\OfflineFiles;
use App\LegacyDatabase\SourceSnapshot;
use App\LegacyDatabase\UserAuditV3Upgrade;
use App\LegacyDatabase\V2Preflight;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Connectors\SQLiteConnector;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\SQLiteConnection;
use PDO;
use RuntimeException;
use Throwable;

/** Explicit stopped-file operation only; never invoked by native/runtime bootstrap. */
final class OfflineUserAuditV3 extends Command
{
    protected $signature = 'chrono:user-audit-v3
        {action : inspect or upgrade}
        {--target= : Absolute existing private stopped adopted database}
        {--backup= : Distinct byte-identical CURRENT stopped backup; required for upgrade including repeats}
        {--offline : Confirm every writer is stopped and matching code/full private backup retained}';

    protected $description = 'Explicit offline three-field user audit upgrade; no automatic migration, restore or runtime admission.';

    public function handle(AuditContext $context, AuditFunctions $functions): int
    {
        $phase = 'explicit offline arguments';
        $targetRead = $backupRead = $connection = null;
        try {
            $action = $this->argument('action');
            if (! in_array($action, ['inspect', 'upgrade'], true) || ! $this->option('offline')
                || ! is_string($this->option('target')) || $this->option('target') === ''
                || ($action === 'upgrade' && (! is_string($this->option('backup')) || $this->option('backup') === ''))) {
                throw new RuntimeException('Explicit inspect/upgrade, target and offline confirmation are required; upgrade also needs a current backup.');
            }
            $files = new OfflineFiles([dirname(base_path()), public_path()]);
            $path = $files->validate($this->option('target'));
            $before = $files->snapshot($path);
            $targetRead = $files->openReadOnly($path);
            $targetRead->exec('BEGIN');
            [$mapping, $v2, $v3] = $this->references($functions);
            $upgrade = new UserAuditV3Upgrade(new V2Preflight($mapping), $mapping);
            $phase = 'complete read-only schema and ledger preflight';
            $checked = $upgrade->inspect($targetRead, $v2, $v3);
            $fullSource = SourceSnapshot::capture($targetRead, $mapping);
            $preserved = $upgrade->preservedSource($targetRead);
            $settings = $this->settings($targetRead);
            if ($files->snapshot($path) !== $before) {
                throw new RuntimeException('Source changed during read-only preflight.');
            }
            if ($action === 'inspect') {
                $targetRead->rollBack();
                $targetRead = null;
                $this->line(json_encode($checked + ['read_only' => true, 'changed' => false], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

                return self::SUCCESS;
            }

            $phase = 'distinct current stopped backup verification';
            $backup = $files->validate($this->option('backup'));
            $backedUp = $files->verifyBackup($path, $backup, true);
            $backupRead = $files->openReadOnly($backup);
            $backupRead->exec('BEGIN');
            if ($upgrade->inspect($backupRead, $v2, $v3) !== $checked
                || SourceSnapshot::capture($backupRead, $mapping) !== $fullSource
                || $this->settings($backupRead) !== $settings
                || $files->snapshot($path) !== $before || $files->snapshot($backup) !== $backedUp['backup']) {
                throw new RuntimeException('Backup does not preserve the exact current source.');
            }
            $targetRead->rollBack();
            $targetRead = null;
            $backupRead->rollBack();
            $backupRead = null;
            if ($checked['profile'] === 'user-audit-v3') {
                $this->line(json_encode($checked + ['read_only' => true, 'changed' => false, 'already_applied' => true, 'backup_verified' => true], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

                return self::SUCCESS;
            }

            $phase = 'single offline writer admission';
            $configuration = $files->readWriteConfiguration($path, $before);
            $configuration['name'] = 'chrono_offline_user_audit_v3';
            $pdo = (new SQLiteConnector)->connect($configuration);
            $opened = $pdo->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC);
            if (count($opened) !== 1 || $opened[0]['name'] !== 'main' || $opened[0]['file'] !== $path
                || $files->snapshot($path) !== $before || $this->settings($pdo) !== $settings) {
                throw new RuntimeException('Offline writer identity or settings changed.');
            }
            $functions->register($pdo);
            $connection = new SQLiteConnection($pdo, $path, '', $configuration);
            $guard = function (PDO $writer) use ($upgrade, $preserved, $files, $backup, $backedUp, $settings): void {
                if ($upgrade->preservedSource($writer) !== $preserved || $this->settings($writer) !== $settings
                    || $files->snapshot($backup) !== $backedUp['backup']) {
                    throw new RuntimeException('Source settings/data or verified backup changed.');
                }
            };
            $phase = 'one versioned trigger and ledger transaction';
            $result = $context->run('migration:offline-user-audit-v3', 'Explicit offline user audit field upgrade',
                fn () => $upgrade->applyConnection($connection, $v2, $v3, $guard));
            $connection->disconnect();
            $connection = null;
            $pdo = null;

            // A postcommit failure cannot imply rollback or authorize an automatic retry.
            $phase = 'closed postcommit verification; keep offline if unavailable';
            if ($files->snapshot($backup) !== $backedUp['backup']) {
                throw new RuntimeException('Backup changed after commit; saved target requires offline review.');
            }
            $closed = $files->snapshot($path);
            $targetRead = $files->openReadOnly($path);
            $targetRead->exec('BEGIN');
            $post = $upgrade->inspect($targetRead, $v2, $v3);
            $cookie = $targetRead->query('PRAGMA schema_version')->fetchColumn();
            if ($post['profile'] !== 'user-audit-v3' || $upgrade->preservedSource($targetRead) !== $preserved
                || $this->settings($targetRead) !== $settings || $files->snapshot($path) !== $closed) {
                throw new RuntimeException('Closed post-upgrade validation failed; no runtime admission performed.');
            }
            $targetRead->rollBack();
            $targetRead = null;
            $this->line(json_encode($result + ['read_only' => false, 'backup_verified' => true,
                'schema_cookie' => $cookie, 'source_data_preserved' => true, 'runtime_re_admission_required' => true,
                'scope' => 'three user audit fields only; no runtime activation or automatic restore'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable) {
            // Fixed phase only, never native exception, rows, credentials or file paths.
            $this->error('User audit V3 stopped during '.$phase.'. Keep offline and inspect saved state; no automatic retry or restore.');

            return self::FAILURE;
        } finally {
            foreach ([$targetRead, $backupRead] as $read) {
                try {
                    if ($read instanceof PDO && $read->inTransaction()) { $read->rollBack(); }
                } catch (Throwable) {
                    $this->error('Offline read cleanup was not confirmed; retain the maintenance window.');
                }
            }
            try { $connection?->disconnect(); }
            catch (Throwable) { $this->error('Offline writer cleanup was not confirmed; retain the maintenance window.'); }
        }
    }

    private function references(AuditFunctions $functions): array
    {
        $mapping = json_decode(file_get_contents(dirname(base_path()).'/docs/framework/schema-map.json'), true, 512, JSON_THROW_ON_ERROR);
        $build = require database_path('fixtures/legacy_v2.php');
        $references = [];
        foreach ([false, true] as $v3) {
            $reference = (new SQLiteConnector)->connect(['database' => ':memory:', 'busy_timeout' => 5000]);
            $build($reference, $mapping, false);
            $functions->register($reference);
            $tables = array_column($mapping['tables'], null, 'name');
            $reference->exec($tables['login_attempts']['create_sql']);
            if ($v3) {
                require_once dirname(base_path()).'/lib/user_audit_v3.php';
                $reference->beginTransaction();
                try {
                    \user_audit_v3_apply_locked($reference);
                    $reference->commit();
                } catch (Throwable $error) {
                    if ($reference->inTransaction()) { $reference->rollBack(); }
                    throw $error;
                }
            }
            $connection = new SQLiteConnection($reference, ':memory:', '', ['name' => 'chrono_user_audit_reference']);
            $repository = new DatabaseMigrationRepository(new ConnectionResolver(['chrono_user_audit_reference' => $connection]), V2Preflight::LEDGER);
            $repository->setSource('chrono_user_audit_reference');
            $repository->createRepository();
            $references[] = $reference;
        }

        return [$mapping, ...$references];
    }

    private function settings(PDO $pdo): array
    {
        $settings = [];
        foreach (['foreign_keys', 'busy_timeout', 'journal_mode', 'synchronous'] as $name) {
            $settings[$name] = $pdo->query('PRAGMA '.$name)->fetchColumn();
        }

        return $settings;
    }
}
