<?php

namespace App\Http\Controllers;

use App\Chrono\ChronoReads;
use App\Chrono\HistoryQuery;
use App\View\ExistingUi\AttendancePage;
use App\View\ExistingUi\Copy;
use App\View\ExistingUi\HistoryPage;
use App\View\ExistingUi\Interactions;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Native audit cards only; routing and shared chrome integration remain separate. */
final class LocalWebHistoryController extends Controller
{
    public function __construct(
        private ChronoReads $reads,
        private Factory $auth,
        private Interactions $interactions,
    ) {}

    public function index(Request $request): Response
    {
        // Definitions only: native Berlin display formatting, never its bootstrap.
        require_once dirname(__DIR__, 4).'/lib/attendance.php';
        $actor = $this->auth->guard('web')->user();
        $language = $request->query('lang', $request->session()->get('lang', 'de'));
        $language = is_string($language) && in_array($language, ['de', 'en'], true) ? $language : 'de';
        $request->session()->put('lang', $language);

        $query = HistoryQuery::fromQueryString((string) $request->server->get('QUERY_STRING', ''));
        // Invalid native filters render HTTP 400 without querying audit rows.
        $rows = $query->errors ? [] : $this->reads->historyQuery($query);
        $labels = $query->entities;
        $entries = array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'occurred_at' => \attendance_local((int) $row['occurred_at']),
            'entity' => $labels[$row['entity']] ?? $row['entity'],
            'record_id' => (int) $row['record_id'],
            'operation' => $row['operation'], 'actor' => $row['actor'], 'reason' => $row['reason'],
            // Stored JSON remains verbatim text; only null receives the native fallback.
            'before_json' => $row['before_json'] ?? 'Kein vorheriger Eintrag',
            'after_json' => $row['after_json'] ?? 'Storniert',
        ], $rows);

        return response()->view('chrono.history', [
            // Deliberate prerequisite: history.php must first join the shared chrome whitelist.
            // Only the separately reviewed protected route may supply this controller's actor.
            'page' => new AttendancePage(
                new Copy($language), (int) $actor->getAuthIdentifier(), $actor->username,
                $actor->role, $this->reads->attendanceStatus(),
                interactions: $this->interactions, activePage: 'history.php',
            ),
            'history' => new HistoryPage(
                $entries, $query->values, $query->errors, $labels,
                ! $query->errors && $query->hasBefore ? $query->url() : null,
                count($rows) === 100 ? $query->url((int) $rows[array_key_last($rows)]['id']) : null,
            ),
        ], $query->errors ? 400 : 200);
    }
}
