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
        try {
            // Keep the direct endpoint's PHP coercion and strict JSON true, not
            // Laravel Boolean/422 normalization. Domain validation is only #75.
            return LegacyApiResponse::json($this->attendance->transition(
                $input['event_id'] ?? '', $input['action'] ?? '', ($input['confirm_end_pause'] ?? false) === true,
            ));
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
