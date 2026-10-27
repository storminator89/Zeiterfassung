<?php

namespace App\LegacyDatabase;

use PDO;
use RuntimeException;

final class V2Preflight
{
    public const LEDGER = 'framework_migrations';

    public function __construct(private readonly array $mapping) {}

    // Both handles are supplied explicitly. This read-only service never opens
    // a path or executes DDL; callers own memory/private-offline file admission.
    public function inspect(PDO $target, PDO $reference): array
    {
        MemoryOnly::connection($reference);

        foreach ($target->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC) as $attached) {
            if (! in_array($attached['name'], ['main', 'temp'], true)) {
                throw new RuntimeException('Attached databases require a separate admission policy.');
            }
        }
        if ((int) $target->query("SELECT count(*) FROM sqlite_schema WHERE type='view'")->fetchColumn() !== 0
            || (int) $target->query("SELECT count(*) FROM sqlite_temp_schema WHERE name NOT LIKE 'sqlite_%'")->fetchColumn() !== 0) {
            throw new RuntimeException('Unexpected view or temporary schema requires review.');
        }

        if ($target->query('PRAGMA quick_check')->fetchColumn() !== 'ok') {
            throw new RuntimeException('SQLite integrity preflight failed.');
        }

        $names = $target->query("SELECT name FROM sqlite_schema WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        $allowed = array_column($this->mapping['tables'], 'name');
        foreach ($this->mapping['tables'] as $table) {
            if ($table['presence'] !== 'optional-until-first-login' && ! in_array($table['name'], $names, true)) {
                throw new RuntimeException('Required source table is missing: '.$table['name']);
            }
        }
        if (array_diff($names, [...$allowed, self::LEDGER]) !== []) {
            throw new RuntimeException('Unexpected tables require review before adoption.');
        }

        // login_attempts is lazy in the source; compare it only when it exists.
        $referenceNames = $reference->query("SELECT name FROM sqlite_schema WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('login_attempts', $names, true) && ! in_array('login_attempts', $referenceNames, true)) {
            throw new RuntimeException('Reference must include the optional source table when present.');
        }

        $schema = [];
        foreach ($names as $name) {
            $actual = $this->structure($target, $name);
            if ($actual !== $this->structure($reference, $name)) {
                throw new RuntimeException('Source schema drift requires review: '.$name);
            }
            if ($name !== self::LEDGER) {
                $schema[$name] = $actual;
            }
        }

        if ($target->query('SELECT version FROM app_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN) !== [1, 2]) {
            throw new RuntimeException('Only a complete source v2 database is supported.');
        }
        if ($target->query('PRAGMA foreign_key_check')->fetch() !== false) {
            throw new RuntimeException('Declared relationship preflight failed; no automatic repairs.');
        }

        foreach ($this->mapping['logical_relationships'] as $relationship) {
            $table = $relationship['from_table'];
            $column = $relationship['from_column'];
            $to = $relationship['to_table'];
            $key = $relationship['to_column'];
            if ($column === 'record_id' && in_array($table, ['work_sessions', 'time_adjustments'], true)) {
                $this->projectionReferences($target, $table);
                continue;
            }
            $system = $table === 'work_models' && $column === 'created_by' ? ' AND child.'.$column.' <> 0' : '';
            $sql = "SELECT count(*) FROM $table child LEFT JOIN $to parent ON parent.$key=child.$column WHERE child.$column IS NOT NULL AND parent.$key IS NULL$system";
            if ((int) $target->query($sql)->fetchColumn() !== 0) {
                throw new RuntimeException('Logical relationship preflight failed: '.$table.'.'.$column);
            }
        }

        if ((int) $target->query('SELECT count(*) FROM attendance_events e JOIN work_sessions s ON s.id=e.session_id WHERE e.user_id<>s.user_id')->fetchColumn() !== 0) {
            throw new RuntimeException('Event/session ownership mismatch.');
        }

        $present = count($schema);
        // A correctly absent lazy login table must not change the adoption ID
        // when the first legitimate login later creates that known table.
        if (! isset($schema['login_attempts'])) {
            $schema['login_attempts'] = $this->structure($reference, 'login_attempts');
        }
        ksort($schema);

        return [
            'schema_signature' => hash('sha256', json_encode($schema, JSON_THROW_ON_ERROR)),
            'application_versions' => [1, 2],
            'tables' => $present,
        ];
    }

    private function projectionReferences(PDO $pdo, string $table): void
    {
        $rows = $pdo->query("SELECT child.* FROM $table child LEFT JOIN zeiterfassung z ON z.id=child.record_id WHERE child.record_id IS NOT NULL AND (z.id IS NULL OR z.user_id<>child.user_id)")->fetchAll(PDO::FETCH_ASSOC);
        $projection = $pdo->prepare('SELECT user_id FROM zeiterfassung WHERE id=?');
        $cancel = $pdo->prepare("SELECT original_json FROM time_adjustments WHERE record_id=? AND user_id=? AND status='approved' AND cancel=1 AND reviewer_id<>user_id AND reviewed_at IS NOT NULL");
        $audit = $pdo->prepare("SELECT before_json FROM audit_log WHERE entity='zeiterfassung' AND record_id=? AND user_id=? AND operation='DELETE'");

        foreach ($rows as $row) {
            $projection->execute([$row['record_id']]);
            if ($projection->fetchColumn() !== false || ($table === 'work_sessions' && $row['ended_at'] === null)) {
                throw new RuntimeException('Projection ownership or open-interval relationship failed.');
            }
            $cancel->execute([$row['record_id'], $row['user_id']]);
            $originals = $cancel->fetchAll(PDO::FETCH_COLUMN);
            $audit->execute([$row['record_id'], $row['user_id']]);
            $deleted = $audit->fetchAll(PDO::FETCH_COLUMN);
            $evidence = false;
            foreach ($originals as $original) {
                $before = json_decode($original ?? '', true);
                if (! is_array($before) || ($before['id'] ?? null) !== $row['record_id'] || ($before['user_id'] ?? null) !== $row['user_id']) {
                    continue;
                }
                foreach ($deleted as $json) {
                    if (json_decode($json ?? '', true) === $before) {
                        $evidence = true;
                    }
                }
            }
            if (! $evidence) {
                throw new RuntimeException('Missing projection has no matching approved cancellation and audit evidence.');
            }
        }
    }

    private function structure(PDO $pdo, string $name): array
    {
        // Identifiers are restricted to source inventory names plus the one ledger.
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name)) {
            throw new RuntimeException('Invalid schema inventory identifier.');
        }
        $statement = $pdo->prepare('SELECT type,name,tbl_name,sql FROM sqlite_schema WHERE tbl_name=? ORDER BY type,name');
        $statement->execute([$name]);
        $objects = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($objects as &$object) {
            if ($object['sql'] !== null) {
                // Tokenize native SQLite definitions; ignore only whitespace/case
                // outside quoted literals. Do not weaken trigger/CHECK expressions.
                preg_match_all('/\'(?:\'\'|[^\'])*\'|"(?:""|[^"])*"|[A-Za-z_][A-Za-z0-9_]*|[0-9]+|\S/', $object['sql'], $tokens);
                $object['sql'] = array_map(static fn (string $token): string => in_array($token[0], ["'", '"'], true) ? $token : strtoupper($token), $tokens[0]);
            }
        }
        unset($object);
        $indexes = $pdo->query('PRAGMA index_list("'.$name.'")')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($indexes as &$index) {
            $index['columns'] = $pdo->query('PRAGMA index_xinfo("'.str_replace('"', '""', $index['name']).'")')->fetchAll(PDO::FETCH_ASSOC);
            unset($index['seq']);
        }
        unset($index);
        usort($indexes, static fn (array $a, array $b): int => $a['name'] <=> $b['name']);

        return [
            'columns' => $pdo->query('PRAGMA table_xinfo("'.$name.'")')->fetchAll(PDO::FETCH_ASSOC),
            'foreign_keys' => $pdo->query('PRAGMA foreign_key_list("'.$name.'")')->fetchAll(PDO::FETCH_ASSOC),
            'indexes' => $indexes,
            'objects' => $objects,
        ];
    }
}
