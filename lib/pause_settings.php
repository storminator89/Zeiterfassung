<?php

/** Validate the complete paired form payload before any stored rule is changed. */
function validatePauseSettings($hoursThresholds, $minimumPauses): array
{
    // An empty submission is not a request to clear all rules: the legacy
    // bootstrap would recreate defaults on the next request anyway.
    if (!is_array($hoursThresholds) || !is_array($minimumPauses)
        || !array_is_list($hoursThresholds) || !array_is_list($minimumPauses)
        || count($hoursThresholds) === 0 || count($hoursThresholds) !== count($minimumPauses)) {
        throw new InvalidArgumentException('Bitte mindestens eine vollständige Pausenregel angeben.');
    }

    $integer = static function ($value): int {
        if ((!is_string($value) && !is_int($value))
            || !preg_match('/^\+?[0-9]+(?:\.0+)?$/D', (string)$value)) {
            throw new InvalidArgumentException('Stundengrenzen und Mindestpausen müssen nichtnegative ganze Zahlen sein.');
        }
        // Normalize leading zeroes and integer decimal notation without float
        // rounding, then reject values outside the supported integer range.
        $value = explode('.', ltrim((string)$value, '+'))[0];
        $value = ltrim($value, '0');
        $value = filter_var($value === '' ? '0' : $value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($value === false) {
            throw new InvalidArgumentException('Stundengrenze oder Mindestpause ist zu groß.');
        }
        return $value;
    };

    $rules = [];
    $seenThresholds = [];
    foreach ($hoursThresholds as $index => $hoursThreshold) {
        $hoursThreshold = $integer($hoursThreshold);
        $minimumPause = $integer($minimumPauses[$index]);
        if (isset($seenThresholds[$hoursThreshold])) {
            throw new InvalidArgumentException('Jede Stundengrenze darf nur einmal vorkommen.');
        }
        $seenThresholds[$hoursThreshold] = true;
        $rules[] = [$hoursThreshold, $minimumPause];
    }
    return $rules;
}

/**
 * Internal complete-list replacement on the caller's already authorized locked
 * PDO. Keep each old/new rule as one secret-free entity in the same transaction.
 * No bootstrap, schema/UDF installation, transaction, actor or SQL callback here.
 */
function replacePauseSettingsLocked(PDO $db, array $rules): void
{
    if (!$db->inTransaction()) {
        throw new LogicException('Pause-settings replacement requires the caller transaction.');
    }
    foreach ($rules as $rule) {
        if (!is_array($rule) || !array_is_list($rule) || count($rule)!==2) {
            throw new InvalidArgumentException('Ungültige vollständige Pausenregeln.');
        }
    }
    if (!array_is_list($rules)
        || $rules!==validatePauseSettings(array_column($rules,0),array_column($rules,1))) {
        throw new InvalidArgumentException('Ungültige vollständige Pausenregeln.');
    }

    // Owner zero keeps installation-wide rules out of employee/direct-team
    // history. Only the existing Admin scope can see these rows. Fixed columns
    // exclude all user/credential data; DB operators remain trusted.
    $db->exec("INSERT INTO audit_log(occurred_at,actor,reason,entity,record_id,user_id,operation,before_json,after_json)
SELECT unixepoch(),audit_actor(),audit_reason(),'pause_settings',id,0,'DELETE',
json_object('id',id,'hours_threshold',hours_threshold,'minimum_pause',minimum_pause),NULL
FROM pause_settings ORDER BY id");
    $db->exec('DELETE FROM pause_settings');
    $statement=$db->prepare('INSERT INTO pause_settings (hours_threshold, minimum_pause) VALUES (?, ?)');
    foreach ($rules as $rule) {
        $statement->execute($rule);
        $statement->closeCursor();
    }
    $db->exec("INSERT INTO audit_log(occurred_at,actor,reason,entity,record_id,user_id,operation,before_json,after_json)
SELECT unixepoch(),audit_actor(),audit_reason(),'pause_settings',id,0,'INSERT',NULL,
json_object('id',id,'hours_threshold',hours_threshold,'minimum_pause',minimum_pause)
FROM pause_settings ORDER BY id");
}
