<?php

namespace App\Chrono;

use LogicException;

/** Pure state-level transition hints, never an authorization or write capability. */
final class AttendanceWorkflowStatus
{
    public static function fromStatus(array $status): array
    {
        $state = $status['state'] ?? null;
        $record = $status['record_id'] ?? null;
        $exact = $status['record_id_string'] ?? null;
        if (($status['success'] ?? null) !== true
            || ! in_array($state, ['absent', 'working', 'paused'], true)
            || ! array_key_exists('record_id', $status) || ! array_key_exists('record_id_string', $status)
            || ($state === 'absent' ? $record !== null || $exact !== null
                : ! is_int($record) || $record < 1 || ! is_string($exact) || $exact !== (string) $record)) {
            throw new LogicException('A canonical authenticated attendance status is required.');
        }

        // The shared attendance_transition_locked branches own these actions:
        // absent -> start; working -> pause_start/end; paused -> pause_end/end.
        // Its paused end still requires the person's explicit confirmation.
        $actions = match ($state) {
            'absent' => ['start'],
            'working' => ['pause_start', 'end'],
            'paused' => ['pause_end', 'end'],
        };

        // Allowlist only public wire fields; never forward extra internal values.
        return [
            'success' => true, 'state' => $state,
            'record_id' => $record, 'record_id_string' => $exact,
            'workflow' => [
                'version' => 1,
                'allowed_actions' => $actions,
                'action_scope' => 'state_only',
                'requires_write_recheck' => true,
                'expected_record' => $exact,
                'confirmation_required_actions' => $state === 'paused' ? ['end'] : [],
            ],
        ];
    }
}
