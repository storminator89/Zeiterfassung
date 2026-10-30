<?php

namespace App\Http\Controllers;

use App\Chrono\ChronoReads;
use App\Chrono\LocalWebAttendance;
use App\Chrono\OwnOpenReviewReads;
use App\Chrono\OwnCorrectionDecisionsReads;
use App\View\ExistingUi\OwnCorrectionDecisions;
use App\Chrono\OwnMonthReviewReads;
use App\Chrono\OwnMonthReviewFindingReads;
use App\Chrono\OwnPeriodComparisonReads;
use App\View\ExistingUi\OwnPeriodComparison;
use App\View\ExistingUi\OwnMonthReview;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
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
            return $this->json(function () use ($request): array {
                $status=$this->reads->attendanceStatus();
                if ($this->interactions->home && $request->query('home_balance') === '1') {
                    $summary=$this->reads->homeSummary();
                    $status['home_balance']=[
                        'label'=>$summary['total_overtime_formatted'],
                        'presentation'=>!empty($summary['absence_conflicts']) ? 'unresolved' : (!empty($summary['invalid_records']) ? 'incomplete' : 'current'),
                        'positive'=>$summary['positive_overtime'],
                    ];
                    // Displayed number and its finality come from the same authoritative snapshot.
                    $status['report_finality']=$summary['report_finality'];
                }
                return $status;
            });
        }

        // Registrar already ran RefreshLocalAccount -> auth:web -> auth.session.
        $actor = $this->auth->guard('web')->user();
        $language = $request->query('lang', $request->session()->get('lang', 'de'));
        $language = is_string($language) && in_array($language, ['de', 'en', 'zh'], true) ? $language : 'de';
        $request->session()->put('lang', $language);
        $copy = new Copy($language);
        $summary = $this->interactions->home ? $this->reads->homeSummary() : null;

        $ownOpenReview = null;
        $ownOpenReviewUnavailable = false;
        if ($this->reads instanceof OwnOpenReviewReads) {
            try {
                $ownOpenReview = $this->reads->ownOpenReview();
            } catch (AuthenticationException|AuthorizationException $error) {
                throw $error; // A stale/forbidden identity must retain its protected response.
            } catch (Throwable) {
                $ownOpenReviewUnavailable = true; // Never turn a failed read into an empty/clean list.
            }
        }

        $ownMonthReview = null;
        if ($this->interactions->home && $this->reads instanceof OwnMonthReviewReads) {
            require_once dirname(__DIR__, 4).'/lib/own_month_review.php';
            $month = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin')))->format('Y-m');
            $findingsAvailable=$this->reads instanceof OwnMonthReviewFindingReads;
            $ownMonthReview = new OwnMonthReview($month, $language, findingsAvailable: $findingsAvailable);
            if (array_key_exists('month_review', $request->query->all())) {
                $findingsPage=null;
                try {
                    $month = \chrono_own_month_review_month($request->query('month_review'));
                    if (array_key_exists('month_review_page',$request->query->all())) {
                        $findingsPage=\chrono_own_month_review_page($request->query('month_review_page'));
                    }
                } catch (\InvalidArgumentException) { $month = ''; }
                if ($month === '') { $ownMonthReview = new OwnMonthReview('', $language, requested: true, invalid: true); }
                else {
                    try {
                        if ($findingsPage!==null && !$findingsAvailable) { throw new \RuntimeException('Own finding navigation unavailable.'); }
                        $snapshot=$findingsPage===null?$this->reads->ownMonthReview($month)
                            :$this->reads->ownMonthReviewFindings($month,$findingsPage);
                        $ownMonthReview = new OwnMonthReview($month, $language, $snapshot, requested: true, findingsAvailable: $findingsAvailable);
                    }
                    catch (AuthenticationException|AuthorizationException $error) { throw $error; }
                    catch (Throwable) { $ownMonthReview = new OwnMonthReview($month, $language, requested: true, unavailable: true); }
                }
            }
        }

        $ownCorrectionDecisions = null;
        if ($request->isMethod('GET') && $this->interactions->home && $this->reads instanceof OwnCorrectionDecisionsReads) {
            try { $ownCorrectionDecisions = new OwnCorrectionDecisions($language, $this->reads->ownCorrectionDecisions()); }
            catch (AuthenticationException|AuthorizationException $error) { throw $error; }
            catch (Throwable) { $ownCorrectionDecisions = new OwnCorrectionDecisions($language, unavailable: true); }
        }

        $ownPeriodComparison = null;
        if ($this->interactions->home) {
            require_once dirname(__DIR__, 4).'/lib/own_period_comparison.php';
            $loaded = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin'));
            $from = $loaded->modify('-7 days')->format('Y-m-d');
            $through = $loaded->modify('-1 day')->format('Y-m-d');
            $ownPeriodComparison = new OwnPeriodComparison($from, $through, $language);
            // Preserve raw PHP query strings/arrays before Laravel normalization.
            parse_str((string) $request->server->get('QUERY_STRING', ''), $comparisonQuery);
            if (array_key_exists('compare_from', $comparisonQuery) || array_key_exists('compare_through', $comparisonQuery)) {
                try { [$from, $through] = \own_period_comparison_range($comparisonQuery['compare_from'] ?? null, $comparisonQuery['compare_through'] ?? null, $loaded); }
                catch (\InvalidArgumentException) { $from = ''; $through = ''; }
                if ($from === '') { $ownPeriodComparison = new OwnPeriodComparison('', '', $language, requested: true, invalid: true); }
                elseif (! $this->reads instanceof OwnPeriodComparisonReads) {
                    $ownPeriodComparison = new OwnPeriodComparison($from, $through, $language, requested: true, unavailable: true);
                } else {
                    try { $ownPeriodComparison = new OwnPeriodComparison($from, $through, $language, $this->reads->ownPeriodComparison($from, $through), requested: true); }
                    catch (AuthenticationException|AuthorizationException $error) { throw $error; }
                    catch (Throwable) { $ownPeriodComparison = new OwnPeriodComparison($from, $through, $language, requested: true, unavailable: true); }
                }
            }
        }

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
            reportInvalidRecordIds: $summary['invalid_record_ids'] ?? [],
            reportUnresolved: $summary['report_finality']['all'] ?? false,
        ), 'ownOpenReview' => $ownOpenReview, 'ownOpenReviewUnavailable' => $ownOpenReviewUnavailable, 'ownMonthReview' => $ownMonthReview, 'ownPeriodComparison' => $ownPeriodComparison, 'ownCorrectionDecisions' => $ownCorrectionDecisions]);
    }

    public function transition(Request $request): JsonResponse
    {
        return $this->json(function () use ($request): array {
            require_once dirname(__DIR__, 4).'/lib/attendance.php';

            // Same raw form parser and fixed Web-only requirement as attendance.php.
            return $this->attendance->transition(
                $request->post('event_id', ''), $request->post('action', ''),
                $request->post('confirm_end_pause') === '1',
                \attendance_expected_record($request->request->all()),
                \attendance_start_metadata($request->request->all()),
            );
        });
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
