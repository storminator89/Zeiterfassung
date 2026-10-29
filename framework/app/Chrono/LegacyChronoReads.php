<?php

namespace App\Chrono;

use App\Auth\LegacyUser;
use App\Auth\AccountLifecycleWebIdentity;
use App\Auth\ExistingAccountUserProvider;
use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\SQLiteConnection;
use LogicException;
use PDO;

/** Thin projection adapter: no legacy bootstrap, HTTP, session globals or writes. */
final class LegacyChronoReads implements ChronoReads, WorkModelCreditPreviewReads
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly Repository $config,
        private readonly Factory $auth,
        private readonly Gate $gate,
        private readonly ?AccountLifecycleWebIdentity $lifecycle = null,
    ) {}

    public function attendanceStatus(): array
    {
        return $this->snapshot(function (PDO $pdo, LegacyUser $actor): array {
            $statement = $pdo->prepare('SELECT id,CAST(id AS TEXT) AS id_string,startzeit,endzeit,pause,absence_kind,planned_minutes FROM zeiterfassung WHERE user_id=? ORDER BY id DESC LIMIT 30');
            $statement->execute([$actor->getKey()]);
            $records = $statement->fetchAll(PDO::FETCH_ASSOC);
            $statement->closeCursor();

            // Match native web status: full own context and state share this snapshot.
            // Keep apiAttendanceStatus()'s independent raw API wire unchanged.
            $context = $this->rows($pdo, 'SELECT * FROM zeiterfassung WHERE user_id=?', [$actor->getKey()]);
            $today = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin'));

            $status = attendance_status($pdo, (int) $actor->getKey());

            return ['success' => true, 'start_metadata_version' => 1, 'records' => $records,
                'report_finality' => $this->reportFinality(report_absence_conflicts($context), $today),
                'expected_record' => $status['record_id'] === null ? null : (string) $status['record_id'],
            ] + $status;
        });
    }

    public function reportRows(bool $chronological = false): array
    {
        return $this->snapshot(function (PDO $pdo, LegacyUser $actor) use ($chronological): array {
            // Preserve the default query; Home, CSV and Corrections explicitly
            // opt into native instant/id ordering before their existing paging.
            $order = 'startzeit DESC';
            if ($chronological) {
                require_once dirname(__DIR__, 3).'/lib/report_ordering.php';
                report_register_start_order($pdo);
                $order = 'chrono_report_start_epoch(startzeit) DESC,id DESC';
            }

            return $this->rows($pdo, 'SELECT * FROM zeiterfassung WHERE user_id=? ORDER BY '.$order, [$actor->getKey()]);
        });
    }

    public function latestWorkRecord(): array
    {
        return $this->snapshot(function (PDO $pdo, LegacyUser $actor): array {
            // Native get_latest_record.php selection, deliberately not report chronology.
            // No request subject, team selector, absence credit or auth fields are exposed.
            return $this->rows($pdo, "SELECT id,startzeit,endzeit,pause,standort,beschreibung FROM zeiterfassung WHERE user_id=? AND absence_kind IS NULL AND COALESCE(beschreibung,'') NOT IN ('Urlaub','Krankheit','Feiertag') ORDER BY (endzeit IS NULL) DESC,id DESC LIMIT 1", [$actor->getKey()])[0] ?? [];
        });
    }

    public function homeSummary(): array
    {
        return $this->snapshot(function (PDO $pdo, LegacyUser $actor): array {
            $userData = $this->rows($pdo, 'SELECT regelarbeitszeit,ueberstunden FROM users WHERE id=?', [$actor->getKey()])[0];
            $records = $this->rows($pdo, 'SELECT * FROM zeiterfassung WHERE user_id=? ORDER BY startzeit DESC', [$actor->getKey()]);

            // Explicit diagnostic opt-in is paired with visible Home warnings.
            // The shared helpers own all malformed-row/conflict rules; never
            // suppress an exception and fabricate a zero or a final partial total.
            $days = report_days($records, $invalidRecords, $conflicts);
            $today = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin'));
            $summary = home_summary($pdo, (int) $actor->getKey(), $days, (float) ($userData['ueberstunden'] ?? 0.0), $today);
            $summary['invalid_records'] = $invalidRecords;
            require_once dirname(__DIR__, 3).'/lib/own_invalid_record_links.php';
            $summary['invalid_record_ids'] = \own_invalid_record_links($invalidRecords, $records, (int) $actor->getKey());
            $summary['absence_conflicts'] = $conflicts;
            $summary['report_finality'] = $this->reportFinality($conflicts, $today);
            if ($invalidRecords !== [] || $conflicts !== []) {
                $summary['total_overtime_hours'] = null;
                $summary['total_overtime_formatted'] = $conflicts !== [] ? 'Klärung offen' : 'Unvollständig';
                $summary['positive_overtime'] = null;
            }

            return $summary;
        });
    }

    public function calendarWeek(mixed $calendarDate = null): array
    {
        return $this->snapshot(function (PDO $pdo, LegacyUser $actor) use ($calendarDate): array {
            require_once dirname(__DIR__, 3).'/lib/calendar_range.php';
            // One Berlin clock for both current-period cards and selected-week navigation.
            $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin'));
            $today = $now->format('Y-m-d');
            $user = (int) $actor->getKey();
            $userData = $this->rows($pdo, 'SELECT ueberstunden FROM users WHERE id=?', [$user])[0];
            $records = $this->rows($pdo, 'SELECT * FROM zeiterfassung WHERE user_id=? ORDER BY startzeit DESC', [$user]);
            $days = report_days($records, $invalidRecords, $conflicts);
            $finality = $this->reportFinality($conflicts, $now);
            [$weekStart, $weekEnd] = report_week_bounds($today);
            $periods = [
                'week' => [$weekStart, $weekEnd],
                'month' => [$now->format('Y-m-01'), $now->format('Y-m-t')],
                'year' => [$now->format('Y-01-01'), $now->format('Y-12-31')],
            ];
            $incomplete = ['all' => $invalidRecords !== [], 'week' => false, 'month' => false, 'year' => false, 'unknown' => false];
            $calendarRecords = [];
            $weeks = [];
            foreach ($records as $record) {
                $calculation = report_record_calculation($record);
                if ($calculation['status'] === 'invalid') {
                    // A known report start-day narrows incompleteness. An unreadable
                    // start cannot acquire a date: conservatively mark every period.
                    $day = $calculation['day'];
                    $incomplete['unknown'] = $incomplete['unknown'] || $day === null;
                    foreach ($periods as $period => [$from, $to]) {
                        $incomplete[$period] = $incomplete[$period] || $day === null || ($day >= $from && $day <= $to);
                    }
                    continue;
                }
                $calendarRecords[] = $record;
                if ($calculation['status'] === 'completed') {
                    $weeks[$calculation['week']] = true;
                }
            }

            $range = calendar_visible_week($calendarDate, $now);
            // Cache only: no native holiday downloader, writes, or today-relative cutoff.
            $holidays = $this->rows($pdo, 'SELECT datum,name FROM Feiertage WHERE datum>=? AND datum<? ORDER BY datum', [
                $range['start']->format('Y-m-d'), $range['end']->format('Y-m-d'),
            ]);
            $weekHours = report_period_minutes($days, $weekStart, $weekEnd) / 60;
            $monthHours = report_period_minutes($days, ...$periods['month']) / 60;
            $yearHours = report_period_minutes($days, ...$periods['year']) / 60;
            // One operation-local resolver in this already-authorized read snapshot.
            // Cover current periods (including ISO-week year edges) and every report-start-day.
            $plannedFirst = min($weekStart, $periods['year'][0]);
            $plannedLast = max($weekEnd, $periods['year'][1]);
            foreach ($days as $day => $_minutes) {
                if (strcmp((string) $day, $plannedFirst) < 0) { $plannedFirst = (string) $day; }
                if (strcmp((string) $day, $plannedLast) > 0) { $plannedLast = (string) $day; }
            }
            $planned = \planned_minutes_snapshot($pdo, $user, $plannedFirst, $plannedLast);
            $plannedWeek = planned_hours($pdo, $user, $weekStart, $weekEnd, $planned);
            $plannedMonth = planned_hours($pdo, $user, $periods['month'][0], $periods['month'][1], $planned);
            $plannedYear = planned_hours($pdo, $user, $periods['year'][0], $periods['year'][1], $planned);
            $monthDays = array_filter($days, static fn (string $day): bool => $day >= $periods['month'][0] && $day <= $periods['month'][1], ARRAY_FILTER_USE_KEY);
            $workedDays = count($monthDays);
            require_once dirname(__DIR__, 3).'/lib/dashboard_presentation.php';
            $components = \chrono_dashboard_components($records, $weekStart, $weekEnd, ...$periods['month']);
            // The native cards remain CURRENT-period statistics when browsing an old week.
            $statistics = [
                'regular_hours' => home_daily_hours($pdo, $user, $today, $planned),
                'weeks_count' => count($weeks), 'is_first_week' => count($weeks) === 1,
                'total_overtime' => report_format_hours(home_total_overtime($pdo, $user, $days, (float) ($userData['ueberstunden'] ?? 0.0), $planned)),
                'year_overtime' => report_format_hours($yearHours - $plannedYear),
                'week_hours' => report_format_hours($weekHours),
                'week_work_hours' => report_format_hours($components['week_work_minutes'] / 60),
                'week_absence_hours' => report_format_hours($components['week_absence_minutes'] / 60),
                'week_from' => $weekStart, 'week_to' => $weekEnd,
                'remaining_week' => report_format_hours(max(0, $plannedWeek - $weekHours)),
                'weekly_productivity' => number_format(report_percentage($weekHours, $plannedWeek), 1).'%',
                'month_hours' => report_format_hours($monthHours),
                'month_work_hours' => report_format_hours($components['month_work_minutes'] / 60),
                'month_absence_hours' => report_format_hours($components['month_absence_minutes'] / 60),
                'month_from' => $periods['month'][0], 'month_to' => $periods['month'][1],
                'planned_month' => report_format_hours($plannedMonth),
                'average_daily_hours' => report_format_hours($workedDays > 0 ? $monthHours / $workedDays : 0),
                'worked_days' => $workedDays,
                'monthly_productivity' => number_format(report_percentage($monthHours, $plannedMonth), 1).'%',
            ];
            foreach ([
                'all' => ['weeks_count', 'is_first_week', 'total_overtime'],
                'year' => ['year_overtime'],
                'week' => ['week_hours', 'week_work_hours', 'week_absence_hours', 'remaining_week', 'weekly_productivity'],
                'month' => ['month_hours', 'month_work_hours', 'month_absence_hours', 'average_daily_hours', 'worked_days', 'monthly_productivity'],
            ] as $period => $keys) {
                if ($finality[$period] || $incomplete[$period]) {
                    foreach ($keys as $key) {
                        $statistics[$key] = null;
                    }
                }
            }

            return [
                'current_date' => $today, 'range' => $range, 'records' => $calendarRecords,
                'holidays' => $holidays, 'statistics' => $statistics,
                'invalid_records' => $invalidRecords, 'absence_conflicts' => $conflicts,
                'report_finality' => $finality, 'report_incomplete' => $incomplete,
                'status' => ['success' => true, 'report_finality' => $finality] + attendance_status($pdo, $user),
            ];
        });
    }

    public function teamReport(string $userSearch = '', mixed $page = null): array
    {
        return $this->snapshot(function (PDO $pdo, LegacyUser $actor) use ($userSearch, $page): array {
            // The snapshot freshly reloads role/team. A user's own-row policy
            // must NOT accidentally widen this native supervisor-only report.
            if (! in_array($actor->role, ['admin', 'supervisor'], true)) {
                throw new AuthorizationException('This report requires a current supervisor or administrator.');
            }
            require_once dirname(__DIR__, 3).'/lib/supervisor_projection.php';
            require_once dirname(__DIR__, 3).'/lib/report_ordering.php';
            \report_register_start_order($pdo);
            // Exact native scope/order: no subject selector or implicit own rows.
            $records = $this->rows($pdo, "SELECT z.*, u.username, u.id as user_id, u.regelarbeitszeit, u.ueberstunden as vorherige_ueberstunden, substr(z.startzeit,1,10) AS day, NULL AS weekNumber
                            FROM zeiterfassung z
                            JOIN users u ON z.user_id = u.id
                            WHERE u.supervisor_id = ? OR ?='admin'
                            ORDER BY chrono_report_start_epoch(z.startzeit) DESC, z.id DESC", [$actor->getKey(), $actor->role]);

            $report = \supervisor_report_projection($pdo, $records, $userSearch, $page);

            return $report + ['team_status' => $this->currentTeamStatus($pdo, $actor)];
        });
    }

    /** Planning evidence only: never an availability promise, health detail or new absence writer. */
    public function teamAbsenceWeek(mixed $calendarDate = null, mixed $page = null): array
    {
        return $this->snapshot(function (PDO $pdo, LegacyUser $actor) use ($calendarDate, $page): array {
            if (! in_array($actor->role, ['admin', 'supervisor'], true)) {
                throw new AuthorizationException('This plan requires a current supervisor or administrator.');
            }
            require_once dirname(__DIR__, 3).'/lib/team_absence_week.php';

            return \chrono_team_absence_week_snapshot($pdo, (int) $actor->getKey(), $actor->role, $calendarDate, $page,
                new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin')));
        });
    }

    /** Current authorized roster only, independent of historical report rows/pages. */
    private function currentTeamStatus(PDO $pdo, LegacyUser $actor): array
    {
        require_once dirname(__DIR__, 3).'/lib/team_status.php';

        return \chrono_team_status_snapshot($pdo, (int) $actor->getKey(), $actor->role,
            new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin')));
    }

    public function apiIdentity(): array
    {
        return $this->snapshot(fn (PDO $pdo, LegacyUser $actor): array => [
            'success' => true,
            'data' => $this->rows($pdo, 'SELECT id,username,email,role FROM users WHERE id=?', [$actor->getKey()]),
        ], false);
    }

    public function apiAttendanceStatus(): array
    {
        return $this->snapshot(
            fn (PDO $pdo, LegacyUser $actor): array => attendance_record_id_string(['success' => true, 'start_metadata_version' => 1] + attendance_status($pdo, (int) $actor->getKey())),
            false,
        );
    }

    public function apiTimeEntries(ApiTimeEntriesQuery $query): array
    {
        return $this->snapshot(function (PDO $pdo, LegacyUser $actor) use ($query): array {
            $where = ['user_id = :user_id'];
            $parameters = [':user_id' => $actor->getKey()];
            foreach (['start_date' => $query->startDate, 'end_date' => $query->endDate] as $field => $date) {
                if ($date !== null) {
                    $where[] = 'chrono_api_booking_day(startzeit) '.($field === 'start_date' ? '>=' : '<=').' :'.$field;
                    $parameters[':'.$field] = $date;
                }
            }
            if ($query->startDate !== null || $query->endDate !== null) {
                require_once dirname(__DIR__, 3).'/lib/api_booking_day.php';
                api_register_booking_day($pdo);
            }
            require_once dirname(__DIR__, 3).'/lib/report_ordering.php';
            report_register_start_order($pdo);
            $predicate = implode(' AND ', $where);
            $statement = $pdo->prepare('SELECT COUNT(*) FROM zeiterfassung WHERE '.$predicate);
            foreach ($parameters as $name => $value) {
                $statement->bindValue($name, $value);
            }
            $statement->execute();
            $total = $statement->fetchColumn();
            $statement->closeCursor();

            $sql = 'SELECT * FROM zeiterfassung WHERE '.$predicate.' ORDER BY chrono_report_start_epoch(startzeit) DESC, id DESC';
            if ($query->limit !== null) {
                $sql .= ' LIMIT :limit';
                $parameters[':limit'] = $query->limit;
            }
            if ($query->offset !== null) {
                if ($query->limit === null) {
                    // SQLite requires LIMIT before OFFSET; -1 leaves the page unbounded.
                    $sql .= ' LIMIT -1';
                }
                $sql .= ' OFFSET :offset';
                $parameters[':offset'] = $query->offset;
            }
            $statement = $pdo->prepare($sql);
            foreach ($parameters as $name => $value) {
                // Keep the existing PDO binding and response shape. The HTTP
                // adapter validates pagination before this read snapshot.
                $statement->bindValue($name, $value);
            }
            $statement->execute();
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            $statement->closeCursor();

            return ['success' => true, 'data' => $rows, 'meta' => [
                'total' => $total, 'filtered' => count($rows),
                'limit' => $query->limitMetadata, 'offset' => $query->offsetMetadata,
            ]];
        }, false);
    }

    public function period(string $start, string $end, ?int $subject = null): array
    {
        return $this->snapshot(function (PDO $pdo, LegacyUser $actor) use ($start, $end, $subject): array {
            // The legacy date parser owns calendar validation; no Carbon/UTC or
            // SQL DATE() replacement of Berlin/start-day/holiday/model semantics.
            practice_date($start);
            practice_date($end);
            $target = $subject === null || $subject === (int) $actor->getKey()
                ? $actor : $this->account($pdo, $subject);
            $this->gate->forUser($actor)->authorize('view', $target);
            $records = $this->rows($pdo, 'SELECT * FROM zeiterfassung WHERE user_id=? ORDER BY startzeit DESC', [$target->getKey()]);
            // Full effective context catches a counterpart outside the period;
            // unrelated historical conflicts must not invalidate this period.
            // The shared helper retains the existing malformed-data fail-close.
            $days = report_days_for_period($records, $start, $end);
            $minutes = report_period_minutes($days, $start, $end);
            $planned = planned_hours($pdo, (int) $target->getKey(), $start, $end);

            return [
                'days' => array_filter($days, fn (string $day): bool => $day >= $start && $day <= $end, ARRAY_FILTER_USE_KEY),
                'actual_minutes' => $minutes,
                'planned_hours' => $planned,
                'actual_formatted' => report_format_hours($minutes / 60),
                'percentage' => report_percentage($minutes / 60, $planned),
            ];
        });
    }

    public function corrections(): array
    {
        return $this->snapshot(fn (PDO $pdo, LegacyUser $actor): array =>
            $this->rows($pdo, "SELECT a.*,u.username FROM time_adjustments a JOIN users u ON u.id=a.user_id WHERE a.user_id=? OR ?='admin' OR (?='supervisor' AND u.supervisor_id=?) ORDER BY a.id DESC LIMIT 100", [
                $actor->getKey(), $actor->role, $actor->role, $actor->getKey(),
            ])
        );
    }

    public function correctionsPage(string $status = 'pending', int $page = 1, ?int $previewRequestId = null): array
    {
        return $this->snapshot(function (PDO $pdo, LegacyUser $actor) use ($status, $page, $previewRequestId): array {
            if (! in_array($status, ['pending', 'all', 'approved', 'rejected', 'withdrawn'], true) || $page < 1) {
                throw new \InvalidArgumentException('Invalid correction request page.');
            }
            // Exact native owner/admin/current-direct-team scope. Counting and
            // paging share this fresh snapshot; no request supplies an actor.
            $scope = "(a.user_id=? OR ?='admin' OR (?='supervisor' AND u.supervisor_id=?))";
            $parameters = [$actor->getKey(), $actor->role, $actor->role, $actor->getKey()];
            $counts = array_fill_keys(['pending', 'all', 'approved', 'rejected', 'withdrawn'], 0);
            foreach ($this->rows($pdo, 'SELECT a.status,COUNT(*) AS total FROM time_adjustments a JOIN users u ON u.id=a.user_id WHERE '.$scope.' GROUP BY a.status', $parameters) as $count) {
                $counts[$count['status']] = (int) $count['total'];
                $counts['all'] += (int) $count['total'];
            }
            $total = $counts[$status];
            $pageSize = 50;
            $pages = max(1, (int) ceil($total / $pageSize));
            $page = min($page, $pages);
            $offset = ($page - 1) * $pageSize;
            if ($status !== 'all') {
                $scope .= ' AND a.status=?';
                $parameters[] = $status;
            }
            $records = $this->rows($pdo, 'SELECT a.*,u.username FROM time_adjustments a JOIN users u ON u.id=a.user_id WHERE '.$scope.' ORDER BY a.id DESC LIMIT '.$pageSize.' OFFSET '.$offset, $parameters);

            // At most ONE explicit own pending request from this visible page.
            // Never load another person's balance or recompute fifty histories.
            $balancePreview = [];
            if ($previewRequestId !== null && $previewRequestId > 0) {
                foreach ($records as $request) {
                    if ((int) $request['id'] !== $previewRequestId
                        || (int) $request['user_id'] !== (int) $actor->getKey()
                        || $request['status'] !== 'pending') {
                        continue;
                    }
                    require_once dirname(__DIR__, 3).'/lib/correction_balance_preview.php';
                    $balancePreview = ['available' => false, 'before' => null, 'after' => null, 'difference' => null];
                    $carryIn = $this->rows($pdo, 'SELECT ueberstunden FROM users WHERE id=?', [$actor->getKey()])[0]['ueberstunden'] ?? null;
                    if ((is_int($carryIn) || is_float($carryIn) || is_string($carryIn)) && is_numeric($carryIn)) {
                        $ownRecords = $this->rows($pdo, 'SELECT * FROM zeiterfassung WHERE user_id=? ORDER BY startzeit DESC', [$actor->getKey()]);
                        $balancePreview = \chrono_correction_balance_preview($pdo, (int) $actor->getKey(), $ownRecords, (float) $carryIn, $request, time());
                    }
                    break;
                }
            }

            return compact('records', 'counts', 'status', 'total', 'page', 'pages', 'balancePreview');
        });
    }

    public function history(int $before = PHP_INT_MAX): array
    {
        return $this->snapshot(fn (PDO $pdo, LegacyUser $actor): array =>
            $this->rows($pdo, "SELECT a.* FROM audit_log a LEFT JOIN users u ON u.id=a.user_id WHERE a.id<? AND (a.user_id=? OR ?='admin' OR (?='supervisor' AND u.supervisor_id=?)) ORDER BY a.id DESC LIMIT 100", [
                max(1, $before), $actor->getKey(), $actor->role, $actor->role, $actor->getKey(),
            ])
        );
    }

    public function historyQuery(HistoryQuery $query): array
    {
        if ($query->errors) {
            return [];
        }

        return $this->snapshot(function (PDO $pdo, LegacyUser $actor) use ($query): array {
            $statement = $query->statementFor((int) $actor->getKey(), $actor->role);

            return $this->rows($pdo, $statement['sql'], $statement['params']);
        });
    }

    public function historyCorrelations(HistoryQuery $query): array
    {
        if ($query->errors) {
            throw new \InvalidArgumentException('Ungültige Historienfilter.');
        }

        return $this->snapshot(function (PDO $pdo, LegacyUser $actor) use ($query): array {
            require_once dirname(__DIR__, 3).'/lib/history_correlation.php';
            $statement = $query->statementFor((int) $actor->getKey(), $actor->role);
            $rows = $this->rows($pdo, $statement['sql'], $statement['params']);

            return ['rows' => $rows, 'correlation' => \chrono_history_correlations(
                $pdo, $rows, (int) $actor->getKey(), $actor->role,
            )];
        });
    }

    /** Existing bounded Native hypothetical comparison; never a writer or recalculation. */
    public function workModelCreditPreview(array $command): array
    {
        $authenticated = $this->auth->guard()->user();
        if (! $authenticated instanceof LegacyUser || (int) $authenticated->getKey() < 1) {
            throw new AuthenticationException;
        }
        if ($authenticated->role !== 'admin' || $authenticated->auth_source !== 'local') {
            throw new AuthorizationException('Nur die lokale Verwaltung darf Gutschriften vorab prüfen.');
        }
        $password = $authenticated->getAuthPassword();
        if (! is_string($password) || $password === '') {
            throw new AuthenticationException;
        }
        // Captured guard version, not a request value or a freshly invented
        // authority. The unchanged snapshot still verifies source/generation.
        $version = hash('sha256', $password);
        $actorId = (int) $authenticated->getKey();
        unset($password);

        return $this->snapshot(function (PDO $pdo, LegacyUser $actor) use ($command, $version, $actorId): array {
            if ((int) $actor->getKey() !== $actorId || $actor->role !== 'admin' || $actor->auth_source !== 'local') {
                throw new AuthorizationException('Nur die lokale Verwaltung darf Gutschriften vorab prüfen.');
            }
            require_once dirname(__DIR__, 3).'/lib/work_model_credit_preview.php';

            return work_model_credit_preview_locked($pdo, $actorId, $version, $command);
        });
    }

    public function workModels(): array
    {
        return $this->snapshot(fn (PDO $pdo, LegacyUser $actor): array =>
            $this->rows($pdo, 'SELECT m.*,u.username FROM work_models m JOIN users u ON u.id=m.user_id WHERE m.user_id=? OR CAST(? AS INTEGER)=1 ORDER BY m.valid_from DESC,m.id DESC LIMIT 200', [
                $actor->getKey(), (int) ($actor->role === 'admin'),
            ])
        );
    }

    public function workModelsPage(int $page = 1, ?int $selectedModelId = null): array
    {
        return $this->snapshot(function (PDO $pdo, LegacyUser $actor) use ($page, $selectedModelId): array {
            // Same queries and scope as native PR136; selection is read-only.
            // No caller-supplied owner/role and no list cap determines today's model.
            $today = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin')))->format('Y-m-d');
            $scope = '(m.user_id=? OR CAST(? AS INTEGER)=1)';
            $parameters = [$actor->getKey(), (int) ($actor->role === 'admin')];
            $total = (int) $this->rows($pdo, 'SELECT COUNT(*) AS total FROM work_models m JOIN users u ON u.id=m.user_id WHERE '.$scope, $parameters)[0]['total'];
            $pageSize = 50;
            $pages = max(1, (int) ceil($total / $pageSize));
            $page = min(max(1, $page), $pages);
            $offset = ($page - 1) * $pageSize;
            $models = $this->rows($pdo, 'SELECT m.*,u.username,(SELECT e.id FROM work_models e WHERE e.user_id=m.user_id AND e.valid_from<=? ORDER BY e.valid_from DESC,e.id DESC LIMIT 1) AS effective_id FROM work_models m JOIN users u ON u.id=m.user_id WHERE '.$scope.' ORDER BY m.valid_from DESC,m.id DESC LIMIT '.$pageSize.' OFFSET '.$offset, array_merge([$today], $parameters));
            $selected = null;
            $selectionError = null;
            if ($selectedModelId !== null) {
                // Zero is the transport's explicit invalid-selection sentinel.
                if ($selectedModelId > 0) {
                    $selected = $this->rows($pdo, 'SELECT m.*,u.username FROM work_models m JOIN users u ON u.id=m.user_id WHERE '.$scope.' AND m.id=?', array_merge($parameters, [$selectedModelId]))[0] ?? null;
                }
                if ($selected === null) {
                    $selectionError = 'Modellversion nicht verfügbar. Bitte aus den sichtbaren Modellen neu auswählen.';
                }
            }
            $effective = null;
            if ($selectionError === null) {
                $owner = $selected === null ? $actor->getKey() : (int) $selected['user_id'];
                $effective = $this->rows($pdo, 'SELECT m.*,u.username FROM work_models m JOIN users u ON u.id=m.user_id WHERE '.$scope.' AND m.user_id=? AND m.valid_from<=? ORDER BY m.valid_from DESC,m.id DESC LIMIT 1', array_merge($parameters, [$owner, $today]))[0] ?? null;
            }

            return [
                'models' => $models, 'total' => $total, 'page' => $page, 'pages' => $pages, 'page_size' => $pageSize,
                'today' => $today, 'selected_model_id' => $selected === null ? 0 : (int) $selected['id'],
                'selected_model' => $selected, 'selection_error' => $selectionError, 'effective_model' => $effective,
            ];
        });
    }

    public function adminPauseSettings(): array
    {
        return $this->snapshot(function (PDO $pdo, LegacyUser $actor): array {
            if ($actor->role !== 'admin' || $actor->auth_source !== 'local') {
                throw new AuthorizationException('Nur die Verwaltung darf Pauseneinstellungen ändern.');
            }

            // Native current configuration only; no default insertion or user/LDAP/token data.
            return $this->rows($pdo, 'SELECT * FROM pause_settings ORDER BY hours_threshold ASC', []);
        });
    }

    public function adminDepartments(): array
    {
        return $this->snapshot(function (PDO $pdo, LegacyUser $actor): array {
            if ($actor->role !== 'admin' || $actor->auth_source !== 'local') {
                throw new AuthorizationException('Nur die Verwaltung darf Abteilungen ändern.');
            }

            // Native query/order: no user fields, LDAP data, sorting or list cap.
            return $this->rows($pdo, 'SELECT id, name FROM departments', []);
        });
    }

    public function adminTerminalDevices(): array
    {
        return $this->snapshot(function (PDO $pdo, LegacyUser $actor): array {
            if ($actor->role !== 'admin' || $actor->auth_source !== 'local') {
                throw new AuthorizationException('Nur die Verwaltung darf die Terminal-Geräteliste sehen.');
            }
            require_once dirname(__DIR__, 3).'/lib/terminal_status.php';
            // One server timestamp for the complete snapshot, never a request clock.
            $now = time();
            // Do not select t.*, generation, any pairing hash/code, credentials,
            // users, grants or key sources. Only nonsecret presence is projected.
            $rows = $this->rows($pdo, "SELECT t.id,t.name,t.enabled,t.last_seen,s.name AS site,
                CASE WHEN t.pairing_hash IS NULL OR t.pairing_hash IN ('','0') THEN 0 ELSE 1 END AS has_pending_pairing,
                t.pairing_expires AS pairing_expiry
                FROM terminals t JOIN terminal_sites s ON s.id=t.site_id ORDER BY t.id DESC", []);
            $devices = array_map(static function (array $row) use ($now): array {
                $contact = terminal_contact_status($row['last_seen'], $now);

                return [
                    'id' => (int) $row['id'], 'name' => $row['name'], 'site' => $row['site'],
                    // Native display metadata only, never an authorization grant.
                    'permission_label' => $row['enabled'] ? 'Freigegeben'
                        : ($row['has_pending_pairing'] && $row['pairing_expiry'] > $now ? 'Kopplung offen' : 'Gesperrt / abgelaufen'),
                    'contact_state' => $contact['state'], 'contact_label' => $contact['label'],
                    'last_seen_label' => $contact['last_seen'] === null ? '—' : attendance_local($contact['last_seen']),
                ];
            }, $rows);

            // Raw pairing presence/expiry and enabled/last_seen never leave this read boundary.
            return ['devices' => $devices, 'contact_max_age' => TERMINAL_CONTACT_MAX_AGE];
        });
    }

    public function adminTerminalPairingSites(): array
    {
        return $this->snapshot(function (PDO $pdo, LegacyUser $actor): array {
            if ($actor->role !== 'admin' || $actor->auth_source !== 'local') {
                throw new AuthorizationException('Nur die lokale Verwaltung darf Terminal-Standorte auswählen.');
            }

            return $this->rows($pdo, 'SELECT id,name FROM terminal_sites ORDER BY name', []);
        });
    }

    public function adminTerminalModuleSettings(): array
    {
        return $this->snapshot(function (PDO $pdo, LegacyUser $actor): array {
            if ($actor->role !== 'admin' || $actor->auth_source !== 'local') {
                throw new AuthorizationException('Nur die lokale Verwaltung darf den Terminal-Modulstatus sehen.');
            }
            // Match attendance_enabled() and the native checkbox. The boolean
            // reveals no environment value and never authorizes a later write.
            $serverEnabled = getenv('APP_TERMINAL_ENABLED') === '1';
            $row = $this->rows($pdo, 'SELECT enabled FROM terminal_settings WHERE id=1', [])[0] ?? [];

            return ['server_enabled' => $serverEnabled,
                'enabled' => $serverEnabled && (int) ($row['enabled'] ?? 0) === 1];
        });
    }

    public function adminTerminalCredentials(): array
    {
        return $this->snapshot(function (PDO $pdo, LegacyUser $actor): array {
            if ($actor->role !== 'admin' || $actor->auth_source !== 'local') {
                throw new AuthorizationException('Nur die Verwaltung darf Terminal-Zugänge sehen.');
            }
            // Exact native INNER JOINs and no added sort/cap. Never select a
            // PIN/card hash, user password/token, credential ID, grant or key.
            $rows = $this->rows($pdo, "SELECT c.employee_code,c.enabled,
                CASE WHEN c.card_hash IS NULL OR c.card_hash='' OR c.card_hash='0' THEN 0 ELSE 1 END AS has_card,
                u.username,s.name AS site
                FROM employee_credentials c JOIN users u ON u.id=c.user_id JOIN terminal_sites s ON s.id=c.site_id", []);

            return array_map(static fn (array $row): array => [
                'username' => (string) $row['username'], 'employee_code' => (string) $row['employee_code'],
                'site' => (string) $row['site'],
                'access_label' => $row['enabled'] ? ($row['has_card'] ? 'PIN + Karte' : 'PIN') : 'Gesperrt',
            ], $rows);
        });
    }

    public function adminTerminalCredentialRevocationTargets(): array
    {
        return $this->snapshot(function (PDO $pdo, LegacyUser $actor): array {
            if ($actor->role !== 'admin' || $actor->auth_source !== 'local') {
                throw new AuthorizationException('Nur die lokale Verwaltung darf Terminal-Zugänge sperren.');
            }
            // Separate form projection: preserve the original four-field reader.
            // The target and its labels come from ONE fresh snapshot, never from
            // index-matching two independently changing lists. No raw secrets.
            $rows = $this->rows($pdo, "SELECT c.user_id,c.employee_code,c.enabled,
                CASE WHEN c.card_hash IS NULL OR c.card_hash='' OR c.card_hash='0' THEN 0 ELSE 1 END AS has_card,
                u.username,s.name AS site
                FROM employee_credentials c JOIN users u ON u.id=c.user_id JOIN terminal_sites s ON s.id=c.site_id", []);

            return array_map(static fn (array $row): array => [
                'user_id' => (int) $row['user_id'],
                'username' => (string) $row['username'], 'employee_code' => (string) $row['employee_code'],
                'site' => (string) $row['site'],
                'access_label' => $row['enabled'] ? ($row['has_card'] ? 'PIN + Karte' : 'PIN') : 'Gesperrt',
            ], $rows);
        });
    }

    public function adminUsersPage(string $search = '', int $page = 1): array
    {
        return $this->snapshot(function (PDO $pdo, LegacyUser $actor) use ($search, $page): array {
            if ($actor->role !== 'admin' || $actor->auth_source !== 'local') {
                throw new AuthorizationException('Nur die Verwaltung darf Benutzer einsehen.');
            }

            // Native scope/LIKE/order/paging; explicitly select only current table fields.
            $perPage = 10;
            $searchParam = "%$search%";
            $offset = ($page - 1) * $perPage;
            $statement = $pdo->prepare('SELECT users.id, users.username, users.email, users.role,
                departments.name AS department_name, supervisors.username AS supervisor_name,
                users.regelarbeitszeit, users.ueberstunden
                FROM users
                LEFT JOIN departments ON users.department_id = departments.id
                LEFT JOIN users AS supervisors ON users.supervisor_id = supervisors.id
                WHERE users.username LIKE :search OR users.email LIKE :search
                ORDER BY users.username LIMIT :offset, :perPage');
            $statement->bindValue(':search', $searchParam, PDO::PARAM_STR);
            $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
            $statement->bindValue(':perPage', $perPage, PDO::PARAM_INT);
            $statement->execute();
            $users = $statement->fetchAll(PDO::FETCH_ASSOC);
            $statement->closeCursor();
            $total = (int) $this->rows($pdo,
                'SELECT COUNT(*) AS total FROM users WHERE username LIKE :search OR email LIKE :search',
                ['search' => $searchParam],
            )[0]['total'];

            return ['users' => $users, 'search' => $search, 'page' => $page,
                'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) ceil($total / $perPage)];
        });
    }

    public function adminSupervisorOptions(): array
    {
        return $this->snapshot(function (PDO $pdo, LegacyUser $actor): array {
            if ($actor->role !== 'admin' || $actor->auth_source !== 'local') {
                throw new AuthorizationException('Nur die Verwaltung darf Benutzer einsehen.');
            }

            // Independent of the search/page; only native select-option fields.
            return $this->rows($pdo, "SELECT id,username FROM users WHERE role IN ('supervisor', 'admin') ORDER BY username", []);
        });
    }

    public function adminUserDetail(mixed $identifier): array
    {
        return $this->snapshot(function (PDO $pdo, LegacyUser $actor) use ($identifier): array {
            if ($actor->role !== 'admin' || $actor->auth_source !== 'local') {
                throw new AuthorizationException('Nur die Verwaltung darf Benutzer einsehen.');
            }
            if ($identifier === null) {
                throw new \InvalidArgumentException('Benutzer-ID nicht angegeben');
            }
            if (! is_scalar($identifier)) {
                throw new \InvalidArgumentException('Ungültige Benutzer-ID.');
            }

            // No SELECT *, model serialization, password, token or auth_source.
            // Identifier stays a bound scalar, preserving native SQLite lookup coercion.
            return $this->rows($pdo, 'SELECT users.id, users.username, users.email, users.role,
                users.department_id, users.supervisor_id, users.regelarbeitszeit, users.ueberstunden,
                departments.name AS department_name, supervisors.username AS supervisor_name
                FROM users
                LEFT JOIN departments ON users.department_id = departments.id
                LEFT JOIN users AS supervisors ON users.supervisor_id = supervisors.id
                WHERE users.id = ?', [$identifier])[0] ?? [];
        });
    }

    public function workModelTargets(): array
    {
        return $this->snapshot(function (PDO $pdo, LegacyUser $actor): array {
            if ($actor->role !== 'admin') {
                throw new AuthorizationException('Nur die Verwaltung darf Arbeitszeitmodelle festlegen.');
            }

            return $this->rows($pdo, 'SELECT id,username FROM users ORDER BY username', []);
        });
    }

    /** Shared native conflict evidence in, native web presentation flags out. */
    private function reportFinality(array $conflicts, \DateTimeImmutable $today): array
    {
        [$weekStart, $weekEnd] = report_week_bounds($today->format('Y-m-d'));

        return [
            'all' => $conflicts !== [],
            'week' => report_conflicts_in_period($conflicts, $weekStart, $weekEnd),
            'month' => report_conflicts_in_period($conflicts, $today->format('Y-m-01'), $today->format('Y-m-t')),
            'year' => report_conflicts_in_period($conflicts, $today->format('Y-01-01'), $today->format('Y-12-31')),
        ];
    }

    private function snapshot(Closure $read, bool $requireKnownRole = true): array
    {
        // No implicit default, environment path, replica, URL, migration, or PDO
        // factory. `chrono` remains intentionally absent in shipped config.
        $configuration = $this->config->get('database.connections.chrono');
        if (! is_array($configuration)
            || ! in_array($configuration['driver'] ?? null, ['sqlite', 'chrono_fixture'], true)
            || isset($configuration['read']) || isset($configuration['write']) || isset($configuration['url'])
            || ($configuration['transaction_mode'] ?? 'DEFERRED') !== 'DEFERRED') {
            throw new LogicException('An explicitly admitted named chrono read connection is required.');
        }

        $guard = $this->auth->guard();
        $authenticated = $guard->user();
        if (! $authenticated instanceof LegacyUser || (int) $authenticated->getKey() < 1) {
            throw new AuthenticationException;
        }
        $provider = $guard instanceof SessionGuard ? $guard->getProvider() : null;
        $webContext = $provider instanceof ExistingAccountUserProvider ? $provider->context($authenticated) : null;
        $lifecycleAuthority = $this->lifecycle?->capture($authenticated);

        $connection = $this->database->connection('chrono');
        if (! $connection instanceof SQLiteConnection || $connection->transactionLevel() !== 0) {
            throw new LogicException('Reads require an idle named SQLite connection, not a writer transaction.');
        }
        $pdo = $connection->getPdo();
        if ($pdo->inTransaction()) {
            throw new LogicException('Reads cannot join a raw transaction.');
        }

        // Definitions only; never include config.php/functions.php or invoke any
        // migration, practice_connection(), raw writer or JSON output wrapper.
        require_once dirname(__DIR__, 3).'/lib/attendance.php';
        require_once dirname(__DIR__, 3).'/lib/practice.php';
        require_once dirname(__DIR__, 3).'/lib/reporting.php';
        require_once dirname(__DIR__, 3).'/lib/home_projection.php';

        return $connection->transaction(function () use ($connection, $pdo, $authenticated, $read, $requireKnownRole, $webContext, $lifecycleAuthority): array {
            if ($connection->getRawPdo() !== $pdo) {
                throw new LogicException('The read snapshot connection changed.');
            }
            // Only the guard supplies identity. Role/team are reloaded in this
            // same snapshot, never accepted from a request or cached session.
            $actor = $this->account($pdo, (int) $authenticated->getKey(), $requireKnownRole);
            if ($lifecycleAuthority !== null) {
                // ACTOR only: retained history of inactive subjects stays visible
                // to an authorized active reader. Never attach secrets to a DTO.
                $current = att_one($pdo, 'SELECT id,role,auth_source,password,account_status,auth_generation FROM users WHERE id=?', [(int) $authenticated->getKey()]);
                if ($current === null || ! $lifecycleAuthority->matches($current)) {
                    throw new AuthenticationException('Unauthenticated.', ['web']);
                }
                unset($current);
            }
            if ($webContext !== null) {
                // ACTOR only, never period subjects/API. Private version data is
                // not attached to the actor model, DTO or returned projection.
                $version = att_one($pdo, 'SELECT password FROM users WHERE id=?', [(int) $authenticated->getKey()])['password'] ?? null;
                if ($actor->auth_source !== $webContext[0] || $actor->role !== $authenticated->role
                    || ! is_string($version) || ! $version
                    || ! hash_equals($webContext[1], hash('sha256', $version))) {
                    throw new AuthenticationException('Unauthenticated.', ['web']);
                }
            }
            $result = $read($pdo, $actor);
            if ($connection->getRawPdo() !== $pdo) {
                throw new LogicException('The read snapshot connection changed.');
            }

            return $result;
        }, 1);
    }

    private function account(PDO $pdo, int $identifier, bool $requireKnownRole = true): LegacyUser
    {
        $row = att_one($pdo, 'SELECT id,username,role,supervisor_id,auth_source FROM users WHERE id=?', [$identifier]);
        if ($row === null || ($requireKnownRole && ! in_array($row['role'], ['user', 'supervisor', 'admin'], true))) {
            throw new AuthenticationException;
        }

        return (new LegacyUser)->newFromBuilder($row, 'chrono');
    }

    private function rows(PDO $pdo, string $sql, array $arguments): array
    {
        $statement = $pdo->prepare($sql);
        $statement->execute($arguments);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        return $rows;
    }
}
