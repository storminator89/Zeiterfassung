<?php

namespace App\Http\Controllers;

use App\Chrono\ChronoReads;
use App\Chrono\TeamReportPeriodReads;
use App\View\ExistingUi\AttendancePage;
use App\View\ExistingUi\Copy;
use App\View\ExistingUi\Interactions;
use App\View\ExistingUi\SupervisorPage;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Http\Response;
use Illuminate\Http\Request;

/** Dormant existing supervisor UI; route/chrome/Chart asset admission is separate. */
final class LocalWebSupervisorController extends Controller
{
    public function __construct(
        private ChronoReads $reads,
        private Factory $auth,
        private Interactions $interactions,
    ) {}

    public function index(Request $request): Response
    {
        $actor = $this->auth->guard('web')->user();
        $language = $request->query('lang', $request->session()->get('lang', 'de'));
        $language = is_string($language) && in_array($language, ['de', 'en', 'zh'], true) ? $language : 'de';
        $request->session()->put('lang', $language);
        // Preserve native $_GET strings/arrays before Laravel's trimming/null normalization.
        parse_str((string) $request->server->get('QUERY_STRING', ''), $query);
        $search = isset($query['user_search']) && is_string($query['user_search']) ? trim($query['user_search']) : '';
        require_once dirname(__DIR__, 4).'/lib/supervisor_table_period.php';
        $period = \supervisor_table_period((string) $request->server->get('QUERY_STRING', ''));
        $periodRequested = $period['range'] !== null || $period['invalid'];
        $periodUnavailable = $periodRequested && ! $this->reads instanceof TeamReportPeriodReads;
        // teamReport rechecks fresh role and direct-team scope in its admitted snapshot.
        $report = $periodUnavailable
            ? ['available' => false, 'invalid_records' => [], 'absence_conflicts' => [], 'overtime' => [],
                'details' => [], 'groups' => [], 'total_records' => 0, 'total_pages' => 1, 'page' => 1]
            : ($periodRequested
                ? $this->reads->teamReportPeriod($search, $query['page'] ?? null, $period['range'], $period['invalid'])
                : $this->reads->teamReport($search, $query['page'] ?? null));
        $absenceWeek = $this->reads->teamAbsenceWeek($query['team_date'] ?? null, $query['team_page'] ?? null);

        return response()->view('chrono.supervisor', [
            // Deliberately blocked until a separately reviewed protected GET and
            // standard AttendancePage active-page whitelist admit supervisor.php.
            'page' => new AttendancePage(
                new Copy($language), (int) $actor->getAuthIdentifier(), $actor->username,
                $actor->role, $this->reads->attendanceStatus(),
                interactions: $this->interactions, activePage: 'supervisor.php',
            ),
            'supervisor' => new SupervisorPage($report, $search, $language, $absenceWeek,
                $period['range'], $period['invalid'], $periodUnavailable),
        ], $period['invalid'] ? 400 : ($periodUnavailable ? 503 : 200))->header('Cache-Control', 'no-store');
    }
}
