<?php

namespace App\Http\Controllers;

use App\Chrono\ChronoReads;
use App\View\ExistingUi\AttendancePage;
use App\View\ExistingUi\Copy;
use App\View\ExistingUi\DashboardPage;
use App\View\ExistingUi\Interactions;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Existing own dashboard only; no routes, provider changes or holiday refresh. */
final class LocalWebDashboardController extends Controller
{
    public function __construct(
        private ChronoReads $reads,
        private Factory $auth,
        private Interactions $interactions,
    ) {}

    public function index(Request $request): View
    {
        // Definitions only, never the legacy header/functions/config bootstrap.
        require_once dirname(__DIR__, 4).'/lib/calendar_range.php';
        $actor = $this->auth->guard('web')->user();
        $language = $request->query('lang', $request->session()->get('lang', 'de'));
        $language = is_string($language) && in_array($language, ['de', 'en', 'zh'], true) ? $language : 'de';
        $request->session()->put('lang', $language);
        $copy = new Copy($language);
        // Match native $_GET before Laravel's trimming/null normalization.
        // The existing calendar helper alone validates the date/array shape.
        parse_str((string) $request->server->get('QUERY_STRING', ''), $query);
        $snapshot = $this->reads->calendarWeek($query['calendar_date'] ?? null);
        $labels = [
            'Arbeit' => $copy->text('WORK'), 'Urlaub' => $copy->text('EVENT_VACATION'),
            'Krankheit' => $copy->text('EVENT_SICK'), 'Feiertag' => $copy->text('EVENT_HOLIDAY'),
        ];

        return view('chrono.dashboard', [
            // Separate prerequisites: dashboard.php must join the shared chrome
            // whitelist and a reviewed protected GET before this can be dispatched.
            'page' => new AttendancePage(
                $copy, (int) $actor->getAuthIdentifier(), $actor->username, $actor->role,
                $snapshot['status'], interactions: $this->interactions, activePage: 'dashboard.php',
                reportInvalidCount: count($snapshot['invalid_records']),
                reportUnresolved: $snapshot['report_finality']['all'],
            ),
            'dashboard' => new DashboardPage(
                $snapshot['statistics'], $snapshot['range'],
                \calendar_range_events($snapshot['records'], $snapshot['holidays'], $snapshot['range'], $labels),
                $snapshot['report_finality'], $snapshot['report_incomplete'],
            ),
        ]);
    }
}
