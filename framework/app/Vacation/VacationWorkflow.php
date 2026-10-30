<?php

namespace App\Vacation;

use App\Accounts\AccountLifecycleAuthority;
use AttendanceError;
use Closure;
use DateInterval;
use DatePeriod;
use LogicException;
use PDO;
use RuntimeException;

/**
 * V7 domain core only. Caller admits this exact PDO, owns one IMMEDIATE transaction,
 * registered scoped audit UDFs, commit/rollback and uncertain-outcome handling.
 * No path opener, transaction lifecycle, retry, credentials or HTTP authority here.
 */
final class VacationWorkflow
{
    private const RECORD_COLUMNS = 'id,startzeit,endzeit,pause,beschreibung,standort,user_id,absence_kind,planned_minutes';
    private const RECORD_KEYS = ['id','startzeit','endzeit','pause','beschreibung','standort','user_id','absence_kind','planned_minutes'];
    private const OWN_ACTIONS = ['request', 'withdraw', 'request_cancel'];
    private const EDGES = [
        'approve' => ['pending', 'approved'], 'reject' => ['pending', 'rejected'],
        'withdraw' => ['pending', 'withdrawn'], 'request_cancel' => ['approved', 'cancel_pending'],
        'approve_cancel' => ['cancel_pending', 'cancelled'], 'reject_cancel' => ['cancel_pending', 'approved'],
    ];

    /** Same planning code as approval, inside an already admitted read snapshot. Never writes. */
    public static function approvalPreviewLocked(PDO $db, AccountLifecycleAuthority $actor, int $requestId): array
    {
        self::ready($db);
        self::currentActor($db, $actor);
        $request = self::request($db, $requestId);
        self::authorize($db, $actor, 'approve', $request);
        if ($request['state'] !== 'pending') { throw new AttendanceError('Antrag ist nicht mehr offen.', 409); }
        return self::plan($db, $request);
    }

    /** @param Closure(string, Closure): mixed $auditRunner Trusted writer context; never request data. */
    public static function applyLocked(PDO $db, AccountLifecycleAuthority $actor, VacationCommand $command,
        Closure $auditRunner): array
    {
        self::ready($db);
        self::currentActor($db, $actor); // Current source/role/generation/password/status BEFORE replay.
        $previous = \att_one($db, 'SELECT * FROM vacation_transitions WHERE actor_id=? AND command_id=?', [$actor->id, $command->commandId]);
        $request = $command->action === 'request'
            ? ($previous === null ? null : self::request($db, (int)$previous['request_id']))
            : self::request($db, $command->requestId);
        self::authorize($db, $actor, $command->action, $request);
        $payloadHash = $command->payloadHash();
        if ($previous !== null) {
            if (!hash_equals($previous['payload_sha256'], $payloadHash)
                || $previous['action'] !== $command->action || $request === null
                || $previous['request_id'] !== $request['id']) {
                throw new AttendanceError('Ereigniskennung wurde bereits mit anderem Inhalt verwendet.', 409);
            }
            // Original receipt, not a claim that the request still has this historical state.
            return self::replayReceipt($previous);
        }
        if ($request !== null && ($request['revision'] !== $command->revision
            || $request['revision'] === PHP_INT_MAX || $request['state'] !== self::EDGES[$command->action][0])) {
            throw new AttendanceError('Antrag wurde inzwischen geändert. Neu laden und erneut prüfen.', 409);
        }
        $now = time();
        if ($now < 1 || ($request !== null && $now < $request['updated_at'])) {
            throw new AttendanceError('Serverzeit liegt vor der letzten Antragsänderung. Vorgang nicht bestätigt.', 503);
        }
        // Compute and validate all reachable business inputs before the first mutation.
        $plan = null;
        $originals = [];
        if ($command->action === 'approve') {
            $plan = self::plan($db, $request);
            if (!$plan['approvable']) { throw new AttendanceError('Keine Gutschrifttage nach aktuellem Modell. Antrag und Vorschau prüfen.', 409); }
            if (!hash_equals($plan['plan_hash'], $command->planHash)) {
                throw new AttendanceError('Gutschriftvorschau wurde inzwischen geändert. Neu laden und bewusst erneut prüfen.', 409);
            }
        } elseif ($command->action === 'approve_cancel') {
            $originals = self::cancellationOriginals($db, $request);
        }
        $reason = 'Urlaubsantrag '.$command->action.' / '.$command->commandId;
        return $auditRunner($reason, static function () use ($db, $actor, $command, $request, $payloadHash,
            $now, $plan, $originals, $auditRunner, $reason): array {
            self::assertAuditContext($db, $actor, $reason);
            $credits = [];
            $deleted = [];
            if ($command->action === 'request') {
                \att_exec($db, 'INSERT INTO vacation_requests(user_id,start_date,end_date,state,revision,created_at,updated_at) VALUES(?,?,?,\'pending\',1,?,?)',
                    [$actor->id, $command->startDate, $command->endDate, $now, $now]);
                $id = VacationCommand::positiveInteger($db->lastInsertId());
                $revision = 1;
                $from = null;
                $to = 'pending';
            } else {
                $id = $request['id'];
                $revision = $request['revision'] + 1;
                [$from, $to] = self::EDGES[$command->action];
                if ($command->action === 'approve') {
                    $credits = self::createCredits($db, $request, $plan, $auditRunner, $reason);
                } elseif ($command->action === 'approve_cancel') {
                    foreach ($originals as $row) {
                        // Exact mapped IDs only. No range DELETE and no inferred replacement row.
                        $statement = $db->prepare('DELETE FROM zeiterfassung WHERE id=? AND user_id=?');
                        try {
                            $statement->execute([$row['id'], $request['user_id']]);
                            if ($statement->rowCount() !== 1) { throw new RuntimeException('Vacation credit deletion was not exact.'); }
                        } finally { $statement->closeCursor(); }
                        $deleted[] = (string)$row['id'];
                    }
                }
                $statement = $db->prepare('UPDATE vacation_requests SET state=?,revision=?,updated_at=? WHERE id=? AND revision=? AND state=?');
                try {
                    $statement->execute([$to, $revision, $now, $id, $request['revision'], $from]);
                    if ($statement->rowCount() !== 1) { throw new AttendanceError('Antrag wurde inzwischen geändert.', 409); }
                } finally { $statement->closeCursor(); }
            }
            $receipt = ['success' => true, 'message' => self::message($command->action), 'request_id' => (string)$id, 'revision' => $revision,
                'state' => $to, 'action' => $command->action,
                'credited_record_ids' => array_map(static fn(array $row): string => (string)$row['id'], $credits),
                'deleted_record_ids' => $deleted];
            \att_exec($db, 'INSERT INTO vacation_transitions(request_id,revision,actor_id,action,from_state,to_state,reason,occurred_at,command_id,payload_sha256,receipt_json) VALUES(?,?,?,?,?,?,?,?,?,?,?)',
                [$id, $revision, $actor->id, $command->action, $from, $to, $command->reason, $now,
                    $command->commandId, $payloadHash, json_encode($receipt, JSON_THROW_ON_ERROR)]);
            $transitionId = VacationCommand::positiveInteger($db->lastInsertId());
            foreach ($credits as $row) {
                $json = json_encode($row, JSON_THROW_ON_ERROR); // Exact nine original fields, default JSON encoding.
                \att_exec($db, 'INSERT INTO vacation_credits(request_id,record_id,approval_transition_id,original_sha256,original_json) VALUES(?,?,?,?,?)',
                    [$id, $row['id'], $transitionId, hash('sha256', $json), $json]);
            }
            if (!$db->inTransaction()) { throw new LogicException('The caller-owned vacation transaction was interrupted.'); }
            return $receipt; // Caller must commit before treating this receipt as confirmed.
        });
    }

    private static function ready(PDO $db): void
    {
        require_once dirname(__DIR__, 3).'/lib/attendance.php';
        require_once dirname(__DIR__, 3).'/lib/practice.php';
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite'
            || $db->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION || !$db->inTransaction()
            || $db->query('SELECT version FROM app_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN) !== VacationSchema::VERSIONS) {
            throw new LogicException('An admitted V7 caller-owned vacation transaction is required.');
        }
    }

    private static function currentActor(PDO $db, AccountLifecycleAuthority $actor): void
    {
        $row = \att_one($db, 'SELECT id,role,auth_source,password,account_status,auth_generation FROM users WHERE id=?', [$actor->id]);
        if ($row === null || !$actor->matches($row)) { throw new AttendanceError('Sitzung nicht mehr gültig. Bitte neu anmelden.', 401); }
    }

    private static function request(PDO $db, int $id): array
    {
        $row = $id > 0 ? \att_one($db, 'SELECT * FROM vacation_requests WHERE id=?', [$id]) : null;
        if ($row === null) { throw new AttendanceError('Urlaubsantrag nicht verfügbar.', 404); }
        return $row;
    }

    private static function authorize(PDO $db, AccountLifecycleAuthority $actor, string $action, ?array $request): void
    {
        if ($request === null) {
            if ($action === 'request') { return; }
            throw new AttendanceError('Urlaubsantrag nicht verfügbar.', 404);
        }
        $owner = \att_one($db, 'SELECT id,supervisor_id FROM users WHERE id=?', [$request['user_id']]);
        if ($owner === null) { throw new AttendanceError('Urlaubsantrag nicht verfügbar.', 404); }
        if (in_array($action, self::OWN_ACTIONS, true)) {
            if ($owner['id'] === $actor->id) { return; }
        } elseif ($owner['id'] !== $actor->id && ($actor->role === 'admin'
            || ($actor->role === 'supervisor' && $owner['supervisor_id'] === $actor->id))) {
            return;
        }
        throw new AttendanceError('Keine Berechtigung für diesen Urlaubsantrag.', 403);
    }

    private static function plan(PDO $db, array $request): array
    {
        [$a, $b] = VacationCommand::range($request['start_date'], $request['end_date']);
        $days = [];
        $count = $total = 0;
        foreach (new DatePeriod($a, new DateInterval('P1D'), $b->modify('+1 day')) as $date) {
            $day = $date->format('Y-m-d');
            $minutes = \planned_minutes($db, $request['user_id'], $day);
            if ($minutes < 0 || $minutes > 1440) { throw new AttendanceError('Arbeitszeitmodell enthält ungültige Sollminuten.', 409); }
            $from = $date->getTimestamp();
            if ($minutes > 0) {
                \booking_assert_no_overlap($db, $request['user_id'], $from, $date->modify('+1 day')->getTimestamp());
                $count++;
                $total += $minutes;
            }
            $days[] = ['day' => $day, 'minutes' => $minutes,
                'startzeit' => $minutes > 0 ? \attendance_local($from) : null,
                'endzeit' => $minutes > 0 ? \attendance_local($from + $minutes * 60) : null];
        }
        $plan = ['request_id' => (string)$request['id'], 'revision' => $request['revision'],
            'start_date' => $request['start_date'], 'end_date' => $request['end_date'],
            'days' => $days, 'credit_days' => $count, 'total_minutes' => $total,
            'approvable' => $count > 0, 'warning' => $count === 0 ? 'Keine Gutschrifttage nach aktuellem Modell.' : null];
        return $plan + ['plan_hash' => hash('sha256', json_encode($plan, JSON_THROW_ON_ERROR))];
    }

    private static function createCredits(PDO $db, array $request, array $plan, Closure $auditRunner, string $reason): array
    {
        $expected = [];
        foreach ($plan['days'] as $day) { if ($day['minutes'] > 0) { $expected[$day['startzeit']] = $day; } }
        $sql = 'SELECT '.self::RECORD_COLUMNS.' FROM zeiterfassung WHERE user_id=? AND startzeit IN ('.implode(',', array_fill(0, count($expected), '?')).') ORDER BY startzeit,id';
        $arguments = [$request['user_id'], ...array_keys($expected)];
        $statement = $db->prepare($sql);
        $statement->execute($arguments);
        $before = $statement->fetchAll(PDO::FETCH_ASSOC);
        $statement->closeCursor();
        if ($before !== []) { throw new AttendanceError('Eine erwartete Gutschriftzeile besteht bereits. Neu prüfen.', 409); }
        $count = (int)\att_one($db, 'SELECT COUNT(*) AS n FROM zeiterfassung WHERE user_id=?', [$request['user_id']])['n'];
        // Original writer recomputes model/calendar/overlap under the same caller lock.
        \practice_absence_apply_locked($db, $request['user_id'], $request['start_date'], $request['end_date'], 'Urlaub',
            static fn(string $legacyReason, Closure $operation): mixed => $auditRunner($reason.' · '.$legacyReason, $operation));
        $statement->execute($arguments);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $statement->closeCursor();
        if (count($rows) !== count($expected)
            || (int)\att_one($db, 'SELECT COUNT(*) AS n FROM zeiterfassung WHERE user_id=?', [$request['user_id']])['n'] !== $count + count($expected)) {
            throw new RuntimeException('Vacation approval did not create the exact planned credit set.');
        }
        foreach ($rows as $row) {
            $day = $expected[$row['startzeit']] ?? null;
            if ($day === null || array_keys($row) !== self::RECORD_KEYS || !is_int($row['id']) || $row['id'] < 1
                || $row['user_id'] !== $request['user_id'] || $row['endzeit'] !== $day['endzeit'] || $row['pause'] !== 0
                || $row['beschreibung'] !== 'Urlaub' || $row['standort'] !== '' || $row['absence_kind'] !== 'Urlaub'
                || $row['planned_minutes'] !== $day['minutes']) {
                throw new RuntimeException('Vacation credit differs from its exact confirmed plan.');
            }
            unset($expected[$row['startzeit']]);
        }
        if ($expected !== []) { throw new RuntimeException('A planned vacation credit is missing.'); }
        return $rows;
    }

    private static function cancellationOriginals(PDO $db, array $request): array
    {
        $statement = $db->prepare("SELECT * FROM vacation_transitions WHERE request_id=? AND action='approve' ORDER BY revision");
        $statement->execute([$request['id']]);
        $approvals = $statement->fetchAll(PDO::FETCH_ASSOC);
        $statement->closeCursor();
        if (count($approvals) !== 1) { throw new AttendanceError('Keine eindeutige ursprüngliche Urlaubsfreigabe vorhanden.', 409); }
        $approval = $approvals[0];
        $receipt = self::replayReceipt($approval);
        $statement = $db->prepare('SELECT * FROM vacation_credits WHERE request_id=? ORDER BY record_id');
        $statement->execute([$request['id']]);
        $mappings = $statement->fetchAll(PDO::FETCH_ASSOC);
        $statement->closeCursor();
        $mappedIds = array_map(static fn(array $row): string => (string)$row['record_id'], $mappings);
        if ($mappings === [] || count($mappings) > 366 || $receipt['deleted_record_ids'] !== []
            || count($mappings) !== count($receipt['credited_record_ids'])
            || array_diff($mappedIds, $receipt['credited_record_ids']) !== []) {
            throw new AttendanceError('Keine vollständige unveränderte Gutschriftzuordnung vorhanden.', 409);
        }
        $rows = [];
        foreach ($mappings as $mapping) {
            $original = json_decode($mapping['original_json'], true, 512, JSON_THROW_ON_ERROR);
            if ($mapping['approval_transition_id'] !== $approval['id'] || !is_array($original) || array_keys($original) !== self::RECORD_KEYS
                || json_encode($original, JSON_THROW_ON_ERROR) !== $mapping['original_json']
                || !hash_equals($mapping['original_sha256'], hash('sha256', $mapping['original_json']))
                || $original['id'] !== $mapping['record_id'] || $original['user_id'] !== $request['user_id']) {
                throw new AttendanceError('Gutschriftoriginal kann nicht sicher zugeordnet werden.', 409);
            }
            $current = \att_one($db, 'SELECT '.self::RECORD_COLUMNS.' FROM zeiterfassung WHERE id=? AND user_id=?', [$mapping['record_id'], $request['user_id']]);
            if ($current === null || json_encode($current, JSON_THROW_ON_ERROR) !== $mapping['original_json']
                || \att_one($db, 'SELECT id FROM work_sessions WHERE record_id=? AND ended_at IS NULL', [$mapping['record_id']]) !== null) {
                throw new AttendanceError('Gutschrift wurde inzwischen geändert oder entfernt. Keine automatische Stornierung; zuerst klären.', 409);
            }
            $rows[] = $current;
        }
        return $rows;
    }

    private static function message(string $action): string
    {
        return match ($action) {
            'request' => 'Urlaubsantrag eingereicht. Noch keine Gutschrift gebucht.',
            'approve' => 'Urlaubsantrag freigegeben und bestätigte Gutschrifttage gebucht.',
            'reject' => 'Urlaubsantrag abgelehnt. Keine Gutschrift gebucht.',
            'withdraw' => 'Offener Urlaubsantrag zurückgezogen.',
            'request_cancel' => 'Stornierung beantragt. Gutschriften bleiben bis zur getrennten Freigabe erhalten.',
            'approve_cancel' => 'Urlaubsstornierung freigegeben und unveränderte zugeordnete Gutschriften entfernt.',
            'reject_cancel' => 'Urlaubsstornierung abgelehnt. Bestehende Gutschriften wurden nicht geändert.',
        };
    }

    private static function assertAuditContext(PDO $db, AccountLifecycleAuthority $actor, string $reason): void
    {
        $row = $db->query('SELECT audit_actor() AS actor,audit_reason() AS reason')->fetch(PDO::FETCH_ASSOC);
        if ($row !== ['actor' => 'web:'.$actor->id, 'reason' => $reason]) {
            throw new LogicException('The fixed vacation actor and audit scope are required.');
        }
    }

    private static function replayReceipt(array $transition): array
    {
        $receipt = json_decode($transition['receipt_json'], true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($receipt) || array_keys($receipt) !== ['success','message','request_id','revision','state','action','credited_record_ids','deleted_record_ids']
            || json_encode($receipt, JSON_THROW_ON_ERROR) !== $transition['receipt_json']
            || $receipt['success'] !== true || !is_string($receipt['message']) || $receipt['message'] === ''
            || strlen($receipt['message']) > 500 || preg_match('//u', $receipt['message']) !== 1
            || preg_match('/[\x00-\x1f\x7f]/', $receipt['message']) !== 0 || $receipt['request_id'] !== (string)$transition['request_id']
            || $receipt['revision'] !== $transition['revision'] || $receipt['state'] !== $transition['to_state']
            || $receipt['action'] !== $transition['action']) {
            throw new RuntimeException('Stored vacation receipt is not representable.');
        }
        foreach (['credited_record_ids', 'deleted_record_ids'] as $key) {
            if (!is_array($receipt[$key]) || !array_is_list($receipt[$key]) || count($receipt[$key]) > 366) {
                throw new RuntimeException('Stored vacation receipt is not representable.');
            }
            foreach ($receipt[$key] as $id) {
                if (!is_string($id) || (string)VacationCommand::positiveInteger($id) !== $id) {
                    throw new RuntimeException('Stored vacation receipt is not representable.');
                }
            }
            if (count(array_unique($receipt[$key])) !== count($receipt[$key])) { throw new RuntimeException('Stored vacation receipt repeats a credit.'); }
        }
        return $receipt;
    }
}
