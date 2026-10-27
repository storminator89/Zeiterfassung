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
        $add = static function (int $id, string $category, ?string $column = null) use (&$findings, &$counts, $limit): void {
            $counts[$category] = ($counts[$category] ?? 0) + 1;
            if (count($findings) < $limit) {
                $findings[] = ['record_id' => $id, 'category' => $category, 'column' => $column];
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
        ksort($counts);
        $total = array_sum($counts);

        return [
            'report_version' => 1,
            'scope_status' => 'partial_diagnostic_only',
            'read_only' => true,
            'records_checked' => $records,
            'checks' => [
                'timestamp_shape_calendar_and_berlin_dst' => 'checked',
                'closed_order_and_pause' => 'checked_only_for_unambiguous_supported_timestamps',
                'multiple_open_records' => 'checked',
                'overlap' => 'deferred_pending_shared_booking_conflict_helper',
                'event_session_break_projection_reconciliation' => 'not_checked',
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

    /** No conversion is returned: in particular a repeated Berlin hour has no chosen instant. */
    private function timestamp(mixed $value): array
    {
        $result = ['category' => 'unsupported_timestamp_shape', 'offset_free' => false];
        if (! is_string($value) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})(Z|[+-]\d{2}:\d{2})?$/D', $value, $parts)) {
            return $result;
        }
        $result['offset_free'] = ! isset($parts[7]) || $parts[7] === '';
        if (! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
            || (int) $parts[4] > 23 || (int) $parts[5] > 59 || (int) $parts[6] > 59
            || (! $result['offset_free'] && $parts[7] !== 'Z' && ((int) substr($parts[7], 1, 2) > 23 || (int) substr($parts[7], 4, 2) > 59))) {
            $result['category'] = 'invalid_timestamp';

            return $result;
        }
        $result['category'] = null;
        if (! $result['offset_free']) {
            // Explicit numeric offsets/Z already identify an instant. Do not
            // require them to equal Berlin's contemporary offset.
            return $result;
        }

        $wallText = substr($value, 0, 10).' '.substr($value, 11, 8);
        $wall = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $wallText, new DateTimeZone('UTC'));
        if ($wall === false) {
            $result['category'] = 'invalid_timestamp';

            return $result;
        }
        $zone = new DateTimeZone('Europe/Berlin');
        $epoch = $wall->getTimestamp();
        $offsets = array_unique(array_column($zone->getTransitions($epoch - 172800, $epoch + 172800), 'offset'));
        $candidates = 0;
        foreach ($offsets as $offset) {
            if ((new DateTimeImmutable('@'.($epoch - $offset)))->setTimezone($zone)->format('Y-m-d H:i:s') === $wallText) {
                $candidates++;
            }
        }
        $result['category'] = match (true) {
            $candidates === 0 => 'nonexistent_berlin_local_time',
            $candidates > 1 => 'ambiguous_berlin_local_time',
            default => null,
        };

        return $result;
    }
}
