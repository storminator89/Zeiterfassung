<?php

namespace App\Http\Controllers\Api;

use App\Api\LegacyApiResponse;
use App\Chrono\ApiAttendance;
use AttendanceError;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class LegacyApiAttendanceController
{
    public function __construct(private ApiAttendance $attendance) {}

    public function __invoke(Request $request): Response
    {
        $input = json_decode($request->getContent(), true);
        // Optional exact string binding; omitted legacy payloads retain null/replay semantics.
        return $this->respond(fn () => $this->attendance->transition(
            $input['event_id'] ?? '', $input['action'] ?? '', ($input['confirm_end_pause'] ?? false) === true,
            \attendance_expected_record(is_array($input) ? $input : []),
        ));
    }

    public function start(Request $request): Response
    {
        $input = json_decode($request->getContent(), true);
        if (! is_array($input)) {
            return LegacyApiResponse::text('Invalid request.', 400);
        }
        if (! empty($input['endzeit']) || ! empty($input['pause'])) {
            return LegacyApiResponse::text('Use a correction request for historical entries.', 409);
        }
        // Ignore client times/location/description. Generated IDs keep the old
        // retry weakness; only a supplied stable event ID is safely replayable.
        return $this->respond(fn () => $this->attendance->transition(
            $input['event_id'] ?? bin2hex(random_bytes(16)), 'start',
        ));
    }

    public function end(Request $request): Response
    {
        $input = json_decode($request->getContent(), true);
        // Preserve the alias's PHP int coercion/missing-id=0 and no array guard.
        return $this->respond(fn () => $this->attendance->transition(
            $input['event_id'] ?? bin2hex(random_bytes(16)), 'end',
            ($input['confirm_end_pause'] ?? false) === true, (int) ($input['id'] ?? 0),
        ));
    }

    private function respond(callable $operation): Response
    {
        try {
            require_once dirname(__DIR__, 5).'/lib/attendance.php';

            // Transport-only metadata, never a rewrite of the stored receipt/hash.
            return LegacyApiResponse::json(\attendance_record_id_string($operation()));
        } catch (AttendanceError $error) {
            return LegacyApiResponse::json(['success' => false, 'message' => $error->getMessage()], $error->status);
        } catch (Throwable) {
            return LegacyApiResponse::json([
                'success' => false,
                'message' => 'Buchung nicht bestätigt. Verbindung prüfen und denselben Vorgang erneut versuchen.',
            ], 503);
        }
    }
}
