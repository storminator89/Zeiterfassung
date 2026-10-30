<?php

namespace App\Http\Controllers;

use App\Chrono\ChronoReads;
use App\Chrono\CsvExportPeriod;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Existing own-user CSV only; no subject selector, writer or late stream callback. */
final class LocalWebCsvExportController extends Controller
{
    public function __construct(private ChronoReads $reads) {}

    public function index(Request $request): Response
    {
        try {
            $period = CsvExportPeriod::fromQueryString((string) $request->server->get('QUERY_STRING', ''));
        } catch (InvalidArgumentException) {
            return new Response('Ungültiger CSV-Zeitraum. Bitte Von/Bis als gültige Berliner Daten (JJJJ-MM-TT) wählen; Von darf nicht nach Bis liegen.', 400, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }
        try {
            // Definitions only; never security.php/config.php/functions.php.
            require_once dirname(__DIR__, 4).'/lib/csv_export.php';
            // One complete authorized own snapshot, even for administrators.
            // Search/page/user_id cannot choose another person or narrow these gates.
            // Even period exports validate this COMPLETE own snapshot before filtering.
            $records = $this->reads->reportRows(chronological: true);
            if (\report_absence_conflicts($records) !== []) {
                return new Response('CSV-Export nicht abschließend verfügbar: Arbeitszeit und ganztägige Abwesenheit überschneiden sich. Bitte zuerst über die Arbeitszeitliste und Korrekturanträge manuell klären. Es wurden keine Zeiten gelöscht oder gekürzt.', 409, [
                    'Content-Type' => 'text/plain; charset=UTF-8',
                ]);
            }
            // Match the native quality gate on this SAME complete own snapshot,
            // before dates/serialization/attachment headers. Never omit a row or
            // turn invalid time into a successful dash/partial CSV. Open is valid.
            foreach ($records as $record) {
                if (\report_record_calculation($record)['status'] === 'invalid') {
                    return new Response('CSV-Export nicht verfügbar: Mindestens ein Zeiteintrag ist ungültig. Bitte die Originalwerte in der eigenen Arbeitszeitliste prüfen und über Korrekturanträge klären. Es wurden keine Zeiten gelöscht oder gekürzt.' . \report_csv_invalid_record_hint($record), 409, [
                        'Content-Type' => 'text/plain; charset=UTF-8',
                    ]);
                }
            }
            // Native date() runs under config.php's Berlin default. Capture at
            // the same point, before serialization, without mutating global time.
            $date = (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format('Y-m-d');
            $document = \report_csv_document($period === null ? $records : $period->select($records));
            $filename = $period === null ? 'Arbeitszeiterfassung_'.$date.'.csv'
                : 'Arbeitszeiterfassung_'.$period->from.'_'.$period->to.'.csv';

            // Materialization can fail; only COMPLETE bytes become an attachment.
            return new Response($document, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        } catch (AuthenticationException $error) {
            throw $error;
        } catch (Throwable) {
            // Explicit framework transport deviation: no raw exception text,
            // misleading successful CSV headers, or partially downloaded rows.
            return new Response('CSV-Export konnte nicht erstellt werden. Bitte erneut versuchen.', 500, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }
    }
}
