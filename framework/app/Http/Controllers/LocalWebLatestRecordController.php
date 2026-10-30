<?php

namespace App\Http\Controllers;

use App\Chrono\ChronoReads;
use App\View\ExistingUi\Copy;
use App\View\ExistingUi\LatestTimeRecordPage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/** Native own HTML fragment, no request-selected subject, writer or second calculation. */
final class LocalWebLatestRecordController extends Controller
{
    public function __construct(private ChronoReads $reads) {}

    public function latest(Request $request): Response
    {
        try {
            $record = $this->reads->latestWorkRecord();
            $language = $request->session()->get('lang', 'de');
            $copy = new Copy(is_string($language) && in_array($language, ['de', 'en', 'zh'], true) ? $language : 'de');
            $entry = null;
            if ($record !== []) {
                require_once dirname(__DIR__, 4).'/lib/reporting.php';
                require_once dirname(__DIR__, 4).'/lib/work_location.php';
                require_once dirname(__DIR__, 4).'/lib/time_record_review.php';
                $calculation = \report_record_calculation($record);
                $entry = [
                    'id' => (int) $record['id'],
                    'invalid' => $calculation['status'] === 'invalid', 'error' => $calculation['error'],
                    'timestamp_review' => $calculation['status'] !== 'invalid' && \time_record_local_time_findings($record) !== [],
                    'duration' => $calculation['status'] === 'invalid' ? 'Ungültig'
                        : ($calculation['minutes'] === null ? '-' : \report_format_hours($calculation['minutes'] / 60)),
                    'start' => $calculation['start'] === null ? 'Ungültig' : \booking_local_date($calculation['start'])->format('d.m.Y H:i'),
                    'end' => ($record['endzeit'] ?? '') === '' ? '-'
                        : ($calculation['end'] === null ? 'Ungültig' : \booking_local_date($calculation['end'])->format('d.m.Y H:i')),
                    'pause' => (string) ($record['pause'] ?? ''),
                    'location' => match (\work_location_code($record['standort'])) {
                        'office' => $copy->text('LOCATION_OFFICE'),
                        'home_office' => $copy->text('LOCATION_HOME_OFFICE'),
                        'business_trip' => $copy->text('LOCATION_BUSINESS_TRIP'),
                        default => 'Unbekannt',
                    },
                    'comment' => (string) ($record['beschreibung'] ?? ''),
                ];
            }

            return response()->view('chrono.latest-record', ['latestRecord' => new LatestTimeRecordPage($copy, $entry)],
                200, ['Content-Type' => 'text/html; charset=UTF-8', 'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
        } catch (AuthenticationException|AuthorizationException $error) {
            throw $error;
        } catch (Throwable) {
            // A failed read/render is not an invented empty successful projection.
            return new Response('Zeiteintrag derzeit nicht bestätigt.', 503,
                ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
        }
    }
}
