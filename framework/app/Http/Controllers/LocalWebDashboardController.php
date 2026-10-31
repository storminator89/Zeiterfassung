<?php

namespace App\Http\Controllers;

use App\Chrono\ChronoReads;
use App\Chrono\HolidayInventoryReads;
use App\Chrono\OwnAbsenceYearReads;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Throwable;
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

        $holidayInventory = null;
        $holidayInventoryUnavailable = false;
        $holidayInventoryOpen = array_key_exists('holiday_year', $query)
            || ($query['holiday_inventory'] ?? null) === '1';
        if ($this->reads instanceof HolidayInventoryReads) {
            try {
                $holidayInventory = $this->reads->holidayInventory($query['holiday_year'] ?? null);
            } catch (AuthenticationException|AuthorizationException $error) {
                throw $error;
            } catch (Throwable) {
                $holidayInventoryUnavailable = true; // Never interpret failure as an empty/complete year.
            }
        }

        require_once dirname(__DIR__, 4).'/lib/own_absence_year_copy.php';
        $absenceYearSupported = $this->reads instanceof OwnAbsenceYearReads;
        $absenceYearState = 'idle'; $absenceYearData = null;
        if ($absenceYearSupported && (array_key_exists('absence_year', $query) || array_key_exists('absence_page', $query))) {
            try {
                $absenceYearData = $this->reads->ownAbsenceYear($query['absence_year'] ?? null, $query['absence_page'] ?? null);
                $absenceYearState = 'loaded';
            } catch (AuthenticationException|AuthorizationException $error) {
                throw $error;
            } catch (\InvalidArgumentException) { $absenceYearState = 'invalid'; }
            catch (Throwable) { $absenceYearState = 'unavailable'; }
        }

        // Reuse the own-ledger presentation; no extra query or new duration formula.
        require_once dirname(__DIR__, 4).'/lib/home_projection.php';
        $calendarDetails = [];
        foreach ($snapshot['records'] as $record) {
            $calendarDetails[(string) $record['id']] = \report_time_record_detail($record);
        }

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
                $snapshot['report_finality'], $snapshot['report_incomplete'], $calendarDetails,
            ),
            'absenceYearSupported' => $absenceYearSupported,
            'absenceYearState' => $absenceYearState, 'absenceYearData' => $absenceYearData,
            'absenceYearCopy' => \chrono_own_absence_year_copy($language),
            'absenceYearCurrent' => (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin')))->format('Y'),
            'absenceYearContext' => ['calendar_date' => $snapshot['range']['start']->format('Y-m-d'),
                ...($holidayInventory === null ? [] : ['holiday_year' => (string) $holidayInventory['year']])],
            'holidayInventory' => $holidayInventory,
            'holidayInventoryUnavailable' => $holidayInventoryUnavailable,
            'holidayInventoryOpen' => $holidayInventoryOpen,
        ]);
    }
}
