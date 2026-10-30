<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// One coarse current-CLI migration flow, using newly generated unusable fixture
// identities only. No path arguments, normal App bootstrap, credentials or routes.
use App\LegacyDatabase\AttendanceWriter;
use App\LegacyDatabase\OfflineFiles;
use App\LegacyDatabase\RuntimeDatabase;
use App\LegacyDatabase\SourceSnapshot;
use App\Providers\LegacyFixtureServiceProvider;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connectors\SQLiteConnector;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\ApplicationBuilder;

umask(0077);
$phase = 'new private proof allocation';
$directory = null;
$root = dirname(__DIR__, 2);
require dirname(__DIR__).'/vendor/autoload.php';

function upgradeProofRequire(bool $condition): void
{
    if (! $condition) { throw new RuntimeException('Synthetic upgrade invariant failed.'); }
}

try {
    upgradeProofRequire($argc === 1);
    $temporary = realpath(sys_get_temp_dir());
    upgradeProofRequire(is_string($temporary));
    $directory = $temporary.'/chrono-user-audit-proof-'.bin2hex(random_bytes(8));
    upgradeProofRequire(mkdir($directory, 0700));
    foreach (['environment', 'bootstrap/cache', 'storage/framework/views', 'storage/logs', 'data'] as $child) {
        upgradeProofRequire(mkdir($directory.'/'.$child, 0700, true));
    }
    file_put_contents($directory.'/SYNTHETIC-ONLY', 'Source-generated unusable fixture identities. No production ingress.');

    $phase = 'isolated console composition';
    // Process-local cache overrides cannot select an operator's cached config,
    // providers or .env. No variable or file on the host is changed.
    foreach (['APP_CONFIG_CACHE', 'APP_SERVICES_CACHE', 'APP_PACKAGES_CACHE',
        'APP_ROUTES_CACHE', 'APP_EVENTS_CACHE', 'COMPOSER_VENDOR_DIR'] as $name) {
        putenv($name);
        unset($_ENV[$name], $_SERVER[$name]);
    }
    $app = (new ApplicationBuilder(new Application($root.'/framework')))
        ->withKernels()
        ->withProviders([LegacyFixtureServiceProvider::class], withBootstrapProviders: false)
        ->create();
    $app->useEnvironmentPath($directory.'/environment');
    $app->useBootstrapPath($directory.'/bootstrap');
    $app->useStoragePath($directory.'/storage');
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();
    upgradeProofRequire(! $app->bound(RuntimeDatabase::class) && ! $app->bound(AttendanceWriter::class)
        && $app->make('db')->getConnections() === []);

    $phase = 'canonical synthetic source and V2 adoption';
    $mapping = json_decode(file_get_contents($root.'/docs/framework/schema-map.json'), true, 512, JSON_THROW_ON_ERROR);
    $source = (new SQLiteConnector)->connect(['database' => ':memory:', 'busy_timeout' => 5000]);
    $build = require dirname(__DIR__).'/database/fixtures/legacy_v2.php';
    $build($source, $mapping, true);
    $source->exec('VACUUM INTO '.$source->quote($directory.'/data/original.sqlite'));
    $source = null;
    $path = $directory.'/data/target.sqlite';
    upgradeProofRequire(copy($directory.'/data/original.sqlite', $path)
        && copy($directory.'/data/original.sqlite', $directory.'/data/pristine.sqlite'));
    $files = new OfflineFiles([$root, $root.'/framework/public']);
    $initial = $files->snapshot($directory.'/data/original.sqlite');
    $pristine = $files->snapshot($directory.'/data/pristine.sqlite');
    upgradeProofRequire($kernel->call('chrono:legacy-v2', [
        'action' => 'adopt', '--target' => $path, '--backup' => $directory.'/data/pristine.sqlite', '--offline' => true,
    ]) === 0);
    upgradeProofRequire(copy($path, $directory.'/data/adopted-backup.sqlite'));
    $adoptedBackup = $files->snapshot($directory.'/data/adopted-backup.sqlite');
    $read = $files->openReadOnly($path);
    $preserveMapping = $mapping;
    $preserveMapping['tables'] = array_values(array_filter($mapping['tables'],
        static fn (array $table): bool => $table['name'] !== 'app_migrations'));
    $preserved = SourceSnapshot::capture($read, $preserveMapping);
    $ledgerBefore = $read->query('SELECT id,migration,batch FROM framework_migrations ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $read = null;

    $phase = 'actual V2 inspect and additive V3 CLI upgrade';
    upgradeProofRequire($kernel->call('chrono:user-audit-v3', [
        'action' => 'inspect', '--target' => $path, '--offline' => true,
    ]) === 0);
    upgradeProofRequire($kernel->call('chrono:user-audit-v3', [
        'action' => 'upgrade', '--target' => $path, '--backup' => $directory.'/data/adopted-backup.sqlite', '--offline' => true,
    ]) === 0);
    $closed = $files->snapshot($path);
    $read = $files->openReadOnly($path);
    upgradeProofRequire($read->query('SELECT version FROM app_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN) === [1, 2, 3]
        && SourceSnapshot::capture($read, $preserveMapping) === $preserved);
    $ledger = $read->query('SELECT id,migration,batch FROM framework_migrations ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    upgradeProofRequire(count($ledgerBefore) === 1 && count($ledger) === 2 && $ledger[0] === $ledgerBefore[0]
        && $ledger[1]['id'] === 2 && $ledger[1]['batch'] === 2);
    $cookie = $read->query('PRAGMA schema_version')->fetchColumn();
    $read = null;

    $phase = 'stale-backup refusal and current-backup repetition';
    upgradeProofRequire($kernel->call('chrono:user-audit-v3', [
        'action' => 'upgrade', '--target' => $path, '--backup' => $directory.'/data/adopted-backup.sqlite', '--offline' => true,
    ]) !== 0 && $files->snapshot($path) === $closed);
    upgradeProofRequire(copy($path, $directory.'/data/current-v3-backup.sqlite'));
    $currentBackup = $files->snapshot($directory.'/data/current-v3-backup.sqlite');
    for ($attempt = 0; $attempt < 2; $attempt++) {
        upgradeProofRequire($kernel->call('chrono:user-audit-v3', [
            'action' => 'upgrade', '--target' => $path, '--backup' => $directory.'/data/current-v3-backup.sqlite', '--offline' => true,
        ]) === 0 && $files->snapshot($path) === $closed
            && $files->snapshot($directory.'/data/current-v3-backup.sqlite') === $currentBackup);
    }

    $phase = 'closed integrity and V2 backout boundary';
    upgradeProofRequire($kernel->call('chrono:user-audit-v3', [
        'action' => 'inspect', '--target' => $path, '--offline' => true,
    ]) === 0);
    upgradeProofRequire($kernel->call('chrono:legacy-v2', [
        'action' => 'rollback-ledger', '--target' => $path, '--backup' => $directory.'/data/current-v3-backup.sqlite', '--offline' => true,
    ]) !== 0 && $files->snapshot($path) === $closed);
    $read = $files->openReadOnly($path);
    upgradeProofRequire($read->query('PRAGMA quick_check')->fetchColumn() === 'ok'
        && SourceSnapshot::capture($read, $preserveMapping) === $preserved
        && $read->query('PRAGMA schema_version')->fetchColumn() === $cookie);
    $sqlite = $read->query('SELECT sqlite_version()')->fetchColumn();
    $read = null;
    upgradeProofRequire($files->snapshot($path) === $closed
        && $files->snapshot($directory.'/data/original.sqlite') === $initial
        && $files->snapshot($directory.'/data/pristine.sqlite') === $pristine
        && $files->snapshot($directory.'/data/adopted-backup.sqlite') === $adoptedBackup
        && $files->snapshot($directory.'/data/current-v3-backup.sqlite') === $currentBackup
        && ! $app->bound(RuntimeDatabase::class) && ! $app->bound(AttendanceWriter::class)
        && $app->make('db')->getConnections() === []);

    echo json_encode([
        'passed' => true, 'php' => PHP_VERSION, 'laravel' => $app->version(), 'sqlite' => $sqlite,
        'scope' => 'actual offline V2/V3 CLI with isolated console and newly generated private synthetic files only',
        'application_versions' => [1, 2, 3], 'ledger_rows' => 2, 'first_ledger_row_exact' => true,
        'other_source_audit_events_records_models_and_sequences_preserved' => true,
        'two_current_backup_repetitions_preserve_bytes_identity_schema_cookie' => true,
        'stale_backup_and_v2_backout_refused_before_mutation' => true,
        'original_and_all_backups_preserved' => true,
        'runtime_writer_or_default_connection_resolved' => false,
        'operator_dotenv_cached_config_storage_or_routes_used' => false,
        'real_or_usable_accounts_credentials_keys_or_listener_created' => false,
        'fixture_identities' => 'two existing source-generated identities with unusable password markers',
        'private_fixture_directory' => $directory,
        'remaining' => ['no Runtime activation or V4/V5 existing-data upgrade', 'no normal App/Auth/HTTP/browser or business/user mutations',
            'no WAL/crash/concurrent upgrade/restore or operating cutover acceptance'],
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
} catch (Throwable $error) {
    // Fixed phase/type only, no SQL, values, file path or exception detail.
    fwrite(STDERR, 'Synthetic user-audit upgrade proof failed at '.$phase.' ('.$error::class."). Keep only this new private proof directory for inspection; no automatic retry.\n");
    exit(1);
}
