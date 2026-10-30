<?php

// Definitions only: no bootstrap, authentication, connections, transactions or writes.
require_once __DIR__.'/practice.php';

/** Canonical selected day; this validates shape/calendar, never changes a supplied date. */
function api_work_model_day(mixed $day): string
{
    if (!is_string($day) || preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $day) !== 1
        || str_starts_with($day, '0000-')) {
        throw new InvalidArgumentException('Invalid work-model date. Use YYYY-MM-DD.');
    }
    try {
        practice_date($day);
    } catch (Throwable) {
        throw new InvalidArgumentException('Invalid work-model date. Use YYYY-MM-DD.');
    }

    return $day;
}

/** Raw PHP query only; no model ID, owner, role or arbitrary subject selector. */
function api_work_model_query(array $query): ?string
{
    if (array_diff(array_keys($query), ['date']) !== []) {
        throw new InvalidArgumentException('Invalid work-model query. Only date is accepted.');
    }

    return array_key_exists('date', $query) ? api_work_model_day($query['date']) : null;
}

/** Own stored version only; no holiday/absence/target/balance calculation or write authority. */
function api_own_work_model_snapshot(PDO $db, int $trustedOwner, ?string $day): array
{
    if (!$db->inTransaction() || $trustedOwner < 1) {
        throw new LogicException('An authenticated own-user read snapshot is required.');
    }
    $day = api_work_model_day($day ?? (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format('Y-m-d'));
    // Same effective-version selection as planned_minutes; not the bounded UI list.
    $statement = $db->prepare('SELECT CAST(id AS TEXT) AS model_id,valid_from,minutes_json
        FROM work_models WHERE user_id=? AND valid_from<=? ORDER BY valid_from DESC,id DESC LIMIT 1');
    $statement->execute([$trustedOwner, $day]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $statement->closeCursor();
    $model = $row === false ? null : api_work_model_row($row);

    return ['success' => true, 'data' => $model, 'meta' => [
        'date' => $day, 'time_zone' => 'Europe/Berlin', 'scope' => 'own',
        'selection' => 'valid_from_desc_id_desc', 'holiday_adjustment' => 'not_applied',
        'legacy_fallback' => 'not_returned',
    ]];
}

/** Same exact stored-row projection for the effective version and bounded discovery. */
function api_work_model_row(array $row): array
{
    if (!is_string($row['model_id']) || preg_match('/\A[1-9][0-9]*\z/', $row['model_id']) !== 1
        || strlen($row['model_id']) > 19 || (strlen($row['model_id']) === 19 && strcmp($row['model_id'], '9223372036854775807') > 0)) {
        throw new UnexpectedValueException('Stored work model is not representable.');
    }
    try {
        $from = api_work_model_day($row['valid_from']);
    } catch (Throwable) {
        throw new UnexpectedValueException('Stored work model is not representable.');
    }
    // Exact source lexemes, not json_decode floats: a fractional value can
    // round/underflow to a whole float before an ordinary integral check.
    // Both original writers emit nonnegative plain numbers; SQLite's
    // initial trigger can add an all-zero fraction such as 480.0.
    $space = '[ \t\r\n]*';
    $token = '((?:0|[1-9][0-9]*)(?:\.0+)?)';
    $pattern = '~\A'.$space.'\['.$space.$token.str_repeat($space.','.$space.$token, 6).$space.'\]'.$space.'\z~';
    if (!is_string($row['minutes_json']) || preg_match($pattern, $row['minutes_json'], $lexemes) !== 1) {
        throw new UnexpectedValueException('Stored work model is not representable.');
    }
    $minutes = [];
    foreach (array_slice($lexemes, 1) as $lexeme) {
        $whole = explode('.', $lexeme, 2)[0];
        if (strlen($whole) > 4 || (strlen($whole) === 4 && strcmp($whole, '1440') > 0)) {
            throw new UnexpectedValueException('Stored work model is not representable.');
        }
        $minutes[] = (int) $whole; // At most four checked decimal digits, no float path.
    }
    return ['model_id' => $row['model_id'], 'valid_from' => $from, 'weekday_minutes' => $minutes];
}

/** Raw own-version discovery only; no date/model/subject/role selector. */
function api_work_model_versions_query(array $query): array
{
    if (array_diff(array_keys($query), ['limit', 'offset']) !== []) {
        throw new InvalidArgumentException('Invalid work-model versions query. Only limit and offset are accepted.');
    }
    require_once __DIR__.'/api_pagination.php';
    return api_pagination($query);
}

/** Own stored timeline, not an effective/fallback/entitlement or approval calculation. */
function api_own_work_model_versions_snapshot(PDO $db, int $trustedOwner, int $limit, ?int $offset): array
{
    if (!$db->inTransaction() || $trustedOwner < 1) {
        throw new LogicException('An authenticated own-user read snapshot is required.');
    }
    require_once __DIR__.'/api_pagination.php';
    if ($limit < 0 || $limit > CHRONO_API_MAX_LIMIT
        || ($offset !== null && ($offset < 0 || $offset > CHRONO_API_MAX_OFFSET))) {
        throw new InvalidArgumentException('Invalid work-model versions page.');
    }
    $count = $db->prepare('SELECT COUNT(*) FROM work_models WHERE user_id=?');
    $count->execute([$trustedOwner]);
    $total = (int)$count->fetchColumn();
    $count->closeCursor();
    $statement = $db->prepare('SELECT CAST(id AS TEXT) AS model_id,valid_from,minutes_json
        FROM work_models WHERE user_id=:owner ORDER BY valid_from DESC,id DESC LIMIT :limit OFFSET :offset');
    $statement->bindValue(':owner', $trustedOwner, PDO::PARAM_INT);
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset ?? 0, PDO::PARAM_INT);
    $statement->execute();
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    $statement->closeCursor();
    // A malformed selected row refuses the whole page; never coerce or return a partial page.
    $models = array_map('api_work_model_row', $rows);
    return ['success' => true, 'data' => $models, 'meta' => [
        'total' => $total, 'filtered' => count($models), 'limit' => $limit, 'offset' => $offset,
        'order' => 'valid_from_desc_id_desc', 'time_zone' => 'Europe/Berlin', 'scope' => 'own',
        'holiday_adjustment' => 'not_applied', 'legacy_fallback' => 'not_returned',
    ]];
}
