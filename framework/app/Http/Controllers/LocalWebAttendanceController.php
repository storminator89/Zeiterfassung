<?php

namespace App\Http\Controllers;

use App\Chrono\ChronoReads;
use App\Chrono\LocalWebAttendance;
use App\View\ExistingUi\AttendancePage;
use App\View\ExistingUi\Copy;
use App\View\ExistingUi\Interactions;
use Closure;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/** Existing page/status/form transport; no actor selector or domain/SQL rules. */
final class LocalWebAttendanceController extends Controller
{
    public function __construct(
        private ChronoReads $reads,
        private LocalWebAttendance $attendance,
        private Factory $auth,
        private Interactions $interactions,
    ) {}

    public function page(Request $request): View|JsonResponse
    {
        if ($request->query('format') === 'status') {
            return $this->json(fn () => $this->reads->attendanceStatus());
        }

        // Registrar already ran RefreshLocalAccount -> auth:web -> auth.session.
        $actor = $this->auth->guard('web')->user();
        $language = $request->query('lang', $request->session()->get('lang', 'de'));
        $language = is_string($language) && in_array($language, ['de', 'en'], true) ? $language : 'de';
        $request->session()->put('lang', $language);
        $copy = new Copy($language);
        $summary = $this->interactions->home ? $this->reads->homeSummary() : null;

        return view('chrono.attendance', ['page' => new AttendancePage(
            $copy, (int) $actor->getAuthIdentifier(), $actor->username,
            $actor->role, $this->reads->attendanceStatus(),
            workingDaysThisMonth: $summary['working_days_this_month'] ?? null,
            currentMonthName: $summary === null ? null : $copy->month($summary['current_month']),
            totalOverHoursFormatted: $summary['total_overtime_formatted'] ?? null,
            positiveOvertime: $summary['positive_overtime'] ?? null,
            dailyHoursFormatted: $summary['daily_hours_formatted'] ?? null,
            holidaysThisWeek: $summary === null ? [] : array_map(static fn (array $holiday): array => [
                'date' => $holiday['formatted_date'], 'name' => $holiday['name'],
            ], $summary['holidays_this_week']),
            interactions: $this->interactions,
            reportInvalidCount: count($summary['invalid_records'] ?? []),
            reportUnresolved: $summary['report_finality']['all'] ?? false,
        )]);
    }

    public function transition(Request $request): JsonResponse
    {
        // Match attendance.php's form-only fields and exact string comparison.
        // Its direct endpoint does NOT pass expectedRecord (unlike other aliases).
        return $this->json(fn () => $this->attendance->transition(
            $request->post('event_id', ''), $request->post('action', ''),
            $request->post('confirm_end_pause') === '1', null,
        ));
    }

    private function json(Closure $operation): JsonResponse
    {
        try {
            return new JsonResponse($operation());
        } catch (\AttendanceError $error) {
            return new JsonResponse(['success' => false, 'message' => $error->getMessage()], $error->status);
        } catch (Throwable) {
            // Preserve legacy's generic uncertain-result response, never log input/SQL/paths.
            return new JsonResponse([
                'success' => false,
                'message' => 'Buchung nicht bestätigt. Verbindung prüfen und denselben Vorgang erneut versuchen.',
            ], 503);
        }
    }
}
