<?php

namespace App\Http\Controllers;

use App\Chrono\ChronoReads;
use App\Chrono\LocalWebAbsences;
use App\View\ExistingUi\Copy;
use App\View\ExistingUi\Interactions;
use App\View\ExistingUi\TimeRecordsPage;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Native own-record fragment and absence form transport, never a second rule engine. */
final class LocalWebHomeController extends Controller
{
    public function __construct(
        private ChronoReads $reads,
        private LocalWebAbsences $absences,
        private Router $router,
        private Interactions $interactions = new Interactions,
    ) {}

    public function records(Request $request): View|Response
    {
        // Pure shared native presentation definitions, not functions.php/config.php.
        require_once dirname(__DIR__, 4).'/lib/home_projection.php';
        require_once dirname(__DIR__, 4).'/lib/time_record_review.php';
        $query = $request->query->all();
        $reviewOnly = array_key_exists('review', $query);
        if ($reviewOnly && $query['review'] !== '1') {
            return new Response('Ungültige Listenansicht. Bitte Alle oder Prüfhinweise wählen.', 400,
                ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
        }
        $language = $request->session()->get('lang', 'de');
        $language = is_string($language) && in_array($language, ['de', 'en', 'zh'], true) ? $language : 'de';
        $page = max(1, intval($request->query('page', 1)));
        $records = $this->reads->reportRows(chronological: true);
        $listConflicts = \report_absence_conflicts($records);
        $workOverlapLookup = \time_record_work_overlap_lookup($records);
        $absenceOverlapLookup = \time_record_absence_overlap_lookup($records);
        // Classify the complete already-own-scoped snapshot before slicing it.
        $visible = $reviewOnly ? \time_record_review_rows($records, $listConflicts, $workOverlapLookup, $absenceOverlapLookup) : $records;
        $totalRecords = count($visible);
        // Both native modes clamp to this same own snapshot's matching count.
        $page = min($page, max(1, (int) ceil($totalRecords / 10)));
        $pagedRows = array_slice($visible, ($page - 1) * 10, 10);
        $localTimeFindings = [];
        $workOverlapFindings = [];
        $absenceOverlapFindings = [];
        foreach ($pagedRows as $row) {
            $localTimeFindings[(string) $row['id']] = \time_record_local_time_findings($row);
            if (isset($workOverlapLookup[\report_conflict_key($row)])) { $workOverlapFindings[(string) $row['id']] = true; }
            if (isset($absenceOverlapLookup[\report_conflict_key($row)])) { $absenceOverlapFindings[(string) $row['id']] = true; }
        }

        // Native ten-RAW-row pagination precedes grouping, but totals and
        // conflict evidence use the complete own rows from that same snapshot.
        return view('chrono.time-records', ['records' => new TimeRecordsPage(
            new Copy($language), \report_time_record_groups($pagedRows, $records, $listConflicts),
            $page, (int) ceil($totalRecords / 10),
            csvExport: $this->router->has('chrono.export-csv'),
            reviewOnly: $reviewOnly, totalRecords: $totalRecords, localTimeFindings: $localTimeFindings,
            historyLinks: $this->interactions->history, workOverlapFindings: $workOverlapFindings,
            absenceOverlapFindings: $absenceOverlapFindings,
        )]);
    }

    // Separate POST route only. A GET/method override cannot dispatch this mutation.
    public function absence(Request $request): JsonResponse|Response
    {
        $form = $request->request->all();
        if (isset($form['id']) || isset($form['update']) || isset($form['delete'])) {
            return $this->text('Änderungen und Stornierungen bitte über einen begründeten Korrekturantrag vornehmen.', 409);
        }
        if (isset($form['action'])) {
            return $this->text('This legacy attendance alias is not available in the synthetic local preview.', 410);
        }
        if (! isset($form['urlaubStart'], $form['urlaubEnde'], $form['beschreibung'])) {
            return $this->text('Unbekannte Aktion.', 400);
        }

        try {
            // Match native save.php: raw POST isset/string casts, no normalization/422.
            return new JsonResponse($this->absences->create(
                (string) $form['urlaubStart'], (string) $form['urlaubEnde'], (string) $form['beschreibung'],
            ));
        } catch (AuthenticationException $error) {
            throw $error;
        } catch (\AttendanceError $error) {
            return new JsonResponse(['success' => false, 'message' => $error->getMessage()], $error->status);
        } catch (Throwable) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Buchung nicht bestätigt. Verbindung prüfen und denselben Vorgang erneut versuchen.',
            ], 503);
        }
    }

    private function text(string $message, int $status): Response
    {
        return new Response($message, $status, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
