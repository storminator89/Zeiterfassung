<?php

// Definitions only: no bootstrap, session, connection, writes or automatic read.
require_once __DIR__.'/attendance.php';

/** Internal same-PDO query; errors are not an empty roster or absent status. */
function chrono_team_status_rows(PDO $pdo, string $sql, array $arguments): array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($arguments);
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    $statement->closeCursor();

    return $rows;
}

/**
 * Already-authorized current direct-team/Admin scope, in the caller's snapshot.
 * Same existing display-only reliability algorithm, not a new status authority.
 * One trusted server instant for all rows; no request clock, identity or role.
 */
function chrono_team_status_snapshot(PDO $pdo, int $actor, string $role, DateTimeImmutable $loaded): array
{
    if (! $pdo->inTransaction()) { throw new LogicException('The caller must own a team-status read snapshot.'); }
    if ($actor < 1 || ! in_array($role, ['admin', 'supervisor'], true)) {
        throw new AttendanceError('Teamstatus nicht verfügbar.', 403);
    }
    $loaded = $loaded->setTimezone(new DateTimeZone('Europe/Berlin'));
    $people = [];
    foreach (chrono_team_status_rows($pdo, "SELECT id,username FROM users WHERE supervisor_id = ? OR ?='admin' ORDER BY username,id", [$actor, $role]) as $person) {
        $people[] = [
            'username' => $person['username'],
            'state' => chrono_team_status_state($pdo, (int) $person['id'], $loaded->getTimestamp()),
        ];
    }

    return ['loaded_at' => $loaded->format(DateTimeInterface::ATOM), 'people' => $people];
}

/**
 * Native-only read boundary after the unchanged native bootstrap. Recheck the
 * same existing native auth-version and current role inside this one snapshot.
 * No session mutation/credential output. The private role output lets native
 * historical reads keep the same current direct-team/Admin predicate.
 */
function chrono_native_team_status(PDO $pdo, int $actor, string $authVersion, ?string &$currentRole = null): array
{
    $currentRole = null;
    if ($pdo->inTransaction()) { throw new LogicException('Native team status requires an idle PDO.'); }
    $pdo->beginTransaction();
    try {
        $current = att_one($pdo, 'SELECT role,password FROM users WHERE id=?', [$actor]);
        if ($actor < 1 || $current === null || $authVersion === ''
            || ! hash_equals($authVersion, hash('sha256', (string) $current['password']))) {
            throw new AttendanceError('Teamstatus nicht verfügbar.', 401);
        }
        $role = (string) $current['role'];
        unset($current);
        if (! in_array($role, ['admin', 'supervisor'], true)) { throw new AttendanceError('Teamstatus nicht verfügbar.', 403); }
        // Server-internal role context only, never part of the status DTO. Retain
        // it even if a later status query fails, so native history cannot reuse a
        // now-known stale Admin role; a failed identity/role check leaves NULL.
        $currentRole = $role;
        $result = chrono_team_status_snapshot($pdo, $actor, $role, new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')));
        if (! $pdo->commit()) { throw new RuntimeException('Team-status snapshot could not be completed.'); }

        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $error;
    }
}

/** Display reliability only: no reconciliation, mutation or historical rule. */
function chrono_team_status_state(PDO $pdo, int $user, int $now): string
{
    // At most two candidates detect ambiguity without scanning closed history.
    // No notes, absence reasons, projects, credentials or record IDs leave here.
    $sessions = chrono_team_status_rows($pdo, "SELECT id,record_id,started_at,ended_at FROM work_sessions WHERE user_id=? AND (ended_at IS NULL OR ended_at='') LIMIT 2", [$user]);
    $projections = chrono_team_status_rows($pdo, "SELECT id,startzeit,endzeit FROM zeiterfassung WHERE user_id=? AND (endzeit IS NULL OR endzeit='') LIMIT 2", [$user]);
    $breaks = chrono_team_status_rows($pdo, "SELECT b.session_id,b.started_at,b.ended_at,s.ended_at AS session_ended FROM break_intervals b JOIN work_sessions s ON s.id=b.session_id WHERE s.user_id=? AND (b.ended_at IS NULL OR b.ended_at='') LIMIT 2", [$user]);
    if (count($sessions) > 1 || count($projections) > 1 || count($breaks) > 1) {
        return 'unknown';
    }
    $session = $sessions[0] ?? null;
    $projection = $projections[0] ?? null;
    $break = $breaks[0] ?? null;
    $expectedRecord = null;
    $projectionStart = null;
    if ($projection !== null) {
        $projectionStart = report_stored_epoch($projection['startzeit']);
        if ($projectionStart === null || $projectionStart > $now) {
            return 'unknown';
        }
        $expectedRecord = (int) $projection['id'];
    }
    if ($session !== null) {
        $started = filter_var($session['started_at'], FILTER_VALIDATE_INT);
        if ($session['ended_at'] !== null || $projection === null
            || filter_var($session['record_id'], FILTER_VALIDATE_INT) !== $expectedRecord
            || $started === false || $started > $now || $started !== $projectionStart) {
            return 'unknown';
        }
        if ($break !== null) {
            $breakStarted = filter_var($break['started_at'], FILTER_VALIDATE_INT);
            if ($break['ended_at'] !== null || $break['session_ended'] !== null
                || (int) $break['session_id'] !== (int) $session['id']
                || $breakStarted === false || $breakStarted < $started || $breakStarted > $now) {
                return 'unknown';
            }
        }
    } elseif ($break !== null || ($projection !== null && $projection['endzeit'] !== null)) {
        // attendance_status intentionally recognizes NULL, not an unmanaged
        // empty-end marker. Never present that ambiguous case as absent.
        return 'unknown';
    }

    $status = attendance_status($pdo, $user);
    $state = $status['state'] ?? null;
    if (! in_array($state, ['working', 'paused', 'absent'], true)
        || ($status['record_id'] ?? null) !== $expectedRecord
        || ($state === 'absent') !== ($expectedRecord === null)
        || ($state === 'paused') !== ($break !== null)) {
        return 'unknown';
    }

    return $state;
}
