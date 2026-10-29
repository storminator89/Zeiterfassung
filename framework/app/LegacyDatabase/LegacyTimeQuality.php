<?php

namespace App\LegacyDatabase;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

/** Read-only, partial diagnostics, not a conversion/mapping or migration service. */
final class LegacyTimeQuality
{
    public function inspect(PDO $pdo, int $limit = 100): array
    {
        if ($limit < 1 || $limit > 1000 || ! $pdo->inTransaction()
            || (int) $pdo->query('PRAGMA query_only')->fetchColumn() !== 1) {
            throw new RuntimeException('An admitted query-only snapshot and bounded output are required.');
        }
        // Definitions only; do not load config.php, security.php or the application.
        require_once dirname(__DIR__, 3).'/lib/reporting.php';

        $findings = [];
        $counts = [];
        $offsetFree = 0;
        $records = 0;
        $add = static function (int $id, string $category, ?string $column = null, ?int $relatedId = null) use (&$findings, &$counts, $limit): void {
            $counts[$category] = ($counts[$category] ?? 0) + 1;
            if (count($findings) < $limit) {
                $finding = ['record_id' => $id, 'category' => $category, 'column' => $column];
                if ($relatedId !== null) {
                    $finding['related_record_id'] = $relatedId;
                }
                $findings[] = $finding;
            }
        };

        $rows = $pdo->query('SELECT id,startzeit,endzeit,pause FROM zeiterfassung ORDER BY id');
        while (($row = $rows->fetch(PDO::FETCH_ASSOC)) !== false) {
            $records++;
            $id = (int) $row['id'];
            $usable = true;
            foreach (['startzeit', 'endzeit'] as $column) {
                if ($column === 'endzeit' && ($row[$column] === null || $row[$column] === '')) {
                    continue; // Same open-row contract as report_record_calculation.
                }
                $assessment = $this->timestamp($row[$column]);
                $offsetFree += (int) $assessment['offset_free'];
                if ($assessment['category'] !== null) {
                    $add($id, $assessment['category'], $column);
                    $usable = false;
                }
            }
            $pause = filter_var($row['pause'], FILTER_VALIDATE_INT);
            if ($pause === false || $pause < 0) {
                $add($id, 'invalid_pause', 'pause');
                continue;
            }
            if (! $usable) {
                continue; // Never select a fold instant or normalize a gap to calculate a duration.
            }
            // Preserve the existing report checker contract; stricter shape/DST
            // diagnostics above do not change any runtime parser or stored value.
            $calculation = \report_record_calculation($row);
            if ($calculation['status'] === 'invalid') {
                $category = $calculation['start'] === null || ($row['endzeit'] !== null && $row['endzeit'] !== '' && $calculation['end'] === null)
                    ? 'unreadable_timestamp'
                    : ($calculation['end'] < $calculation['start'] ? 'end_before_start' : 'pause_exceeds_gross');
                $add($id, $category);
            }
        }
        $rows->closeCursor();

        // Null and empty end markers are both open in the existing report checker.
        $open = $pdo->query("SELECT z.id FROM zeiterfassung z JOIN (SELECT user_id FROM zeiterfassung WHERE endzeit IS NULL OR endzeit='' GROUP BY user_id HAVING count(*)>1) multiple ON multiple.user_id=z.user_id WHERE z.endzeit IS NULL OR z.endzeit='' ORDER BY z.id");
        while (($id = $open->fetchColumn()) !== false) {
            $add((int) $id, 'multiple_open_records');
        }
        $open->closeCursor();
        $this->absenceConflicts($pdo, $add);
        $this->workConflicts($pdo, $add);
        $this->absencePairs($pdo, $add);
        $this->openConflicts($pdo, $add);
        $this->originalIntervals($pdo, $findings, $counts, $limit);
        ksort($counts);
        $total = array_sum($counts);

        return [
            'report_version' => 6,
            'scope_status' => 'partial_diagnostic_only',
            'read_only' => true,
            'records_checked' => $records,
            'checks' => [
                'timestamp_shape_calendar_and_berlin_dst' => 'checked',
                'closed_order_and_pause' => 'checked_only_for_unambiguous_supported_timestamps',
                'multiple_open_records' => 'checked',
                'overlap' => 'partial_completed_pairs_and_possible_open_pairs_only',
                'work_whole_day_absence_overlap' => 'checked_only_for_unambiguous_supported_completed_rows',
                'work_work_overlap' => 'checked_only_for_unambiguous_supported_completed_positive_intervals',
                'absence_absence_overlap' => 'checked_only_for_unambiguous_supported_completed_whole_day_rows',
                'open_overlap' => 'possible_only_for_unambiguous_supported_rows_no_guessed_end',
                'original_session_break_boundaries' => 'checked_stored_integer_epochs_only_no_guessed_end',
                'event_session_break_projection_reconciliation' => 'not_checked_beyond_original_interval_boundaries',
                'mapping_or_repair' => 'not_performed_requires_separate_confirmation',
            ],
            'offset_free_values_requiring_berlin_mapping_confirmation' => $offsetFree,
            'needs_review' => $total > 0 || $offsetFree > 0,
            'category_counts' => $counts,
            'finding_count' => $total,
            'findings' => $findings,
            'findings_truncated' => $total > count($findings),
            'finding_limit' => $limit,
        ];
    }

    /** Every eligible same-user work/absence pair exactly once, with bounded helper input/output. */
    private function absenceConflicts(PDO $pdo, callable $add): void
    {
        $columns = 'id,user_id,startzeit,endzeit,pause,absence_kind,beschreibung';
        $absences = $pdo->query('SELECT '.$columns.' FROM zeiterfassung ORDER BY id');
        $works = $pdo->prepare('SELECT '.$columns.' FROM zeiterfassung WHERE user_id=? ORDER BY id');
        while (($absence = $absences->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (! \booking_is_absence($absence) || ! $this->unambiguousCompleted($absence)) {
                continue;
            }
            $check = static function (array $batch) use ($absence, $add): void {
                // One absence and at most 128 work rows: the unchanged shared
                // helper owns whole-local-day, half-open and owner semantics.
                // It can return at most 128 pairs, never a quadratic pair list.
                foreach (\report_absence_conflicts([$absence, ...$batch]) as $conflict) {
                    $add($conflict['work_id'], 'work_absence_overlap', null, $conflict['absence_id']);
                }
            };
            $works->execute([$absence['user_id']]);
            $batch = [];
            while (($work = $works->fetch(PDO::FETCH_ASSOC)) !== false) {
                if (\booking_is_absence($work) || ! $this->unambiguousCompleted($work)) {
                    continue;
                }
                $batch[] = $work;
                if (count($batch) === 128) {
                    $check($batch);
                    $batch = [];
                }
            }
            $works->closeCursor();
            if ($batch !== []) {
                $check($batch);
            }
        }
        $absences->closeCursor();
    }

    /** Every eligible same-user work pair exactly once, without collecting pair lists. */
    private function workConflicts(PDO $pdo, callable $add): void
    {
        $columns = 'id,user_id,startzeit,endzeit,pause,absence_kind,beschreibung';
        $works = $pdo->query('SELECT '.$columns.' FROM zeiterfassung ORDER BY id');
        $others = $pdo->prepare('SELECT '.$columns.' FROM zeiterfassung WHERE user_id=? AND id>? ORDER BY id');
        while (($work = $works->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (\booking_is_absence($work) || ! $this->unambiguousCompleted($work)) {
                continue;
            }
            $span = \report_record_calculation($work);
            if ($span['end'] <= $span['start']) {
                continue; // A zero-duration completed row occupies no half-open interval.
            }
            // Restrict before streaming: never compare different owners or a
            // pair with itself, and report each pair only under its smaller ID.
            $others->execute([$work['user_id'], $work['id']]);
            while (($other = $others->fetch(PDO::FETCH_ASSOC)) !== false) {
                if (\booking_is_absence($other) || ! $this->unambiguousCompleted($other)) {
                    continue;
                }
                $otherSpan = \report_record_calculation($other);
                if ($otherSpan['end'] > $otherSpan['start']
                    && $span['start'] < $otherSpan['end'] && $otherSpan['start'] < $span['end']) {
                    // Same half-open gross-interval comparison as booking's
                    // overlap guard; no guessed pause placement or net repair.
                    $add((int) $work['id'], 'work_work_overlap', null, (int) $other['id']);
                }
            }
            $others->closeCursor();
        }
        $works->closeCursor();
    }

    /** Same-user completed absence pairs; existing booking core owns whole-local-day spans. */
    private function absencePairs(PDO $pdo, callable $add): void
    {
        $columns = 'id,user_id,startzeit,endzeit,pause,absence_kind,beschreibung';
        $absences = $pdo->query('SELECT '.$columns.' FROM zeiterfassung ORDER BY id');
        $others = $pdo->prepare('SELECT '.$columns.' FROM zeiterfassung WHERE user_id=? AND id>? ORDER BY id');
        try {
            while (($absence = $absences->fetch(PDO::FETCH_ASSOC)) !== false) {
                if (! \booking_is_absence($absence) || ! $this->unambiguousCompleted($absence)) {
                    continue;
                }
                [$from, $to] = \booking_span($absence);
                $others->execute([$absence['user_id'], $absence['id']]);
                try {
                    while (($other = $others->fetch(PDO::FETCH_ASSOC)) !== false) {
                        if (! \booking_is_absence($other) || ! $this->unambiguousCompleted($other)) {
                            continue;
                        }
                        [$otherFrom, $otherTo] = \booking_span($other);
                        if ($from < $otherTo && $otherFrom < $to) {
                            $add((int) $absence['id'], 'absence_absence_overlap', null, (int) $other['id']);
                        }
                    }
                } finally {
                    $others->closeCursor();
                }
            }
        } finally {
            $others->closeCursor();
            $absences->closeCursor();
        }
    }

    private function unambiguousCompleted(array $row): bool
    {
        // Do not let the runtime parser choose a legacy fold or normalize a
        // gap/unsupported shape merely to run the shared conflict projection.
        foreach (['startzeit', 'endzeit'] as $column) {
            if ($this->timestamp($row[$column])['category'] !== null) {
                return false;
            }
        }

        return \report_record_calculation($row)['status'] === 'completed';
    }

    /** Possible conflicts only: an open work row has no known end or credited duration. */
    private function openConflicts(PDO $pdo, callable $add): void
    {
        $columns = 'id,user_id,startzeit,endzeit,pause,absence_kind,beschreibung';
        $rows = $pdo->query('SELECT '.$columns.' FROM zeiterfassung ORDER BY id');
        $others = $pdo->prepare('SELECT '.$columns.' FROM zeiterfassung WHERE user_id=? AND id>? AND (CAST(? AS INTEGER)=1 OR endzeit IS NULL OR endzeit=\'\') ORDER BY id');
        try {
            while (($row = $rows->fetch(PDO::FETCH_ASSOC)) !== false) {
                $span = $this->possibleSpan($row);
                if ($span === null) {
                    continue;
                }
                $others->execute([$row['user_id'], $row['id'], (int) $span['open']]);
                try {
                    while (($other = $others->fetch(PDO::FETCH_ASSOC)) !== false) {
                        $otherSpan = $this->possibleSpan($other);
                        if ($otherSpan !== null
                            && ($span['to'] === null || $otherSpan['from'] < $span['to'])
                            && ($otherSpan['to'] === null || $span['from'] < $otherSpan['to'])) {
                            $add((int) $row['id'], 'possible_open_overlap', null, (int) $other['id']);
                        }
                    }
                } finally {
                    $others->closeCursor();
                }
            }
        } finally {
            $others->closeCursor();
            $rows->closeCursor();
        }
    }

    private function possibleSpan(array $row): ?array
    {
        $open = $row['endzeit'] === null || $row['endzeit'] === '';
        foreach (['startzeit', 'endzeit'] as $column) {
            if ($column === 'endzeit' && $open) {
                continue;
            }
            if ($this->timestamp($row[$column])['category'] !== null) {
                return null;
            }
        }
        $calculation = \report_record_calculation($row);
        if ($calculation['status'] === 'invalid') {
            return null;
        }
        if (\booking_is_absence($row)) {
            [$from, $to] = \booking_span($row); // Existing whole-start-day semantics, not a guessed work end.
        } else {
            $from = $calculation['start'];
            $to = $open ? null : $calculation['end'];
            if ($to !== null && $to <= $from) {
                return null;
            }
        }

        return ['from' => $from, 'to' => $to, 'open' => $open];
    }

    /** Original epochs only; no projection comparison, event reconstruction or guessed open end. */
    private function originalIntervals(PDO $pdo, array &$findings, array &$counts, int $limit): void
    {
        $add = static function (string $entity, int $id, string $category, ?string $column = null) use (&$findings, &$counts, $limit): void {
            $counts[$category] = ($counts[$category] ?? 0) + 1;
            if (count($findings) < $limit) {
                // A separate entity discriminator avoids presenting a session or
                // break ID as a zeiterfassung record ID. IDs remain exact strings.
                $findings[] = ['entity' => $entity, 'entity_id' => (string) $id,
                    'category' => $category, 'column' => $column];
            }
        };
        $sessions = $pdo->query('SELECT id,started_at,ended_at FROM work_sessions ORDER BY id');
        try {
            while (($session = $sessions->fetch(PDO::FETCH_ASSOC)) !== false) {
                $valid = true;
                foreach (['started_at', 'ended_at'] as $column) {
                    if ($column === 'ended_at' && $session[$column] === null) {
                        continue;
                    }
                    if (!is_int($session[$column])) {
                        $add('work_sessions', (int) $session['id'], 'original_session_invalid_epoch', $column);
                        $valid = false;
                    }
                }
                if ($valid && $session['ended_at'] !== null && $session['ended_at'] < $session['started_at']) {
                    $add('work_sessions', (int) $session['id'], 'original_session_end_before_start');
                }
            }
        } finally {
            $sessions->closeCursor();
        }

        // Known-v2 preflight already owns reference/owner/cancellation checks.
        // Do not reproduce them or compare amended projections to immutable originals.
        $breaks = $pdo->query('SELECT b.id,b.started_at,b.ended_at,s.started_at AS session_start,s.ended_at AS session_end
            FROM break_intervals b JOIN work_sessions s ON s.id=b.session_id ORDER BY b.id');
        try {
            while (($break = $breaks->fetch(PDO::FETCH_ASSOC)) !== false) {
                $valid = true;
                foreach (['started_at', 'ended_at'] as $column) {
                    if ($column === 'ended_at' && $break[$column] === null) {
                        continue;
                    }
                    if (!is_int($break[$column])) {
                        $add('break_intervals', (int) $break['id'], 'original_break_invalid_epoch', $column);
                        $valid = false;
                    }
                }
                if (!$valid) {
                    continue;
                }
                if ($break['ended_at'] !== null && $break['ended_at'] < $break['started_at']) {
                    $add('break_intervals', (int) $break['id'], 'original_break_end_before_start');
                    continue; // Do not use a reversed break to infer additional boundaries.
                }
                if (!is_int($break['session_start'])
                    || ($break['session_end'] !== null && (!is_int($break['session_end']) || $break['session_end'] < $break['session_start']))) {
                    continue; // The session's separate finding is not a usable comparison span.
                }
                if ($break['started_at'] < $break['session_start']
                    || ($break['session_end'] !== null && ($break['started_at'] > $break['session_end']
                        || ($break['ended_at'] !== null && $break['ended_at'] > $break['session_end'])))) {
                    $add('break_intervals', (int) $break['id'], 'original_break_outside_session');
                }
                if ($break['ended_at'] === null && $break['session_end'] !== null) {
                    $add('break_intervals', (int) $break['id'], 'original_open_break_in_closed_session');
                }
            }
        } finally {
            $breaks->closeCursor();
        }
    }

    /** No conversion is returned: in particular a repeated Berlin hour has no chosen instant. */
    private function timestamp(mixed $value): array
    {
        require_once dirname(__DIR__, 3).'/lib/legacy_timestamp_assessment.php';

        return \legacy_timestamp_assessment($value);
    }
}
