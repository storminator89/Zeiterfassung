<?php

namespace App\Console\Commands;

use App\LegacyDatabase\AuditContext;
use App\LegacyDatabase\AuditFunctions;
use App\LegacyDatabase\OfflineFiles;
use App\LegacyDatabase\SourceSnapshot;
use App\LegacyDatabase\V2Adoption;
use App\LegacyDatabase\V2Preflight;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Connectors\SQLiteConnector;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\SQLiteConnection;
use PDO;
use RuntimeException;
use Throwable;

final class OfflineLegacyV2 extends Command
{
    protected $signature = 'chrono:legacy-v2
        {action=inspect : inspect, adopt, or rollback-ledger}
        {--target= : Absolute existing private offline v2 database path}
        {--backup= : Absolute distinct verified stopped database backup; required for changes}
        {--offline : Confirm all application/terminal writers are stopped and full private backup is secured}';

    protected $description = 'Inspect or adopt a known-v2 offline database without changing legacy data; never inferred from APP_DATA_DIR.';

    public function handle(AuditContext $context, AuditFunctions $functions): int
    {
        $phase = 'arguments and private file admission';
        $connection = null;
        $targetRead = null;
        $backupRead = null;

        try {
            $action = $this->argument('action');
            if (! in_array($action, ['inspect', 'adopt', 'rollback-ledger'], true)) {
                throw new RuntimeException('Action must be inspect, adopt, or rollback-ledger.');
            }
            if (! is_string($this->option('target')) || $this->option('target') === '') {
                throw new RuntimeException('An explicit --target path is required; no application data fallback is used.');
            }
            if ($action !== 'inspect' && (! $this->option('offline') || ! is_string($this->option('backup')) || $this->option('backup') === '')) {
                throw new RuntimeException('Changes require --offline and an explicit distinct --backup; stop all writers first.');
            }

            $files = new OfflineFiles([dirname(base_path()), public_path()]);
            $path = $files->validate($this->option('target'));
            $fileBefore = $files->snapshot($path);
            $targetRead = $files->openReadOnly($path);
            $targetRead->exec('BEGIN');

            $phase = 'read-only source-v2 preflight';
            [$mapping, $reference] = $this->reference($functions);
            $preflight = new V2Preflight($mapping);
            $checked = $preflight->inspect($targetRead, $reference);
            $state = V2Adoption::ledgerState($targetRead, $checked['schema_signature']);
            $sourceBefore = SourceSnapshot::capture($targetRead, $mapping);
            $settings = $this->settings($targetRead);
            if ($files->snapshot($path) !== $fileBefore) {
                throw new RuntimeException('The target changed during read-only preflight.');
            }

            $result = $checked + [
                'action' => $action, 'ledger_state' => $state, 'changed' => false,
                'source_counts' => $sourceBefore['counts'], 'settings_observed' => $settings,
            ];
            if ($action === 'inspect') {
                $targetRead->rollBack();
                $this->line(json_encode($result + ['read_only' => true], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

                return self::SUCCESS;
            }

            $phase = 'backup verification before any writer connection';
            $backup = $files->validate($this->option('backup'));
            // Only an already-confirmed, exactly-owned ledger permits a repeated
            // adoption to use a pristine pre-ledger backup with different file bytes.
            $requireSameBytes = $action === 'adopt' && $state === 'absent';
            $backedUp = $files->verifyBackup($path, $backup, $requireSameBytes);
            $backupRead = $files->openReadOnly($backup);
            $backupRead->exec('BEGIN');
            $backupChecked = $preflight->inspect($backupRead, $reference);
            V2Adoption::ledgerState($backupRead, $backupChecked['schema_signature']);
            if ($backupChecked['schema_signature'] !== $checked['schema_signature']
                || SourceSnapshot::capture($backupRead, $mapping) !== $sourceBefore) {
                throw new RuntimeException('Backup does not preserve the exact current legacy data and sequences.');
            }
            if ($files->snapshot($path) !== $fileBefore || $files->snapshot($backup) !== $backedUp['backup']) {
                throw new RuntimeException('Target or backup changed after verification.');
            }
            $targetRead->rollBack();
            $targetRead = null;
            $backupRead->rollBack();
            $backupRead = null;

            if (($action === 'adopt' && $state === 'owned') || ($action === 'rollback-ledger' && $state === 'absent')) {
                $this->line(json_encode($result + ['read_only' => true, 'backup_verified' => true], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

                return self::SUCCESS;
            }

            $phase = 'single offline writer admission';
            $config = $files->readWriteConfiguration($path, $fileBefore);
            $config['name'] = 'chrono_offline_v2';
            $pdo = (new SQLiteConnector)->connect($config);
            $opened = $pdo->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC);
            if (count($opened) !== 1 || $opened[0]['name'] !== 'main' || $opened[0]['file'] !== $path) {
                throw new RuntimeException('The writer did not open the explicitly admitted database.');
            }
            $functions->register($pdo);
            $connection = new SQLiteConnection($pdo, $path, '', $config);
            if ($files->snapshot($path) !== $fileBefore || $this->settings($pdo) !== $settings) {
                throw new RuntimeException('Writer identity or effective settings changed before adoption.');
            }
            $adoption = new V2Adoption($this->laravel->make('db'), $preflight, $this->laravel->make('config'));
            $guard = static function (PDO $writer) use ($files, $backup, $backedUp, $mapping, $sourceBefore): void {
                if (SourceSnapshot::capture($writer, $mapping) !== $sourceBefore
                    || $files->snapshot($backup) !== $backedUp['backup']) {
                    throw new RuntimeException('Legacy data or verified backup changed; adoption is aborted.');
                }
            };
            $phase = 'additive tracking transaction';
            $writeResult = $context->run('migration:offline-v2', 'Explicit offline '.$action, fn () => $action === 'adopt'
                ? $adoption->registerConnection($connection, $reference, $guard)
                : $adoption->removeOwnedLedger($connection, $reference, $guard));

            $connection->disconnect();
            $connection = null;
            $pdo = null;
            if ($files->snapshot($backup) !== $backedUp['backup']) {
                throw new RuntimeException('Backup changed; retain the maintenance window for investigation.');
            }
            $files->snapshot($path); // Also require a clean closed DB without leftover sidecars.
            $this->line(json_encode(array_replace($result, $writeResult, [
                'changed' => true, 'read_only' => false, 'backup_verified' => true,
                'ledger_state' => $action === 'adopt' ? 'owned' : 'absent',
                'legacy_data_and_sequences_preserved' => true,
                'scope' => 'known-v2 offline tracking only; no runtime connection or route cutover',
            ]), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable $error) {
            // Our plain RuntimeException messages contain only controlled instructions;
            // never expose native PDO/QueryException DSNs, SQL, bindings or row values.
            $detail = $error::class === RuntimeException::class ? $error->getMessage() : $error::class;
            $this->error('Offline v2 stopped at '.$phase.': '.$detail);

            return self::FAILURE;
        } finally {
            foreach ([$targetRead, $backupRead] as $read) {
                if ($read instanceof PDO && $read->inTransaction()) {
                    $read->rollBack();
                }
            }
            $connection?->disconnect();
        }
    }

    private function reference(AuditFunctions $functions): array
    {
        $mapping = json_decode(file_get_contents(dirname(base_path()).'/docs/framework/schema-map.json'), true, 512, JSON_THROW_ON_ERROR);
        $reference = (new SQLiteConnector)->connect(['database' => ':memory:', 'busy_timeout' => 5000]);
        $build = require database_path('fixtures/legacy_v2.php');
        $build($reference, $mapping, false);
        $functions->register($reference);
        $tables = array_column($mapping['tables'], null, 'name');
        $reference->exec($tables['login_attempts']['create_sql']);
        $connection = new SQLiteConnection($reference, ':memory:', '', ['name' => 'chrono_v2_reference']);
        $repository = new DatabaseMigrationRepository(new ConnectionResolver(['chrono_v2_reference' => $connection]), V2Preflight::LEDGER);
        $repository->setSource('chrono_v2_reference');
        $repository->createRepository();

        return [$mapping, $reference];
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
