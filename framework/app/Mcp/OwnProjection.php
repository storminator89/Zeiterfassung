<?php

namespace App\Mcp;

/** Explicit public DTOs. Unknown upstream fields never cross the MCP boundary. */
final class OwnProjection
{
    public static function make(string $endpoint, array $wire, string $account, array $query, ?array $body): array
    {
        if ($endpoint === 'attendance') {
            $state = $wire['state'] ?? null;
            $record = $wire['record_id_string'] ?? null;
            if (! array_key_exists('record_id_string', $wire) || ! in_array($state, ['absent', 'working', 'paused'], true)
                || ($record !== null && ! LocalProfile::id($record)) || ($body === null && ($state === 'absent') !== ($record === null))) {
                throw new ApiFailure('response_refused');
            }
            $result = ['state' => $state, 'record_id_string' => $record];
            if ($body !== null) {
                if (($wire['event_id'] ?? null) !== $body['event_id'] || ! LocalProfile::id($record)
                    || $state !== match ($body['action']) { 'start', 'pause_end' => 'working', 'pause_start' => 'paused', 'end' => 'absent' }
                    || (isset($body['expected_record']) && $record !== $body['expected_record'])) { throw new ApiFailure('response_refused'); }
                $result += ['event_id' => $body['event_id'], 'outcome' => 'confirmed'];
                foreach (['time', 'local_time'] as $field) { $result[$field] = self::text($wire[$field] ?? null, 64); }
                foreach (['pause_warning', 'overlap_warning'] as $field) { if (isset($wire[$field])) { $result[$field] = self::text($wire[$field], 512); } }
                if (isset($wire['overlap_record_ids'])) {
                    if (! is_array($wire['overlap_record_ids']) || ! array_is_list($wire['overlap_record_ids']) || count($wire['overlap_record_ids']) > 100) { throw new ApiFailure('response_refused'); }
                    $result['overlap_record_ids'] = array_map(self::id(...), $wire['overlap_record_ids']);
                }
            } elseif (($query['workflow'] ?? null) === '1') {
                $workflow = $wire['workflow'] ?? null;
                $actions = match ($state) { 'absent' => ['start'], 'working' => ['pause_start', 'end'], 'paused' => ['pause_end', 'end'] };
                $confirm = $state === 'paused' ? ['end'] : [];
                if (! is_array($workflow) || ($workflow['version'] ?? null) !== 1 || ($workflow['action_scope'] ?? null) !== 'state_only'
                    || ($workflow['requires_write_recheck'] ?? null) !== true || ($workflow['expected_record'] ?? null) !== $record
                    || ($workflow['allowed_actions'] ?? null) !== $actions || ($workflow['confirmation_required_actions'] ?? null) !== $confirm) { throw new ApiFailure('response_refused'); }
                $result['workflow'] = ['version' => 1, 'action_scope' => 'state_only', 'requires_write_recheck' => true,
                    'expected_record' => $record, 'allowed_actions' => $actions, 'confirmation_required_actions' => $confirm];
            }
            return $result;
        }
        if (! array_key_exists('data', $wire)) { throw new ApiFailure('response_refused'); }
        $data = $wire['data'];
        if ($endpoint === 'work-model') { return ['data' => $data === null ? null : self::model($data), 'meta' => self::meta($wire, $endpoint, $query)]; }
        if (! is_array($data) || ! array_is_list($data) || count($data) > ($query['limit'] ?? 1)) { throw new ApiFailure('response_refused'); }
        $rows = [];
        foreach ($data as $row) {
            if (! is_array($row)) { throw new ApiFailure('response_refused'); }
            $rows[] = match ($endpoint) {
                'users' => self::identity($row, $account),
                'timeentries' => self::entry($row, $account),
                'corrections' => self::correction($row),
                'work-models' => self::model($row),
            };
        }
        $result = ['data' => $rows];
        if ($endpoint !== 'users') {
            $result['meta'] = self::meta($wire, $endpoint, $query);
            foreach (['request_id', 'record_id', 'before'] as $filter) {
                if (isset($query[$filter]) && ($wire['meta'][$filter] ?? null) !== $query[$filter]) { throw new ApiFailure('response_refused'); }
                if (isset($query[$filter])) { $result['meta'][$filter] = $query[$filter]; }
            }
            $limit = $query['limit']; $offset = $query['offset'] ?? 0;
            $total = $result['meta']['total'] ?? null;
            if (! is_int($total) || $total < 0 || ($result['meta']['filtered'] ?? null) !== count($rows)
                || ($result['meta']['limit'] ?? null) !== $limit || ($result['meta']['offset'] ?? null) !== $offset) { throw new ApiFailure('response_refused'); }
            $next = $offset + count($rows);
            $result['pagination'] = ['limit' => $limit, 'offset' => $offset,
                'next_offset' => count($rows) > 0 && $next < $total && $next <= 2147483647 ? $next : null,
                'snapshot' => false];
        }
        return $result;
    }

    private static function identity(array $row, string $account): array
    {
        if (self::id($row['id'] ?? null) !== $account) { throw new ApiFailure('account_binding_refused'); }
        return ['id' => $account, 'username' => self::text($row['username'] ?? null, 512),
            'email' => self::text($row['email'] ?? null, 512), 'role' => self::text($row['role'] ?? null, 64)];
    }

    private static function entry(array $row, string $account): array
    {
        if (self::id($row['user_id'] ?? null) !== $account) { throw new ApiFailure('account_binding_refused'); }
        $result = ['id' => self::id($row['id'] ?? null), 'user_id' => $account];
        foreach (['startzeit', 'endzeit', 'beschreibung', 'standort'] as $field) {
            $value = $row[$field] ?? null;
            $result[$field] = $value === null ? null : self::text($value, $field === 'beschreibung' ? 4096 : 256);
        }
        $pause = $row['pause'] ?? null;
        if ($pause !== null && ! is_int($pause) && ! is_float($pause) && ! (is_string($pause) && is_numeric($pause))) { throw new ApiFailure('response_refused'); }
        $result['pause'] = $pause;
        if (array_key_exists('absence_kind', $row)) {
            $result['absence_kind'] = $row['absence_kind'] === null ? null : self::text($row['absence_kind'], 256);
        }
        if (array_key_exists('planned_minutes', $row)) {
            if ($row['planned_minutes'] !== null && (! is_int($row['planned_minutes']) || $row['planned_minutes'] < 0 || $row['planned_minutes'] > 1440)) { throw new ApiFailure('response_refused'); }
            $result['planned_minutes'] = $row['planned_minutes'];
        }
        return $result;
    }

    private static function correction(array $row): array
    {
        $status = $row['status'] ?? null;
        if (! in_array($status, ['pending', 'approved', 'rejected', 'withdrawn'], true)) { throw new ApiFailure('response_refused'); }
        $result = ['request_id' => self::id($row['request_id'] ?? null),
            'record_id' => ($row['record_id'] ?? null) === null ? null : self::id($row['record_id']), 'status' => $status];
        foreach (['created_at_epoch', 'reviewed_at_epoch'] as $field) {
            $value = $row[$field] ?? null;
            if (($field === 'created_at_epoch' || $value !== null)
                && ! self::epoch($value)) { throw new ApiFailure('response_refused'); }
            $result[$field] = $value;
        }
        return $result;
    }

    private static function model(mixed $row): array
    {
        if (! is_array($row) || ! is_array($row['weekday_minutes'] ?? null) || ! array_is_list($row['weekday_minutes'])
            || count($row['weekday_minutes']) !== 7) { throw new ApiFailure('response_refused'); }
        foreach ($row['weekday_minutes'] as $minute) { if (! is_int($minute) || $minute < 0 || $minute > 1440) { throw new ApiFailure('response_refused'); } }
        $day = $row['valid_from'] ?? null;
        if (! is_string($day) || ! preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D', $day)
            || ! checkdate((int) substr($day, 5, 2), (int) substr($day, 8, 2), (int) substr($day, 0, 4))) { throw new ApiFailure('response_refused'); }
        return ['model_id' => self::id($row['model_id'] ?? null), 'valid_from' => $day, 'weekday_minutes' => $row['weekday_minutes']];
    }

    private static function meta(array $wire, string $endpoint, array $query): array
    {
        $result = [];
        if (! is_array($wire['meta'] ?? null)) { throw new ApiFailure('response_refused'); }
        $required = match ($endpoint) {
            'work-model' => ['date', 'time_zone', 'scope', 'selection', 'holiday_adjustment', 'legacy_fallback'],
            'work-models' => ['total', 'filtered', 'limit', 'offset', 'order', 'time_zone', 'scope', 'holiday_adjustment', 'legacy_fallback'],
            'corrections' => ['total', 'filtered', 'limit', 'offset', 'status', 'order'],
            'timeentries' => ['total', 'filtered', 'limit', 'offset'],
        };
        foreach ($required as $field) {
            if (! array_key_exists($field, $wire['meta']) || ($field !== 'offset' && $wire['meta'][$field] === null)) {
                throw new ApiFailure('response_refused');
            }
        }
        if (($endpoint === 'work-model' && isset($query['date']) && $wire['meta']['date'] !== $query['date'])
            || ($endpoint === 'corrections' && $wire['meta']['status'] !== ($query['status'] ?? 'all'))
            || ($endpoint === 'timeentries' && isset($query['entry_kind']) && ($wire['meta']['entry_kind'] ?? null) !== $query['entry_kind'])) {
            throw new ApiFailure('response_refused');
        }
        foreach (['total', 'filtered', 'limit', 'offset'] as $field) {
            if (array_key_exists($field, $wire['meta'])) {
                $value = $wire['meta'][$field];
                if ($value !== null && (! is_int($value) || $value < 0)) { throw new ApiFailure('response_refused'); }
                $result[$field] = $value;
            }
        }
        $allowed = ['time_zone' => ['Europe/Berlin'], 'scope' => ['own'],
            'selection' => ['valid_from_desc_id_desc'], 'holiday_adjustment' => ['not_applied'],
            'legacy_fallback' => ['not_returned'], 'order' => ['valid_from_desc_id_desc', 'request_id_desc'],
            'status' => ['all', 'pending', 'approved', 'rejected', 'withdrawn'], 'entry_kind' => ['work', 'absence']];
        foreach ($allowed as $field => $values) {
            if (isset($wire['meta'][$field])) {
                if (! in_array($wire['meta'][$field], $values, true)) { throw new ApiFailure('response_refused'); }
                $result[$field] = $wire['meta'][$field];
            }
        }
        if (isset($wire['meta']['date'])) {
            $day = $wire['meta']['date'];
            if (! is_string($day) || ! preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D', $day)
                || ! checkdate((int) substr($day, 5, 2), (int) substr($day, 8, 2), (int) substr($day, 0, 4))) { throw new ApiFailure('response_refused'); }
            $result['date'] = $day;
        }
        return $result;
    }

    private static function id(mixed $value): string
    {
        if (is_int($value) && $value > 0) { $value = (string) $value; }
        if (! LocalProfile::id($value)) { throw new ApiFailure('response_refused'); }
        return $value;
    }

    private static function epoch(mixed $value): bool
    {
        if (! is_string($value) || ! preg_match('/\A(?:0|-?[1-9][0-9]{0,18})\z/D', $value)) { return false; }
        $negative = str_starts_with($value, '-'); $digits = $negative ? substr($value, 1) : $value;
        return strlen($digits) < 19 || strcmp($digits, $negative ? '9223372036854775808' : '9223372036854775807') <= 0;
    }

    private static function text(mixed $value, int $maximum): string
    {
        if (! is_string($value) || strlen($value) > $maximum || ! mb_check_encoding($value, 'UTF-8')
            || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $value)) { throw new ApiFailure('response_refused'); }
        return $value;
    }
}
