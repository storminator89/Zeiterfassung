<?php

namespace App\Http\Controllers\Api;

use App\Api\LegacyApiResponse;
use App\Chrono\ApiTimeEntriesQuery;
use App\Chrono\ChronoReads;
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

    public function attendance(): Response
    {
        try {
            return LegacyApiResponse::json($this->reads->apiAttendanceStatus());
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
