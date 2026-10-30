<?php

namespace App\Http\Controllers\Api;

use App\Api\LegacyApiResponse;
use App\Chrono\ApiTimeEntriesQuery;
use App\Chrono\AttendanceWorkflowStatus;
use App\Chrono\ChronoReads;
use App\Chrono\OwnApiCorrectionStatusReads;
use App\Chrono\OwnApiCorrectionRequestStatusReads;
use App\Chrono\OwnApiCorrectionRecordStatusReads;
use App\Chrono\OwnApiWorkModelReads;
use App\Chrono\OwnApiWorkModelVersionsReads;
use AttendanceError;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class LegacyApiReadController
{
    public function __construct(private ChronoReads $reads, private Factory $validation) {}

    public function identity(): Response
    {
        try {
            return LegacyApiResponse::json($this->reads->apiIdentity());
        } catch (Throwable) {
            return LegacyApiResponse::json(['success' => false, 'message' => 'Internal server error'], 500);
        }
    }

    public function attendance(Request $request): Response
    {
        // Explicit additive view matching native raw PHP opt-in. Reconstruct the query:
        // trimming/null normalization must not make a malformed opt-in valid.
        parse_str($request->server('QUERY_STRING', ''), $query);
        if (array_key_exists('workflow', $query) && $query['workflow'] !== '1') {
            return LegacyApiResponse::json(['success' => false, 'message' => 'Invalid workflow option.'], 400);
        }
        try {
            // Exactly the same own authenticated snapshot; no user selector,
            // additional read, writer resolution or change to the default wire.
            $status = $this->reads->apiAttendanceStatus();

            return LegacyApiResponse::json(array_key_exists('workflow', $query)
                ? AttendanceWorkflowStatus::fromStatus($status) : $status);
        } catch (AttendanceError $error) {
            return LegacyApiResponse::json(['success' => false, 'message' => $error->getMessage()], $error->status);
        } catch (Throwable) {
            // Match attendance_json rather than the reporting reader's 500.
            return LegacyApiResponse::json([
                'success' => false,
                'message' => 'Buchung nicht bestätigt. Verbindung prüfen und denselben Vorgang erneut versuchen.',
            ], 503);
        }
    }

    public function corrections(Request $request): Response
    {
        parse_str($request->server('QUERY_STRING', ''), $query);
        require_once dirname(__DIR__, 5).'/lib/api_correction_status.php';
        try {
            $page = \api_correction_status_query($query);
        } catch (\InvalidArgumentException $error) {
            return LegacyApiResponse::json(['success' => false, 'message' => $error->getMessage()], 400);
        }
        $requestId = $page['request_id'] ?? null;
        $recordId = $page['record_id'] ?? null;
        if (! $this->reads instanceof OwnApiCorrectionStatusReads
            || ($recordId !== null && ! $this->reads instanceof OwnApiCorrectionRecordStatusReads)
            || ($recordId === null && $requestId !== null && ! $this->reads instanceof OwnApiCorrectionRequestStatusReads)) {
            return LegacyApiResponse::json(['success' => false, 'message' => 'Correction status unavailable.'], 503);
        }
        try {
            if ($recordId !== null) {
                return LegacyApiResponse::json($this->reads->ownApiCorrectionRecordStatus($recordId, $requestId, $page['status'], $page['limit'], $page['offset']));
            }
            return LegacyApiResponse::json($requestId === null
                ? $this->reads->ownApiCorrectionStatus($page['status'], $page['limit'], $page['offset'])
                : $this->reads->ownApiCorrectionRequestStatus($requestId, $page['status'], $page['limit'], $page['offset']));
        } catch (Throwable) {
            return LegacyApiResponse::json(['success' => false, 'message' => 'Correction status unavailable.'], 503);
        }
    }

    public function workModelVersions(Request $request): Response
    {
        parse_str($request->server('QUERY_STRING', ''), $query);
        require_once dirname(__DIR__, 5).'/lib/api_work_model.php';
        try {
            $page = \api_work_model_versions_query($query);
        } catch (\InvalidArgumentException $error) {
            return LegacyApiResponse::json(['success' => false, 'message' => $error->getMessage()], 400);
        }
        if (! $this->reads instanceof OwnApiWorkModelVersionsReads) {
            return LegacyApiResponse::json(['success' => false, 'message' => 'Work-model versions unavailable.'], 503);
        }
        try {
            return LegacyApiResponse::json($this->reads->ownApiWorkModelVersions($page['limit'], $page['offset']));
        } catch (Throwable) {
            return LegacyApiResponse::json(['success' => false, 'message' => 'Work-model versions unavailable.'], 503);
        }
    }

    public function workModel(Request $request): Response
    {
        parse_str($request->server('QUERY_STRING', ''), $query);
        require_once dirname(__DIR__, 5).'/lib/api_work_model.php';
        try {
            $day = \api_work_model_query($query);
        } catch (\InvalidArgumentException $error) {
            return LegacyApiResponse::json(['success' => false, 'message' => $error->getMessage()], 400);
        }
        if (! $this->reads instanceof OwnApiWorkModelReads) {
            return LegacyApiResponse::json(['success' => false, 'message' => 'Work model unavailable.'], 503);
        }
        try {
            return LegacyApiResponse::json($this->reads->ownApiWorkModel($day));
        } catch (Throwable) {
            return LegacyApiResponse::json(['success' => false, 'message' => 'Work model unavailable.'], 503);
        }
    }

    public function entries(Request $request): Response
    {
        // Laravel's global trimming/null normalization must not silently turn
        // an invalid legacy date into an absent filter. PHP parses this same raw
        // query into $_GET in api.php. No user_id selector is ever forwarded.
        parse_str($request->server('QUERY_STRING', ''), $query);
        require_once dirname(__DIR__, 5).'/lib/api_pagination.php';
        try {
            $pagination = api_pagination($query);
        } catch (\InvalidArgumentException $error) {
            return LegacyApiResponse::json(['success' => false, 'message' => $error->getMessage()], 400);
        }
        foreach (['start_date', 'end_date'] as $field) {
            // Shape only, as before: not date/calendar/timezone normalization.
            if (isset($query[$field]) && $this->validation->make([$field => $query[$field]], [
                $field => ['bail', 'required', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            ])->fails()) {
                return LegacyApiResponse::json(['success' => false, 'message' => 'Invalid '.$field.' format. Use YYYY-MM-DD'], 400);
            }
        }
        $input = new ApiTimeEntriesQuery(
            $query['start_date'] ?? null, $query['end_date'] ?? null,
            $pagination['limit'], $pagination['offset'],
            $pagination['limit'], $pagination['offset'],
        );
        try {
            return LegacyApiResponse::json($this->reads->apiTimeEntries($input));
        } catch (Throwable) {
            // Unexpected read failures retain JSON 500, never a paginator/422.
            return LegacyApiResponse::json(['success' => false, 'message' => 'Internal server error'], 500);
        }
    }
}
