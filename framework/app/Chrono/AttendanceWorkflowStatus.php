<?php

namespace App\Chrono;

/** Pure state-level transition hints, never an authorization or write capability. */
final class AttendanceWorkflowStatus
{
    public static function fromStatus(array $status): array
    {
        require_once dirname(__DIR__, 3).'/lib/attendance_workflow_status.php';

        return \attendance_workflow_status($status);
    }
}
