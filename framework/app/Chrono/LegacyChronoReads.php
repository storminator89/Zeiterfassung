<?php

namespace App\Chrono;

use App\Auth\LegacyUser;
use Closure;
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
final class LegacyChronoReads implements ChronoReads
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly Repository $config,
        private readonly Factory $auth,
        private readonly Gate $gate,
    ) {}

    public function attendanceStatus(): array
    {
        return $this->snapshot(function (PDO $pdo, LegacyUser $actor): array {
            $statement = $pdo->prepare('SELECT id,startzeit,endzeit,pause,absence_kind,planned_minutes FROM zeiterfassung WHERE user_id=? ORDER BY id DESC LIMIT 30');
            $statement->execute([$actor->getKey()]);
            $records = $statement->fetchAll(PDO::FETCH_ASSOC);
            $statement->closeCursor();

            // Match native web status: full own context and state share this snapshot.
            // Keep apiAttendanceStatus()'s independent raw API wire unchanged.
            $context = $this->rows($pdo, 'SELECT * FROM zeiterfassung WHERE user_id=?', [$actor->getKey()]);
            $today = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin'));

            return ['success' => true, 'records' => $records,
                'report_finality' => $this->reportFinality(report_absence_conflicts($context), $today),
            ] + attendance_status($pdo, (int) $actor->getKey());
        });
    }

    public function reportRows(bool $chronological = false): array
    {
        return $this->snapshot(function (PDO $pdo, LegacyUser $actor) use ($chronological): array {
            // Corrections retain their native lexical first-200 options. Only
            // Home opts into the reviewed native instant/id ordering before paging.
            $order = 'startzeit DESC';
            if ($chronological) {
                require_once dirname(__DIR__, 3).'/lib/report_ordering.php';
                report_register_start_order($pdo);
                $order = 'chrono_report_start_epoch(startzeit) DESC,id DESC';
            }

            return $this->rows($pdo, 'SELECT * FROM zeiterfassung WHERE user_id=? ORDER BY '.$order, [$actor->getKey()]);
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
            $plannedWeek = planned_hours($pdo, $user, $weekStart, $weekEnd);
            $plannedMonth = planned_hours($pdo, $user, ...$periods['month']);
            $plannedYear = planned_hours($pdo, $user, ...$periods['year']);
            $monthDays = array_filter($days, static fn (string $day): bool => $day >= $periods['month'][0] && $day <= $periods['month'][1], ARRAY_FILTER_USE_KEY);
            $workedDays = count($monthDays);
            // The native cards remain CURRENT-period statistics when browsing an old week.
            $statistics = [
                'regular_hours' => home_daily_hours($pdo, $user, $today),
                'weeks_count' => count($weeks), 'is_first_week' => count($weeks) === 1,
                'total_overtime' => report_format_hours(home_total_overtime($pdo, $user, $days, (float) ($userData['ueberstunden'] ?? 0.0))),
                'year_overtime' => report_format_hours($yearHours - $plannedYear),
                'week_hours' => report_format_hours($weekHours),
                'remaining_week' => report_format_hours(max(0, $plannedWeek - $weekHours)),
                'weekly_productivity' => number_format(report_percentage($weekHours, $plannedWeek), 1).'%',
                'month_hours' => report_format_hours($monthHours),
                'planned_month' => report_format_hours($plannedMonth),
                'average_daily_hours' => report_format_hours($workedDays > 0 ? $monthHours / $workedDays : 0),
                'worked_days' => $workedDays,
                'monthly_productivity' => number_format(report_percentage($monthHours, $plannedMonth), 1).'%',
            ];
            foreach ([
                'all' => ['weeks_count', 'is_first_week', 'total_overtime'],
                'year' => ['year_overtime'],
                'week' => ['week_hours', 'remaining_week', 'weekly_productivity'],
                'month' => ['month_hours', 'average_daily_hours', 'worked_days', 'monthly_productivity'],
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
            // Exact native scope/order: no subject selector or implicit own rows.
            $records = $this->rows($pdo, "SELECT z.*, u.username, u.id as user_id, u.regelarbeitszeit, u.ueberstunden as vorherige_ueberstunden, substr(z.startzeit,1,10) AS day, NULL AS weekNumber
                            FROM zeiterfassung z
                            JOIN users u ON z.user_id = u.id
                            WHERE u.supervisor_id = ? OR ?='admin'
                            ORDER BY z.startzeit DESC", [$actor->getKey(), $actor->role]);

            return \supervisor_report_projection($pdo, $records, $userSearch, $page);
        });
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
            fn (PDO $pdo, LegacyUser $actor): array => ['success' => true] + attendance_status($pdo, (int) $actor->getKey()),
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
            $predicate = implode(' AND ', $where);
            $statement = $pdo->prepare('SELECT COUNT(*) FROM zeiterfassung WHERE '.$predicate);
            foreach ($parameters as $name => $value) {
                $statement->bindValue($name, $value);
            }
            $statement->execute();
            $total = $statement->fetchColumn();
            $statement->closeCursor();

            $sql = 'SELECT * FROM zeiterfassung WHERE '.$predicate.' ORDER BY startzeit DESC';
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

        $authenticated = $this->auth->guard()->user();
        if (! $authenticated instanceof LegacyUser || (int) $authenticated->getKey() < 1) {
            throw new AuthenticationException;
        }

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

        return $connection->transaction(function () use ($connection, $pdo, $authenticated, $read, $requireKnownRole): array {
            if ($connection->getRawPdo() !== $pdo) {
                throw new LogicException('The read snapshot connection changed.');
            }
            // Only the guard supplies identity. Role/team are reloaded in this
            // same snapshot, never accepted from a request or cached session.
            $actor = $this->account($pdo, (int) $authenticated->getKey(), $requireKnownRole);
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
