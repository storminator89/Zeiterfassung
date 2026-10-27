<?php

// One coarse memory-only admin-list/refusal check; no HTTP or fixture arguments.
use App\Auth\LegacyUser;
use App\Chrono\ChronoReads;
use App\LegacyDatabase\AuditContext;
use App\LegacyDatabase\AuditFunctions;
use App\LegacyDatabase\MemoryOnly;
use App\LegacyDatabase\SourceSnapshot;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$config = $app->make('config');
$database = $app->make('db');
$previousConnections = $config->get('database.connections');
$previousSession = $config->get('session.driver');
$previousKey = $config->get('app.key');
$globals = array_intersect_key($GLOBALS, array_flip(['audit_actor', 'audit_reason', '_SESSION']));
$phase = 'inactive named-connection refusal';
$ensure = static function (bool $condition): void {
    if (! $condition) {
        throw new RuntimeException('Coarse work-model target invariant failed.');
    }
};

try {
    $ensure($argc === 1 && ! $config->has('database.connections.chrono') && $database->getConnections() === []);
    $reads = $app->make(ChronoReads::class);
    try {
        $reads->workModelTargets();
        throw new RuntimeException('An inactive connection was accepted.');
    } catch (LogicException) {
        $ensure($database->getConnections() === []);
    }
    $config->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    $config->set('session.driver', 'array');
    $config->set('database.connections.chrono', [
        'driver' => 'chrono_fixture', 'database' => ':memory:', 'prefix' => '',
        'foreign_key_constraints' => false, 'busy_timeout' => 5000,
        'journal_mode' => null, 'synchronous' => null, 'transaction_mode' => 'DEFERRED',
    ]);
    $pdo = $database->connection('chrono')->getPdo();
    MemoryOnly::connection($pdo);
    $mapping = json_decode(file_get_contents(dirname(__DIR__, 2).'/docs/framework/schema-map.json'), true, 512, JSON_THROW_ON_ERROR);
    $build = require dirname(__DIR__).'/database/fixtures/legacy_v2.php';
    $build($pdo, $mapping, true);
    $app->make(AuditFunctions::class)->register($pdo);
    $app->make(AuditContext::class)->run('fixture:work-model-targets', 'Synthetic target setup only', function () use ($pdo): void {
        $pdo->exec("INSERT INTO users(id,username,password,email,role,auth_source) VALUES
            (3,'A <synthetic target>','not-a-usable-password-hash','target@example.invalid','unknown','ldap')");
    });
    $before = SourceSnapshot::capture($pdo, $mapping);

    $phase = 'native admin options and fresh-role nonadmin refusal';
    $admin = att_one($pdo, 'SELECT * FROM users WHERE id=2');
    $app->make('auth')->guard('web')->setUser((new LegacyUser)->newFromBuilder($admin, 'chrono'));
    $statement = $pdo->query('SELECT id,username FROM users ORDER BY username');
    $expected = $statement->fetchAll(PDO::FETCH_ASSOC);
    $statement->closeCursor();
    $ensure($reads->workModelTargets() === $expected && $expected[0]['id'] === 3 && count($expected) === 3);
    $models = $reads->workModels();
    $ensure(count($models) === 3);

    $member = att_one($pdo, 'SELECT * FROM users WHERE id=1');
    $member['role'] = 'admin';
    $app->make('auth')->guard('web')->setUser((new LegacyUser)->newFromBuilder($member, 'chrono'));
    try {
        $reads->workModelTargets();
        throw new RuntimeException('A stale guard role was accepted.');
    } catch (AuthorizationException $error) {
        $ensure($error->getMessage() === 'Nur die Verwaltung darf Arbeitszeitmodelle festlegen.');
    }
    $ensure(count($reads->workModels()) === 1);

    $phase = 'unchanged source and snapshot boundaries';
    $ensure(SourceSnapshot::capture($pdo, $mapping) === $before
        && array_keys($database->getConnections()) === ['chrono']
        && ! $pdo->inTransaction() && $database->connection('chrono')->transactionLevel() === 0
        && array_intersect_key($GLOBALS, array_flip(['audit_actor', 'audit_reason', '_SESSION'])) === $globals);
    echo json_encode([
        'passed' => true, 'groups' => ['native admin options', 'fresh-role nonadmin refusal', 'unchanged source/snapshot boundaries'],
        'synthetic_memory_only' => true, 'http_ui_runtime_acceptance' => false,
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$phase.' ('.$error::class.").\n");
    exit(1);
} finally {
    $app->make('auth')->forgetGuards();
    $database->purge('chrono');
    $config->set('database.connections', $previousConnections);
    $config->set('session.driver', $previousSession);
    $config->set('app.key', $previousKey);
    $app->forgetInstance('encrypter');
}
