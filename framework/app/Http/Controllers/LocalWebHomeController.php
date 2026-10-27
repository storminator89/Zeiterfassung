<?php

namespace App\Http\Controllers;

use App\Chrono\ChronoReads;
use App\Chrono\LocalWebAbsences;
use App\View\ExistingUi\Copy;
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
    public function __construct(private ChronoReads $reads, private LocalWebAbsences $absences, private Router $router) {}

    public function records(Request $request): View
    {
        // Pure shared native presentation definitions, not functions.php/config.php.
        require_once dirname(__DIR__, 4).'/lib/home_projection.php';
        $language = $request->session()->get('lang', 'de');
        $language = is_string($language) && in_array($language, ['de', 'en'], true) ? $language : 'de';
        $page = max(1, intval($request->query('page', 1)));
        $records = $this->reads->reportRows(chronological: true);

        // Native ten-RAW-row pagination precedes grouping, but totals and
        // conflict evidence use the complete own rows from that same snapshot.
        return view('chrono.time-records', ['records' => new TimeRecordsPage(
            new Copy($language), \report_time_record_groups(array_slice($records, ($page - 1) * 10, 10), $records),
            $page, (int) ceil(count($records) / 10),
            csvExport: $this->router->has('chrono.export-csv'),
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
