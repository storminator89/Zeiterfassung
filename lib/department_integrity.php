<?php

require_once __DIR__.'/admin_reference_status.php';

/** Fixed read-only diagnostic, not a mapping, repair or migration authority. */
function department_integrity_snapshot(PDO $db, mixed $after = null): array
{
    $afterId = $after === null ? 0 : admin_assignment_id($after, 'Ungültige Prüfseite.');
    if (!$db->inTransaction() || (int)$db->query('PRAGMA query_only')->fetchColumn() !== 1) {
        throw new LogicException('Department diagnostics require a query-only snapshot.');
    }
    $counts = ['resolved' => 0, 'unassigned' => 0, 'missing' => 0, 'invalid' => 0, 'unknown' => 0];
    $problems = [];
    $more = false;
    $unaddressable = 0;
    $rows = $db->query('SELECT u.id,u.username,u.department_id,d.id AS matched_department_id
        FROM users u LEFT JOIN departments d ON u.department_id=d.id ORDER BY u.id');
    try {
        while (($row = $rows->fetch(PDO::FETCH_ASSOC)) !== false) {
            $status = admin_reference_status($row['department_id'], $row['matched_department_id']);
            $counts[$status]++;
            if (!in_array($status, ['missing', 'invalid', 'unknown'], true)) {
                continue;
            }
            if ($row['id'] < 1) {
                $unaddressable++;
                continue; // Existing editor/cursor cannot address broken nonpositive user IDs.
            }
            if ($row['id'] <= $afterId) {
                continue;
            }
            if (count($problems) >= 25) {
                $more = true;
                continue; // Full counters continue; only page details are bounded.
            }
            $problems[] = ['id' => (string)$row['id'], 'username' => (string)$row['username'],
                'status' => $status,
                // Only a valid missing numeric ID is displayed. Malformed raw
                // stored text is not copied into diagnostics or coerced to zero.
                'department_id' => $status === 'missing' ? (string)$row['department_id'] : null];
        }
    } finally {
        $rows->closeCursor();
    }
    return ['counts' => $counts, 'checked' => array_sum($counts),
        'problem_count' => $counts['missing'] + $counts['invalid'] + $counts['unknown'],
        'unaddressable_count' => $unaddressable,
        'problems' => $problems, 'after' => $afterId === 0 ? null : (string)$afterId,
        'next_after' => $more ? $problems[array_key_last($problems)]['id'] : null];
}
