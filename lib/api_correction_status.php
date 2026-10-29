<?php

// Definitions only: no bootstrap, authentication, connection, transaction or writes.
require_once __DIR__.'/api_pagination.php';

/** Raw PHP query only; there is deliberately no subject/actor selector. */
function api_correction_status_query(array $query): array
{
    if (array_diff(array_keys($query), ['status', 'limit', 'offset']) !== []) {
        throw new InvalidArgumentException('Invalid correction status query. Only status, limit and offset are accepted.');
    }
    $status = array_key_exists('status', $query) ? $query['status'] : 'all';
    if (!is_string($status) || !in_array($status, ['all', 'pending', 'approved', 'rejected', 'withdrawn'], true)) {
        throw new InvalidArgumentException('Invalid correction status.');
    }

    return ['status' => $status] + api_pagination($query);
}

/**
 * Own stored facts only, inside the caller's authenticated read snapshot.
 * The trusted owner comes from the existing guard, never from the query/body.
 * No review/request/withdrawal authority or notification read marker is created.
 */
function api_own_correction_status_snapshot(PDO $db, int $trustedOwner, string $status, int $limit, ?int $offset): array
{
    if (!$db->inTransaction() || $trustedOwner < 1) {
        throw new LogicException('An authenticated own-user read snapshot is required.');
    }
    if (!in_array($status, ['all', 'pending', 'approved', 'rejected', 'withdrawn'], true)
        || $limit < 0 || $limit > CHRONO_API_MAX_LIMIT
        || ($offset !== null && ($offset < 0 || $offset > CHRONO_API_MAX_OFFSET))) {
        throw new InvalidArgumentException('Invalid correction status page.');
    }
    $where = 'user_id = :owner'.($status === 'all' ? '' : ' AND status = :status');
    $parameters = [':owner' => $trustedOwner];
    if ($status !== 'all') { $parameters[':status'] = $status; }
    $count = $db->prepare('SELECT COUNT(*) FROM time_adjustments WHERE '.$where);
    $count->execute($parameters);
    $total = (int) $count->fetchColumn();
    $count->closeCursor();

    // Exact IDs and epoch values are decimal strings, not lossy JS numbers.
    // Do not expose reason/proposed times/reviewer/owner/password/original JSON.
    $statement = $db->prepare('SELECT CAST(id AS TEXT) AS request_id, CAST(record_id AS TEXT) AS record_id,
        status, CAST(created_at AS TEXT) AS created_at_epoch, CAST(reviewed_at AS TEXT) AS reviewed_at_epoch
        FROM time_adjustments WHERE '.$where.' ORDER BY id DESC LIMIT :limit OFFSET :offset');
    foreach ($parameters as $name => $value) {
        $statement->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset ?? 0, PDO::PARAM_INT);
    $statement->execute();
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    $statement->closeCursor();
    // SQLite's INTEGER domain, without a native integer/float conversion.
    $integerString = static function (mixed $value, bool $positive = false): bool {
        if (!is_string($value) || preg_match($positive ? '/\A[1-9][0-9]*\z/' : '/\A(?:0|-?[1-9][0-9]*)\z/', $value) !== 1) {
            return false;
        }
        $negative = $value[0] === '-';
        $digits = $negative ? substr($value, 1) : $value;
        $maximum = $negative ? '9223372036854775808' : '9223372036854775807';

        return strlen($digits) < strlen($maximum)
            || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) <= 0);
    };
    foreach ($rows as $row) {
        if (!$integerString($row['request_id'], true)
            || ($row['record_id'] !== null && !$integerString($row['record_id'], true))
            || !in_array($row['status'], ['pending', 'approved', 'rejected', 'withdrawn'], true)
            || !$integerString($row['created_at_epoch'])
            || ($row['reviewed_at_epoch'] !== null && !$integerString($row['reviewed_at_epoch']))) {
            throw new UnexpectedValueException('Stored correction status is not representable.');
        }
    }

    return ['success' => true, 'data' => $rows, 'meta' => [
        'total' => $total, 'filtered' => count($rows), 'limit' => $limit,
        'offset' => $offset, 'status' => $status, 'order' => 'request_id_desc',
    ]];
}
