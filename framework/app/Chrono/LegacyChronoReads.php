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

            return ['success' => true, 'records' => $records] + attendance_status($pdo, (int) $actor->getKey());
        });
    }

    public function reportRows(): array
    {
        return $this->snapshot(fn (PDO $pdo, LegacyUser $actor): array =>
            $this->rows($pdo, 'SELECT * FROM zeiterfassung WHERE user_id=? ORDER BY startzeit DESC', [$actor->getKey()])
        );
    }

    public function homeSummary(): array
    {
        return $this->snapshot(function (PDO $pdo, LegacyUser $actor): array {
            $userData = $this->rows($pdo, 'SELECT regelarbeitszeit,ueberstunden FROM users WHERE id=?', [$actor->getKey()])[0];
            $records = $this->rows($pdo, 'SELECT * FROM zeiterfassung WHERE user_id=? ORDER BY startzeit DESC', [$actor->getKey()]);

            return home_summary($pdo, (int) $actor->getKey(), report_days($records), (float) ($userData['ueberstunden'] ?? 0.0), new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin')));
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

    public function workModelTargets(): array
    {
        return $this->snapshot(function (PDO $pdo, LegacyUser $actor): array {
            if ($actor->role !== 'admin') {
                throw new AuthorizationException('Nur die Verwaltung darf Arbeitszeitmodelle festlegen.');
            }

            return $this->rows($pdo, 'SELECT id,username FROM users ORDER BY username', []);
        });
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
