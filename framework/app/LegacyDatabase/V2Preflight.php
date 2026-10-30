<?php

namespace App\LegacyDatabase;

use App\Accounts\IntakePolicy;
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
        return $this->inspectProfile($target, $reference, [1, 2]);
    }

    /** Explicit fixed overlay only; the original V2 entry remains unchanged. */
    public function inspectUserAuditV3(PDO $target, PDO $reference): array
    {
        MemoryOnly::connection($reference);
        if ($reference->query('SELECT version FROM app_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN) !== [1, 2, 3]) {
            throw new RuntimeException('The explicit user-audit-v3 reference is required.');
        }

        return $this->inspectProfile($target, $reference, [1, 2, 3]);
    }

    /** Fixed additive fresh-install profile; original V2/V3 admission stays exact. */
    public function inspectIntakePolicyV4(PDO $target, PDO $reference): array
    {
        MemoryOnly::connection($reference);
        if ($reference->query('SELECT version FROM app_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN) !== [1, 2, 3, 4]) {
            throw new RuntimeException('The explicit intake-policy-v4 reference is required.');
        }
        $mapping = $this->mapping;
        if (in_array('user_intake_policy', array_column($mapping['tables'], 'name'), true)) {
            throw new RuntimeException('The unchanged base inventory is required.');
        }
        $mapping['tables'][] = ['name' => 'user_intake_policy', 'presence' => 'required', 'primary_key' => ['id']];
        $checked = (new self($mapping))->inspectProfile($target, $reference, [1, 2, 3, 4]);
        $rows = $target->query(RuntimeAdmission::INTAKE_POLICY_SQL)->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== 1) {
            throw new RuntimeException('The persisted intake policy is required.');
        }
        IntakePolicy::fromRow($rows[0]);

        return $checked;
    }

    /** Separate NEW-only V5 profile; never normalizes V5 into V4 eligibility. */
    public function inspectAccountLifecycleV5(PDO $target, PDO $reference): array
    {
        MemoryOnly::connection($reference);
        if ($reference->query('SELECT version FROM app_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN) !== [1, 2, 3, 4, 5]) {
            throw new RuntimeException('The explicit account-lifecycle-v5 reference is required.');
        }
        $mapping = $this->mapping;
        if (in_array('user_intake_policy', array_column($mapping['tables'], 'name'), true)) {
            throw new RuntimeException('The unchanged base inventory is required.');
        }
        $mapping['tables'][] = ['name' => 'user_intake_policy', 'presence' => 'required', 'primary_key' => ['id']];
        $checked = (new self($mapping))->inspectProfile($target, $reference, [1, 2, 3, 4, 5]);
        $rows = $target->query(RuntimeAdmission::INTAKE_POLICY_SQL)->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== 1) {
            throw new RuntimeException('The persisted V5 intake policy is required.');
        }
        IntakePolicy::fromRow($rows[0]);
        if ((int) $target->query("SELECT COUNT(*) FROM users WHERE account_status IS NULL OR typeof(account_status)<>'text' OR account_status NOT IN ('active','inactive','departed') OR typeof(auth_generation)<>'integer' OR auth_generation<1")->fetchColumn() !== 0
            || (int) $target->query("SELECT COUNT(*) FROM terminal_grants WHERE typeof(account_generation)<>'integer' OR account_generation<1")->fetchColumn() !== 0) {
            throw new RuntimeException('The complete constrained V5 authority state is required.');
        }

        return $checked;
    }

    /** Independent additive vacation profile; older profile entry points remain exact. */
    public function inspectVacationWorkflowV7(PDO $target, PDO $reference): array
    {
        MemoryOnly::connection($reference);
        if ($reference->query('SELECT version FROM app_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN) !== [1, 2, 3, 4, 5, 7]) {
            throw new RuntimeException('Explicit vacation-requests-v7 reference required.');
        }
        $mapping = $this->mapping;
        $additions = [['name' => 'user_intake_policy', 'presence' => 'required', 'primary_key' => ['id']],
            ...\App\Vacation\VacationSchema::tables()];
        if (array_intersect(array_column($mapping['tables'], 'name'), array_column($additions, 'name')) !== []) {
            throw new RuntimeException('Unchanged base inventory required.');
        }
        $mapping['tables'] = [...$mapping['tables'], ...$additions];
        $checked = (new self($mapping))->inspectProfile($target, $reference, [1, 2, 3, 4, 5, 7]);
        $rows = $target->query(RuntimeAdmission::INTAKE_POLICY_SQL)->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== 1) { throw new RuntimeException('Persisted vacation profile intake policy required.'); }
        IntakePolicy::fromRow($rows[0]);
        if ((int)$target->query("SELECT COUNT(*) FROM users WHERE account_status IS NULL OR typeof(account_status)<>'text' OR account_status NOT IN ('active','inactive','departed') OR typeof(auth_generation)<>'integer' OR auth_generation<1")->fetchColumn() !== 0
            || (int)$target->query("SELECT COUNT(*) FROM terminal_grants WHERE typeof(account_generation)<>'integer' OR account_generation<1")->fetchColumn() !== 0) {
            throw new RuntimeException('Complete account authority state required.');
        }
        $this->vacationReferences($target);
        return $checked;
    }

    /** Retained domain history does not require a deleted compatibility credit to reappear. */
    private function vacationReferences(PDO $pdo): void
    {
        $invalid = [
            "SELECT 1 FROM vacation_requests r LEFT JOIN users u ON u.id=r.user_id WHERE u.id IS NULL OR NOT EXISTS(SELECT 1 FROM vacation_transitions t WHERE t.request_id=r.id AND t.revision=r.revision AND t.to_state=r.state AND t.occurred_at=r.updated_at) OR r.revision<>(SELECT COUNT(*) FROM vacation_transitions t WHERE t.request_id=r.id) LIMIT 1",
            "SELECT 1 FROM vacation_transitions t LEFT JOIN vacation_requests r ON r.id=t.request_id LEFT JOIN users u ON u.id=t.actor_id WHERE r.id IS NULL OR u.id IS NULL OR (t.revision=1 AND (t.action<>'request' OR t.occurred_at<>r.created_at)) OR (t.revision>1 AND NOT EXISTS(SELECT 1 FROM vacation_transitions p WHERE p.request_id=t.request_id AND p.revision=t.revision-1 AND p.to_state=t.from_state AND p.occurred_at<=t.occurred_at)) OR (t.action IN ('request','withdraw','request_cancel') AND t.actor_id<>r.user_id) OR (t.action IN ('approve','reject','approve_cancel','reject_cancel') AND t.actor_id=r.user_id) LIMIT 1",
            "SELECT 1 FROM vacation_credits c LEFT JOIN vacation_requests r ON r.id=c.request_id LEFT JOIN vacation_transitions t ON t.id=c.approval_transition_id WHERE r.id IS NULL OR t.id IS NULL OR t.request_id<>c.request_id OR t.action<>'approve' LIMIT 1",
        ];
        foreach ($invalid as $sql) {
            if ($pdo->query($sql)->fetchColumn() !== false) { throw new RuntimeException('Vacation history relationship failed.'); }
        }
        foreach ($pdo->query('SELECT reason,receipt_json FROM vacation_transitions')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (preg_match('//u', $row['reason']) !== 1 || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $row['reason'])
                || !is_object(json_decode($row['receipt_json']))) {
                throw new RuntimeException('Invalid bounded vacation decision content.');
            }
        }
        $projection = $pdo->prepare('SELECT user_id FROM zeiterfassung WHERE id=?');
        $deletions = $pdo->prepare("SELECT before_json FROM audit_log WHERE entity='zeiterfassung' AND record_id=? AND user_id=? AND operation='DELETE'");
        $cancel = $pdo->prepare("SELECT original_json FROM time_adjustments WHERE record_id=? AND user_id=? AND status='approved' AND cancel=1 AND reviewer_id<>user_id AND reviewed_at IS NOT NULL");
        foreach ($pdo->query('SELECT c.*,r.user_id,r.state FROM vacation_credits c JOIN vacation_requests r ON r.id=c.request_id')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $object = json_decode($row['original_json']);
            $original = is_object($object) ? (array)$object : null;
            if (!$object instanceof \stdClass || json_encode($object, JSON_THROW_ON_ERROR) !== $row['original_json']
                || array_keys($original) !== ['id','startzeit','endzeit','pause','beschreibung','standort','user_id','absence_kind','planned_minutes']
                || !is_array($original) || ($original['id'] ?? null) !== $row['record_id'] || ($original['user_id'] ?? null) !== $row['user_id']
                || ($original['absence_kind'] ?? null) !== 'Urlaub' || !hash_equals($row['original_sha256'], hash('sha256', $row['original_json']))) {
                throw new RuntimeException('Invalid immutable vacation credit snapshot.');
            }
            $projection->execute([$row['record_id']]);
            $owner = $projection->fetchColumn();
            if ($owner !== false) {
                if ($owner !== $row['user_id']) { throw new RuntimeException('Vacation credit ownership mismatch.'); }
                continue; // Legitimate later corrections may change current projection bytes.
            }
            $deletions->execute([$row['record_id'], $row['user_id']]);
            $deleted = $deletions->fetchAll(PDO::FETCH_COLUMN);
            $cancel->execute([$row['record_id'], $row['user_id']]);
            $originals = $cancel->fetchAll(PDO::FETCH_COLUMN);
            if ($row['state'] === 'cancelled') { $originals[] = $row['original_json']; }
            $evidence = false;
            foreach ($originals as $candidate) {
                $value = json_decode($candidate, true);
                if (!is_array($value) || ($value['id'] ?? null) !== $row['record_id'] || ($value['user_id'] ?? null) !== $row['user_id']) { continue; }
                foreach ($deleted as $json) { if (json_decode($json, true) === $value) { $evidence = true; } }
            }
            if (!$evidence) { throw new RuntimeException('Missing vacation credit lacks explicit cancellation and deletion evidence.'); }
        }
    }

    private function inspectProfile(PDO $target, PDO $reference, array $versions): array
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

        if ($target->query('SELECT version FROM app_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN) !== $versions) {
            throw new RuntimeException($versions === [1, 2] ? 'Only a complete source v2 database is supported.'
                : ($versions === [1, 2, 3] ? 'Only a complete user-audit-v3 database is supported.' : ($versions === [1, 2, 3, 4] ? 'Only a complete intake-policy-v4 database is supported.' : 'Only a complete account-lifecycle-v5 database is supported.')));
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
                if ($versions === [1, 2, 3, 4, 5, 7]) {
                    $this->vacationProjectionReferences($target, $table);
                } else {
                    $this->projectionReferences($target, $table);
                }
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
            'application_versions' => $versions,
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

    private function vacationProjectionReferences(PDO $pdo, string $table): void
    {
        $rows = $pdo->query("SELECT child.* FROM $table child LEFT JOIN zeiterfassung z ON z.id=child.record_id WHERE child.record_id IS NOT NULL AND (z.id IS NULL OR z.user_id<>child.user_id)")->fetchAll(PDO::FETCH_ASSOC);
        $projection = $pdo->prepare('SELECT user_id FROM zeiterfassung WHERE id=?');
        $cancel = $pdo->prepare("SELECT original_json FROM time_adjustments WHERE record_id=? AND user_id=? AND status='approved' AND cancel=1 AND reviewer_id<>user_id AND reviewed_at IS NOT NULL");
        $vacation = $pdo->prepare("SELECT c.original_json FROM vacation_credits c JOIN vacation_requests r ON r.id=c.request_id JOIN vacation_transitions t ON t.request_id=r.id AND t.revision=r.revision WHERE c.record_id=? AND r.user_id=? AND r.state='cancelled' AND t.action='approve_cancel' AND t.actor_id<>r.user_id");
        $audit = $pdo->prepare("SELECT before_json FROM audit_log WHERE entity='zeiterfassung' AND record_id=? AND user_id=? AND operation='DELETE'");

        foreach ($rows as $row) {
            $projection->execute([$row['record_id']]);
            if ($projection->fetchColumn() !== false || ($table === 'work_sessions' && $row['ended_at'] === null)) {
                throw new RuntimeException('Projection ownership or open-interval relationship failed.');
            }
            $cancel->execute([$row['record_id'], $row['user_id']]);
            $originals = $cancel->fetchAll(PDO::FETCH_COLUMN);
            $vacation->execute([$row['record_id'], $row['user_id']]);
            $originals = [...$originals, ...$vacation->fetchAll(PDO::FETCH_COLUMN)];
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
