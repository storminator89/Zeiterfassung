<?php

namespace App\Console\Commands;

use App\LegacyDatabase\AuditContext;
use App\LegacyDatabase\AuditFunctions;
use App\LegacyDatabase\FixtureSnapshot;
use App\LegacyDatabase\MemoryOnly;
use App\LegacyDatabase\V2Adoption;
use App\LegacyDatabase\V2Preflight;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use LogicException;
use PDO;
use RuntimeException;
use Throwable;

final class RehearseLegacyAdoption extends Command
{
    protected $signature = 'chrono:rehearse-v2-adoption';

    protected $description = 'Rehearse source-v2 adoption on anonymous synthetic memory databases only (no file ingress).';

    public function handle(AuditContext $context, AuditFunctions $functions): int
    {
        $database = $this->laravel->make('db');
        $default = $database->getDefaultConnection();
        $names = ['chrono_fixture_target', 'chrono_fixture_reference', 'chrono_fixture_rejected'];
        $phase = 'source fixture';

        try {
            $mapping = json_decode(file_get_contents(dirname(base_path()).'/docs/framework/schema-map.json'), true, 512, JSON_THROW_ON_ERROR);
            $build = require database_path('fixtures/legacy_v2.php');
            foreach ($names as $name) {
                // No environment variable, command argument, URL or filename can
                // change this connection. The default connection is never resolved.
                $this->laravel->make('config')->set('database.connections.'.$name, [
                    'driver' => 'chrono_fixture', 'database' => ':memory:', 'prefix' => '',
                    'foreign_key_constraints' => false, 'busy_timeout' => 5000,
                    'journal_mode' => null, 'synchronous' => null, 'transaction_mode' => 'DEFERRED',
                ]);
            }
            $target = $database->connection($names[0])->getPdo();
            $reference = $database->connection($names[1])->getPdo();
            $build($target, $mapping, true);
            $build($reference, $mapping, false);
            // practice_migrate owns the source registration during fixture setup.
            // Afterwards the framework connection-scoped registration owns writes.
            $functions->register($target);
            $functions->register($reference);
            $tables = array_column($mapping['tables'], null, 'name');
            $reference->exec($tables['login_attempts']['create_sql']);
            $referenceRepository = new DatabaseMigrationRepository($database, V2Preflight::LEDGER);
            $referenceRepository->setSource($names[1]);
            $referenceRepository->createRepository();

            $context->run('fixture:history', 'Synthetic approved cancellation', fn () => $this->cancelSyntheticRecord($target));
            $preflight = new V2Preflight($mapping);
            $adoption = new V2Adoption($database, $preflight, $this->laravel->make('config'));
            $before = FixtureSnapshot::capture($target, $mapping);

            $phase = 'v2 adoption and repetition';
            $first = $context->run('fixture:adopter', 'Synthetic source-v2 adoption', fn () => $adoption->register($names[0], $reference));
            $second = $context->run('fixture:adopter', 'Repeated source-v2 adoption', fn () => $adoption->register($names[0], $reference));
            $this->require($first['registered'] && ! $second['registered'], 'Adoption repetition changed registration.');
            $this->require($before === FixtureSnapshot::capture($target, $mapping), 'Adoption changed source data or sequences.');
            $this->require((int) $target->query('SELECT count(*) FROM framework_migrations')->fetchColumn() === 1, 'Adoption ledger was duplicated.');

            $phase = 'preflight refusal before tracking';
            $rejected = $database->connection($names[2])->getPdo();
            $build($rejected, $mapping, true);
            $functions->register($rejected);
            $rejected->exec('CREATE TABLE unknown_schema(value TEXT)');
            $this->expectRefusal(fn () => $adoption->register($names[2], $reference), RuntimeException::class);
            $this->require(! $this->ledgerExists($rejected), 'Unknown schema created tracking state.');
            $rejected->exec('DROP TABLE unknown_schema');
            // A closed original with no projection, cancellation or audit tombstone
            // is not equivalent to the explicitly approved historical exception.
            $context->run('fixture:invalid', 'Synthetic invalid relationship', fn () => $rejected->exec('INSERT INTO work_sessions(user_id,record_id,started_at,ended_at) VALUES(1,999,1,2)'));
            $this->expectRefusal(fn () => $adoption->register($names[2], $reference), RuntimeException::class);
            $this->require(! $this->ledgerExists($rejected), 'Invalid relationship created tracking state.');

            $phase = 'same-PDO audit and context reset';
            $target->exec('BEGIN IMMEDIATE');
            try {
                $context->run('fixture:writer', 'Same PDO writer proof', fn () => $target->exec('UPDATE zeiterfassung SET pause=pause WHERE id=42'));
                $latest = $target->query('SELECT actor,reason FROM audit_log ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
                $this->require($latest === ['actor' => 'fixture:writer', 'reason' => 'Same PDO writer proof'], 'Audit did not use the operation context.');
            } finally {
                $target->exec('ROLLBACK');
            }
            $this->expectRefusal(fn () => $target->exec('UPDATE zeiterfassung SET pause=pause WHERE id=42'), LogicException::class);
            $this->require($before === FixtureSnapshot::capture($target, $mapping), 'Missing context did not fail closed.');

            $phase = 'reconnect lifecycle and ingress guard';
            $database->disconnect($names[0]);
            $fresh = $database->reconnect($names[0])->getPdo();
            $this->require($fresh !== $target, 'Reconnect did not create a fresh PDO.');
            $value = $context->run('fixture:reconnected', 'Reconnect UDF proof', fn () => $fresh->query('SELECT audit_actor()')->fetchColumn());
            $this->require($value === 'fixture:reconnected', 'Reconnect omitted audit functions.');
            $this->expectRefusal(fn () => $fresh->query('SELECT audit_actor()'), LogicException::class);
            $this->expectRefusal(fn () => MemoryOnly::configuration(['database' => '/not-an-accepted-file.sqlite']), RuntimeException::class);
            $this->require($database->getDefaultConnection() === $default && ! isset($database->getConnections()[$default]), 'Rehearsal opened or replaced the default connection.');

            $this->line(json_encode([
                'php' => PHP_VERSION, 'laravel' => $this->laravel->version(),
                'sqlite' => $target->query('SELECT sqlite_version()')->fetchColumn(),
                'pdo_class' => $target::class, 'scope' => 'source-generated anonymous memory fixtures only',
                'source_contract_commit' => $mapping['source_commit'],
                'preflight' => $first,
                'source_counts_preserved' => $before['counts'],
                'source_data_and_sequences_preserved' => true,
                'single_adoption_record_after_repetition' => true,
                'historical_cancellation_tombstone_accepted' => true,
                'schema_and_relationship_refusal_before_tracking' => true,
                'same_pdo_audit_and_missing_context_refusal' => true,
                'reconnect_udfs_and_context_reset' => true,
                'default_connection_opened' => false,
                'file_ingress_supported' => false,
                'remaining' => ['private-file adoption', 'supported new and pre-v2 migration', 'FW-04 lock/concurrency and cutover gates'],
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable $error) {
            // Do not print database contents, SQL/bindings or arbitrary exception text.
            $this->error('Memory-only rehearsal failed at '.$phase.' ('.$error::class.').');

            return self::FAILURE;
        } finally {
            foreach ($names as $name) {
                $database->purge($name);
            }
        }
    }

    private function cancelSyntheticRecord(PDO $pdo): void
    {
        $row = $pdo->query('SELECT * FROM zeiterfassung WHERE id=43')->fetch(PDO::FETCH_ASSOC);
        $start = booking_time($row['startzeit']);
        $end = booking_time($row['endzeit']);
        $pdo->prepare('INSERT INTO work_sessions(id,user_id,record_id,started_at,ended_at) VALUES(103,1,43,?,?)')->execute([$start, $end]);
        $pdo->prepare('INSERT INTO attendance_events(id,source,event_id,user_id,session_id,kind,received_at,payload_hash,response) VALUES(201,?,?,?,?,?,?,?,?)')->execute([
            'web:1', 'synthetic-end-event-0001', 1, 103, 'end', $end,
            hash('sha256', json_encode([1, 'end', false, null], JSON_THROW_ON_ERROR)),
            json_encode(['success' => true, 'event_id' => 'synthetic-end-event-0001', 'record_id' => 43, 'state' => 'absent'], JSON_THROW_ON_ERROR),
        ]);
        $pdo->prepare('INSERT INTO time_adjustments(id,user_id,record_id,proposed_start,proposed_end,proposed_pause,reason,original_json,created_at,cancel) VALUES(301,1,43,?,?,0,?,?,?,1)')->execute([
            $start, $end, 'Synthetic cancellation request', json_encode($row, JSON_THROW_ON_ERROR), $end,
        ]);
        $pdo->exec('DELETE FROM zeiterfassung WHERE id=43');
        $pdo->prepare("UPDATE time_adjustments SET status='approved',reviewer_id=2,reviewed_at=?,review_reason='Synthetic second-person decision' WHERE id=301")->execute([$end + 60]);
    }

    private function ledgerExists(PDO $pdo): bool
    {
        return $pdo->query("SELECT 1 FROM sqlite_schema WHERE type='table' AND name='framework_migrations'")->fetchColumn() !== false;
    }

    private function expectRefusal(callable $operation, string $exceptionClass): void
    {
        try {
            $operation();
        } catch (Throwable $error) {
            if (! $error instanceof $exceptionClass) {
                throw $error;
            }
            return;
        }
        throw new RuntimeException('Expected refusal did not occur.');
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }
}
