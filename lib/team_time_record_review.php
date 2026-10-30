<?php
// Already-authorized complete team rows only. This pure projection is NOT an access gate.
require_once __DIR__.'/time_record_review.php';

function team_time_record_review(array $authorizedRows): array
{
    $conflicts = report_absence_conflicts($authorizedRows);
    $conflictLookup = report_conflict_lookup($conflicts);
    $workOverlap = time_record_work_overlap_lookup($authorizedRows);
    $selected = time_record_review_rows($authorizedRows, $conflicts, $workOverlap);
    $rows = [];
    foreach (array_slice($selected, 0, 100) as $row) {
        $calculation = report_record_calculation($row);
        $truncated = false;
        // Show types, NULL/empty and control characters explicitly, never a repaired time.
        $original = static function (mixed $value) use (&$truncated): string {
            $text = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
            if (strlen($text) > 512) {
                $truncated = true;
                return mb_strcut($text, 0, 512, 'UTF-8').'…';
            }
            return $text;
        };
        $rows[] = [
            'id' => (string) $row['id'], 'user_id' => (string) $row['user_id'],
            'username' => (string) $row['username'],
            'start_original' => $original($row['startzeit'] ?? null),
            'end_original' => $original($row['endzeit'] ?? null),
            'pause_original' => $original($row['pause'] ?? null),
            'invalid' => $calculation['status'] === 'invalid',
            'local_time' => time_record_local_time_findings($row) !== [],
            'work_overlap' => isset($workOverlap[report_conflict_key($row)]),
            'absence_conflict' => isset($conflictLookup[report_conflict_key($row)]),
            'truncated' => $truncated,
        ];
    }

    return ['rows' => $rows, 'total' => count($selected), 'truncated' => count($selected) > count($rows)];
}
