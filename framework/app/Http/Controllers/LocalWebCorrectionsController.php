<?php

namespace App\Http\Controllers;

use App\Chrono\ChronoReads;
use App\Chrono\LocalWebCorrections;
use App\View\ExistingUi\AttendancePage;
use App\View\ExistingUi\Copy;
use App\View\ExistingUi\CorrectionsPage;
use App\View\ExistingUi\Interactions;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/** Native correction forms only. All commands/permissions/SQL/audit stay in #78. */
final class LocalWebCorrectionsController extends Controller
{
    public function __construct(
        private ChronoReads $reads,
        private LocalWebCorrections $corrections,
        private Factory $auth,
        private Interactions $interactions,
    ) {}

    public function index(Request $request): View
    {
        return $this->page($request);
    }

    // Only the POST route calls this method; GET/method override never dispatches a command.
    public function store(Request $request): View|RedirectResponse
    {
        try {
            // Raw form bag only. No actor, role, source, audit, date or boolean normalization.
            $command = array_intersect_key($request->request->all(), array_flip([
                'action', 'record_id', 'cancel', 'start', 'end', 'pause', 'reason',
                'id', 'decision', 'review_reason',
            ]));
            $this->corrections->apply($command);

            return new RedirectResponse('adjustments.php?saved=1');
        } catch (AuthenticationException $error) {
            throw $error; // Preserve the registrar's protected-route auth response.
        } catch (\AttendanceError $error) {
            return $this->page($request, $error->getMessage());
        } catch (Throwable) {
            return $this->page($request, 'Antrag konnte nicht verarbeitet werden.');
        }
    }

    private function page(Request $request, ?string $error = null): View
    {
        // Definitions only: reuse the native pure display formatter, never its bootstrap.
        require_once dirname(__DIR__, 4).'/lib/attendance.php';
        require_once dirname(__DIR__, 4).'/lib/correction_presentation.php';
        $actor = $this->auth->guard('web')->user();
        $language = $request->query('lang', $request->session()->get('lang', 'de'));
        $language = is_string($language) && in_array($language, ['de', 'en', 'zh'], true) ? $language : 'de';
        $request->session()->put('lang', $language);

        // Existing own projection order, native 200-option display cap; no new query.
        $records = array_map(static fn (array $row): array => [
            'id' => (int) $row['id'], 'start' => $row['startzeit'], 'end' => $row['endzeit'],
        ], array_slice($this->reads->reportRows(), 0, 200));
        $requests = array_map(static function (array $row): array {
            $preview = \chrono_correction_preview($row);
            return [
                'id' => (int) $row['id'], 'user_id' => (int) $row['user_id'],
                'username' => $row['username'], 'status' => $row['status'],
                'cancel' => (bool) $row['cancel'],
                // Native pure Berlin display formatting, not input parsing/accounting.
                'start' => $preview['proposed_usable'] ? \attendance_local((int) $row['proposed_start']) : '',
                'end' => $preview['proposed_usable'] ? \attendance_local((int) $row['proposed_end']) : '',
                'pause' => $preview['proposed_usable'] ? (int) $row['proposed_pause'] : null, 'reason' => $row['reason'],
                'review_reason' => $row['review_reason'],
                // Selected time fields only; no original snapshot is passed to Blade.
                'preview' => $preview,
            ];
        }, $this->reads->corrections());

        return view('chrono.corrections', [
            // Reuse the existing shared chrome contract; no legacy header/bootstrap include.
            'page' => new AttendancePage(
                new Copy($language), (int) $actor->getAuthIdentifier(), $actor->username,
                $actor->role, $this->reads->attendanceStatus(),
                interactions: $this->interactions, activePage: 'adjustments.php',
            ),
            'corrections' => new CorrectionsPage(
                $records, $requests, (int) $request->query('record_id', 0), $error,
                $request->query->has('saved'), $this->interactions->corrections,
            ),
        ]);
    }
}
