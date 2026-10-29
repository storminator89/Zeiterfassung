<?php

// Definitions only: no bootstrap, credentials, file access or write capability.
require_once __DIR__.'/admin_reference_status.php';
require_once __DIR__.'/api_work_model.php';

/** Own stored assignment and weekly rule, never another account selector or approval grant. */
function own_settings_profile_snapshot(PDO $db, int $trustedOwner): array
{
    if (!$db->inTransaction() || $trustedOwner < 1) {
        throw new LogicException('An authenticated own-user read snapshot is required.');
    }
    $statement = $db->prepare('SELECT
        CAST(u.department_id AS TEXT) AS department_id,
        CAST(d.id AS TEXT) AS department_match, d.name AS department_name,
        CAST(u.supervisor_id AS TEXT) AS supervisor_id,
        CAST(s.id AS TEXT) AS supervisor_match, s.username AS supervisor_name
        FROM users AS u
        LEFT JOIN departments AS d ON d.id=u.department_id
        LEFT JOIN users AS s ON s.id=u.supervisor_id
        WHERE u.id=?');
    $statement->execute([$trustedOwner]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $statement->closeCursor();
    if ($row === false) {
        throw new UnexpectedValueException('Own settings profile unavailable.');
    }
    $reference = static function (string $kind) use ($row): array {
        $status = admin_reference_status($row[$kind.'_id'], $row[$kind.'_match']);
        $name = $status === 'resolved' ? $row[$kind.'_name'] : null;
        if ($status === 'resolved' && !is_string($name)) {
            throw new UnexpectedValueException('Own settings profile unavailable.');
        }
        // No reference IDs, email, other role/source or credential fields leave this read.
        return ['status' => $status, 'name' => $name];
    };
    // The already-published own-only selector retains its strict stored-minute parser.
    // This same PDO transaction supplies both assignment and model observations.
    $model = api_own_work_model_snapshot($db, $trustedOwner, null);

    return [
        'date' => $model['meta']['date'],
        'department' => $reference('department'),
        'supervisor' => $reference('supervisor'),
        'model' => $model['data'],
    ];
}
