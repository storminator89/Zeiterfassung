<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// One coarse native-domain lifecycle, anonymous memory only; no HTTP or paths.
use App\LegacyDatabase\MemoryOnly;
use App\LegacyDatabase\SourceSnapshot;

require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__, 2).'/lib/corrections.php';
$globals = array_intersect_key($GLOBALS, array_flip(['audit_actor', 'audit_reason', '_SESSION']));
$phase = 'anonymous memory and caller-owned transaction';
$ensure = static function (bool $condition): void {
    if (! $condition) { throw new RuntimeException('Coarse correction lifecycle invariant failed.'); }
};

try {
    $ensure($argc === 1);
    $pdo = PDO::connect('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    MemoryOnly::connection($pdo);
    $mapping = json_decode(file_get_contents(dirname(__DIR__, 2).'/docs/framework/schema-map.json'), true, 512, JSON_THROW_ON_ERROR);
    $build = require dirname(__DIR__).'/database/fixtures/legacy_v2.php';
    $build($pdo, $mapping, true);
    $GLOBALS['audit_actor'] = 'fixture:corrections';
    $GLOBALS['audit_reason'] = 'Synthetic correction setup';
    $pdo->exec("UPDATE users SET supervisor_id=2 WHERE id=1;
        UPDATE users SET role='supervisor' WHERE id=2;
        INSERT INTO users(id,username,password,email,role,regelarbeitszeit) VALUES
        (3,'Synthetic admin','not-a-usable-password-hash','admin@example.invalid','admin',8),
        (4,'Synthetic outside','not-a-usable-password-hash','outside@example.invalid','supervisor',8);
        INSERT INTO work_sessions(user_id,record_id,started_at,ended_at) VALUES(1,43,1792648800,1792663200);");
    $originalSession = att_one($pdo, 'SELECT * FROM work_sessions WHERE record_id=43');

    // The trusted transport's identity/role, not any corresponding command keys.
    // Exactly the native transaction shell; no alternative business operations.
    $apply = static function (int $user, string $role, array $command) use ($pdo): void {
        $GLOBALS['audit_actor'] = 'web:'.$user;
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            correction_apply_locked($pdo, $user, $role, $command);
            $pdo->exec('COMMIT');
        } catch (Throwable $error) {
            $pdo->exec('ROLLBACK');
            throw $error;
        }
    };
    $refuses = static function (Closure $operation, string $type, ?int $status = null) use ($pdo, $mapping, $ensure): void {
        $before = SourceSnapshot::capture($pdo, $mapping);
        try { $operation(); }
        catch (Throwable $error) {
            $ensure($error instanceof $type && ($status === null || $error->status === $status)
                && ! $pdo->inTransaction() && SourceSnapshot::capture($pdo, $mapping) === $before);
            return;
        }
        throw new RuntimeException('Expected correction refusal did not occur.');
    };
    $refuses(fn () => correction_apply_locked($pdo, 1, 'user', []), LogicException::class);
    $request = ['action' => 'request', 'record_id' => '42', 'start' => '2026-10-23T08:00+02:00',
        'end' => '2026-10-23T14:00+02:00', 'pause' => '30', 'reason' => 'Synthetic correction reason',
        'user_id' => 3, 'role' => 'admin', 'source' => 'untrusted', 'audit_actor' => 'untrusted'];
    $review = ['action' => 'review', 'id' => 1, 'decision' => 'approved', 'review_reason' => 'Synthetic independent approval'];

    $phase = 'request, foreign authorized approval, self/outside denial and final decision';
    $original = att_one($pdo, 'SELECT * FROM zeiterfassung WHERE id=42');
    $apply(1, 'user', $request);
    $adjustment = att_one($pdo, 'SELECT * FROM time_adjustments WHERE id=1');
    $ensure((int) $adjustment['user_id'] === 1 && $adjustment['status'] === 'pending'
        && $adjustment['original_json'] === json_encode($original));
    $refuses(fn () => $apply(1, 'admin', $review), AttendanceError::class, 403);
    $refuses(fn () => $apply(4, 'supervisor', $review), AttendanceError::class, 403);
    $apply(2, 'supervisor', $review);
    $approved = att_one($pdo, 'SELECT * FROM time_adjustments WHERE id=1');
    $projection = att_one($pdo, 'SELECT * FROM zeiterfassung WHERE id=42');
    $ensure($approved['status'] === 'approved' && (int) $approved['reviewer_id'] === 2
        && $approved['original_json'] === json_encode($original)
        && $projection['endzeit'] === '2026-10-23T14:00:00+02:00'
        && att_one($pdo, 'SELECT ended_at FROM work_sessions WHERE record_id=42')['ended_at'] === correction_time($request['end']));
    $refuses(fn () => $apply(3, 'admin', $review), AttendanceError::class, 403);
    $refuses(fn () => $pdo->exec("UPDATE time_adjustments SET status='rejected' WHERE id=1"), PDOException::class);
    $ensure(att_one($pdo, "SELECT actor,reason FROM audit_log WHERE entity='time_adjustments' AND record_id=1 AND operation='INSERT'")
        === ['actor' => 'web:1', 'reason' => 'Korrekturantrag: Synthetic correction reason']);
    $ensure(att_one($pdo, "SELECT actor,reason FROM audit_log WHERE entity='time_adjustments' AND record_id=1 AND operation='UPDATE'")
        === ['actor' => 'web:2', 'reason' => 'Korrektur #1: Synthetic independent approval']);

    $phase = 'foreign rejection without projection mutation';
    $request['end'] = '2026-10-23T15:00+02:00';
    $apply(1, 'user', $request);
    $review['id'] = 2;
    $review['decision'] = 'rejected';
    $apply(3, 'admin', $review);
    $ensure(att_one($pdo, 'SELECT * FROM zeiterfassung WHERE id=42') === $projection
        && att_one($pdo, 'SELECT status,reviewer_id FROM time_adjustments WHERE id=2') === ['status' => 'rejected', 'reviewer_id' => 3]);
    $refuses(fn () => $apply(2, 'supervisor', $review), AttendanceError::class, 403);

    $phase = 'missing booking approval and cancellation retain original intervals';
    $apply(1, 'user', array_replace($request, ['record_id' => '0', 'start' => '2026-10-24T08:00+02:00', 'end' => '2026-10-24T12:00+02:00']));
    $review['id'] = 3;
    $review['decision'] = 'approved';
    $apply(3, 'admin', $review);
    $added = att_one($pdo, 'SELECT record_id FROM time_adjustments WHERE id=3');
    $ensure((int) $added['record_id'] > 43
        && att_one($pdo, 'SELECT beschreibung FROM zeiterfassung WHERE id=?', [$added['record_id']])['beschreibung'] === 'Korrektur freigegeben'
        && att_one($pdo, 'SELECT id FROM work_sessions WHERE record_id=?', [$added['record_id']]) !== null);
    $apply(1, 'user', ['action' => 'request', 'record_id' => '43', 'cancel' => '1', 'reason' => 'Synthetic cancellation reason']);
    $review['id'] = 4;
    $apply(2, 'supervisor', $review);
    $ensure(att_one($pdo, 'SELECT id FROM zeiterfassung WHERE id=43') === null
        && att_one($pdo, 'SELECT * FROM work_sessions WHERE record_id=43') === $originalSession
        && att_one($pdo, "SELECT before_json FROM audit_log WHERE entity='zeiterfassung' AND record_id=43 AND operation='DELETE'") !== null);

    $phase = 'final-decision failure rolls back projection and audit together';
    $apply(1, 'user', $request);
    $review['id'] = 5;
    $pdo->exec("CREATE TEMP TRIGGER synthetic_decision_failure BEFORE UPDATE OF status ON time_adjustments
        WHEN NEW.status='approved' BEGIN SELECT RAISE(ABORT,'Synthetic final decision failure'); END");
    $refuses(fn () => $apply(2, 'supervisor', $review), PDOException::class);
    $ensure(att_one($pdo, 'SELECT * FROM zeiterfassung WHERE id=42') === $projection
        && att_one($pdo, 'SELECT status FROM time_adjustments WHERE id=5')['status'] === 'pending');
    $pdo->exec('DROP TRIGGER synthetic_decision_failure');
    $ensure(! $pdo->inTransaction());
    echo json_encode(['passed' => true, 'groups' => ['trusted request and independent final approval',
        'rejection without projection mutation', 'missing booking and cancellation with retained original',
        'projection/decision/audit rollback together'], 'synthetic_memory_only' => true,
        'http_ui_laravel_writer_acceptance' => false], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$phase.' ('.$error::class.").\n");
    exit(1);
} finally {
    unset($GLOBALS['audit_actor'], $GLOBALS['audit_reason'], $GLOBALS['_SESSION']);
    foreach ($globals as $key => $value) { $GLOBALS[$key] = $value; }
}
