<?php

namespace App\Vacation;

use App\LegacyDatabase\AccountLifecycleV5References;
use App\LegacyDatabase\MemoryOnly;
use PDO;
use LogicException;

/** Fixed additive domain schema. No path opener, runtime activation or inferred account authority. */
final class VacationSchema
{
    public const PROFILE = 'vacation-requests-v7';
    public const VERSIONS = [1, 2, 3, 4, 5, 7];

    public static function tables(): array
    {
        return [
            ['name' => 'vacation_requests', 'presence' => 'required', 'primary_key' => ['id']],
            ['name' => 'vacation_transitions', 'presence' => 'required', 'primary_key' => ['id']],
            ['name' => 'vacation_credits', 'presence' => 'required', 'primary_key' => ['request_id', 'record_id']],
        ];
    }

    /** Caller owns admitted offline transaction; statements never touch any existing table. */
    public static function createStatements(): array
    {
        return [
            <<<'SQL'
CREATE TABLE vacation_requests(
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 user_id INTEGER NOT NULL REFERENCES users(id) CHECK(typeof(user_id)='integer' AND user_id>0),
 start_date TEXT NOT NULL CHECK(typeof(start_date)='text' AND length(start_date)=10 AND start_date GLOB '[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]' AND date(start_date,'+0 days') IS start_date),
 end_date TEXT NOT NULL CHECK(typeof(end_date)='text' AND length(end_date)=10 AND date(end_date,'+0 days') IS end_date AND end_date>=start_date AND julianday(end_date)-julianday(start_date)<=366),
 state TEXT NOT NULL CHECK(state IN ('pending','approved','rejected','withdrawn','cancel_pending','cancelled')),
 revision INTEGER NOT NULL CHECK(typeof(revision)='integer' AND revision>=1),
 created_at INTEGER NOT NULL CHECK(typeof(created_at)='integer' AND created_at>0),
 updated_at INTEGER NOT NULL CHECK(typeof(updated_at)='integer' AND updated_at>=created_at)
)
SQL,
            <<<'SQL'
CREATE TABLE vacation_transitions(
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 request_id INTEGER NOT NULL REFERENCES vacation_requests(id),
 revision INTEGER NOT NULL CHECK(typeof(revision)='integer' AND revision>=1),
 actor_id INTEGER NOT NULL REFERENCES users(id) CHECK(typeof(actor_id)='integer' AND actor_id>0),
 action TEXT NOT NULL CHECK(action IN ('request','approve','reject','withdraw','request_cancel','approve_cancel','reject_cancel')),
 from_state TEXT,
 to_state TEXT NOT NULL,
 reason TEXT NOT NULL CHECK(typeof(reason)='text' AND length(CAST(reason AS BLOB))<=2000 AND instr(reason,char(0))=0),
 occurred_at INTEGER NOT NULL CHECK(typeof(occurred_at)='integer' AND occurred_at>0),
 command_id TEXT NOT NULL CHECK(typeof(command_id)='text' AND length(command_id)=32 AND command_id NOT GLOB '*[^0-9a-f]*'),
 payload_sha256 TEXT NOT NULL CHECK(typeof(payload_sha256)='text' AND length(payload_sha256)=64 AND payload_sha256 NOT GLOB '*[^0-9a-f]*'),
 receipt_json TEXT NOT NULL CHECK(typeof(receipt_json)='text' AND length(CAST(receipt_json AS BLOB))<=65536 AND json_valid(receipt_json) AND json_type(receipt_json)='object'),
 UNIQUE(actor_id,command_id), UNIQUE(request_id,revision),
 CHECK(COALESCE((action='request' AND revision=1 AND from_state IS NULL AND to_state='pending')
 OR (action='approve' AND from_state='pending' AND to_state='approved')
 OR (action='reject' AND from_state='pending' AND to_state='rejected')
 OR (action='withdraw' AND from_state='pending' AND to_state='withdrawn')
 OR (action='request_cancel' AND from_state='approved' AND to_state='cancel_pending')
 OR (action='approve_cancel' AND from_state='cancel_pending' AND to_state='cancelled')
 OR (action='reject_cancel' AND from_state='cancel_pending' AND to_state='approved'),0))
)
SQL,
            <<<'SQL'
CREATE TABLE vacation_credits(
 request_id INTEGER NOT NULL REFERENCES vacation_requests(id),
 record_id INTEGER NOT NULL CHECK(typeof(record_id)='integer' AND record_id>0),
 approval_transition_id INTEGER NOT NULL REFERENCES vacation_transitions(id),
 original_sha256 TEXT NOT NULL CHECK(typeof(original_sha256)='text' AND length(original_sha256)=64 AND original_sha256 NOT GLOB '*[^0-9a-f]*'),
 original_json TEXT NOT NULL CHECK(typeof(original_json)='text' AND length(CAST(original_json AS BLOB))<=65536 AND json_valid(original_json) AND json_type(original_json)='object'),
 PRIMARY KEY(request_id,record_id), UNIQUE(record_id)
)
SQL,
            'CREATE INDEX vacation_requests_own ON vacation_requests(user_id,state,id)',
            'CREATE INDEX vacation_requests_review ON vacation_requests(state,user_id,id)',
            'CREATE INDEX vacation_transitions_request ON vacation_transitions(request_id,id)',
            "CREATE TRIGGER vacation_requests_initial BEFORE INSERT ON vacation_requests WHEN NEW.state<>'pending' OR NEW.revision<>1 OR NEW.updated_at<>NEW.created_at BEGIN SELECT RAISE(ABORT,'Initial vacation request required'); END",
            "CREATE TRIGGER vacation_requests_update BEFORE UPDATE ON vacation_requests WHEN NEW.id IS NOT OLD.id OR NEW.user_id IS NOT OLD.user_id OR NEW.start_date IS NOT OLD.start_date OR NEW.end_date IS NOT OLD.end_date OR NEW.created_at IS NOT OLD.created_at OR OLD.revision=9223372036854775807 OR NEW.revision<>OLD.revision+1 OR NEW.updated_at<OLD.updated_at OR NOT EXISTS(SELECT 1 FROM vacation_transitions WHERE request_id=OLD.id AND revision=OLD.revision AND to_state=OLD.state) OR NOT ((OLD.state='pending' AND NEW.state IN ('approved','rejected','withdrawn')) OR (OLD.state='approved' AND NEW.state='cancel_pending') OR (OLD.state='cancel_pending' AND NEW.state IN ('approved','cancelled'))) BEGIN SELECT RAISE(ABORT,'Vacation state transition required'); END",
            "CREATE TRIGGER vacation_requests_delete BEFORE DELETE ON vacation_requests BEGIN SELECT RAISE(ABORT,'Retain vacation request'); END",
            "CREATE TRIGGER vacation_transition_link BEFORE INSERT ON vacation_transitions WHEN NOT EXISTS(SELECT 1 FROM vacation_requests r WHERE r.id=NEW.request_id AND r.revision=NEW.revision AND r.state=NEW.to_state AND r.updated_at=NEW.occurred_at AND ((NEW.action IN ('request','withdraw','request_cancel') AND NEW.actor_id=r.user_id) OR (NEW.action IN ('approve','reject','approve_cancel','reject_cancel') AND NEW.actor_id<>r.user_id))) OR (NEW.revision>1 AND NOT EXISTS(SELECT 1 FROM vacation_transitions p WHERE p.request_id=NEW.request_id AND p.revision=NEW.revision-1 AND p.to_state=NEW.from_state AND p.occurred_at<=NEW.occurred_at)) BEGIN SELECT RAISE(ABORT,'Linked vacation decision required'); END",
            "CREATE TRIGGER vacation_credit_link BEFORE INSERT ON vacation_credits WHEN NOT EXISTS(SELECT 1 FROM vacation_transitions t JOIN vacation_requests r ON r.id=t.request_id JOIN zeiterfassung z ON z.id=NEW.record_id WHERE t.id=NEW.approval_transition_id AND t.request_id=NEW.request_id AND t.action='approve' AND r.state='approved' AND r.revision=t.revision AND z.user_id=r.user_id AND z.absence_kind='Urlaub' AND json_extract(NEW.original_json,'$.id')=z.id AND json_extract(NEW.original_json,'$.user_id')=r.user_id) BEGIN SELECT RAISE(ABORT,'Exact vacation approval credit required'); END",
            ...self::immutableStatements(),
        ];
    }

    private static function immutableStatements(): array
    {
        $sql = [];
        foreach (['vacation_transitions', 'vacation_credits'] as $table) {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                $sql[] = "CREATE TRIGGER immutable_{$table}_{$operation} BEFORE $operation ON $table BEGIN SELECT RAISE(ABORT,'Append-only vacation history'); END";
            }
        }
        return $sql;
    }

    /** Canonical anonymous producer only; existing-file upgrade has its own offline admission. */
    public static function initializeFreshLocked(PDO $pdo, array $mapping): void
    {
        MemoryOnly::connection($pdo);
        if (!$pdo->inTransaction()) { throw new LogicException('Caller-owned fresh vacation transaction required.'); }
        AccountLifecycleV5References::build($mapping)->requireFreshLoginBudget($pdo);
        foreach (self::createStatements() as $sql) { $pdo->exec($sql); }
        $pdo->exec('INSERT INTO app_migrations(version) VALUES(7)');
    }
}
