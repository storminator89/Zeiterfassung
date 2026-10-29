<?php

namespace App\Http\Controllers;

use App\Chrono\ChronoReads;
use App\Chrono\HistoryQuery;
use App\View\ExistingUi\AttendancePage;
use App\View\ExistingUi\Copy;
use App\View\ExistingUi\HistoryPage;
use App\View\ExistingUi\Interactions;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Native audit cards only; routing and shared chrome integration remain separate. */
final class LocalWebHistoryController extends Controller
{
    public function __construct(
        private ChronoReads $reads,
        private Factory $auth,
        private Interactions $interactions,
    ) {}

    public function index(Request $request): Response
    {
        // Definitions only: native Berlin display formatting, never its bootstrap.
        require_once dirname(__DIR__, 4).'/lib/attendance.php';
        require_once dirname(__DIR__, 4).'/lib/history_presentation.php';
        require_once dirname(__DIR__, 4).'/lib/history_export.php';
        $exportQueryString = (string) $request->server->get('QUERY_STRING', '');
        parse_str($exportQueryString, $exportParameters);
        $exportMode = \chrono_history_export_mode($exportParameters);
        if ($exportMode['requested']) {
            try {
                $exportQuery = HistoryQuery::fromQueryString($exportQueryString);
                if (! $exportMode['valid'] || $exportQuery->errors) {
                    $export = \chrono_history_export_failure(400);
                } elseif (\chrono_history_correlated_requested($exportParameters)) {
                    $export = \chrono_history_correlated_export_page($this->reads->historyCorrelations($exportQuery), $exportQuery->values);
                } else {
                    $export = \chrono_history_export_page($this->reads->historyQuery($exportQuery), $exportQuery->values);
                }
            } catch (\Illuminate\Auth\AuthenticationException | \Illuminate\Auth\Access\AuthorizationException $authorizationError) {
                // Keep the existing protected-route 401/403 behavior, not a generic export 500.
                throw $authorizationError;
            } catch (\Throwable $exportError) {
                $export = \chrono_history_export_failure();
            }

            return new Response($export['body'], $export['status'], $export['headers']);
        }
        $actor = $this->auth->guard('web')->user();
        $language = $request->query('lang', $request->session()->get('lang', 'de'));
        $language = is_string($language) && in_array($language, ['de', 'en', 'zh'], true) ? $language : 'de';
        $request->session()->put('lang', $language);

        $query = HistoryQuery::fromQueryString($exportQueryString);
        $showRelations = ($exportParameters['relations'] ?? null) === '1';
        $errors = $query->errors;
        if (array_key_exists('relations', $exportParameters) && ! $showRelations) {
            $errors[] = 'Ungültige Auswahl der Historienbezüge.';
        }
        $relations = null;
        $rows = [];
        // The opt-in reuses the existing bounded same-snapshot/owner-checked bundle.
        // Its public export projection drops internal owners/raw fields before Blade.
        if (! $errors && $showRelations) {
            try {
                $bundle = $this->reads->historyCorrelations($query);
                $selected = \chrono_history_correlated_export_page($bundle, $query->values);
                $relations = json_decode($selected['body'], true, 512, JSON_THROW_ON_ERROR)['correlation'];
                foreach ($relations['events'] as &$event) {
                    $event['received_label'] = \attendance_local($event['received_at']);
                    $event['kind_label'] = match ($event['kind']) {
                        'start' => 'Start', 'pause_start' => 'Pausenbeginn',
                        'pause_end' => 'Pausenende', 'end' => 'Ende',
                    };
                }
                unset($event);
                $rows = $bundle['rows'];
            } catch (\Illuminate\Auth\AuthenticationException | \Illuminate\Auth\Access\AuthorizationException $authorizationError) {
                throw $authorizationError;
            } catch (\Throwable) {
                return new Response('Historienbezüge derzeit nicht verfügbar.', 503,
                    ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
            }
        } elseif (! $errors) {
            $rows = $this->reads->historyQuery($query);
        }
        $labels = $query->entities;
        $entries = array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'occurred_at' => \attendance_local((int) $row['occurred_at']),
            'entity' => $labels[$row['entity']] ?? $row['entity'],
            'record_id' => (int) $row['record_id'],
            'operation' => $row['operation'], 'actor' => $row['actor'], 'reason' => $row['reason'],
            // Never pass unfiltered snapshots to the view alongside the selected diff.
            'presentation' => \chrono_history_presentation(
                $row['entity'], $row['before_json'] ?? null, $row['after_json'] ?? null,
            ),
        ], $rows);

        $byAudit = array_column($relations['anchors'] ?? [], null, 'audit_id');
        foreach ($entries as &$entry) {
            $entry['relation'] = $byAudit[(string) $entry['id']] ?? null;
        }
        unset($entry);
        $withRelations = static fn (string $url): string => $url.(str_contains($url, '?') ? '&' : '?').'relations=1';
        $pageUrl = $query->url($query->hasBefore ? (int) $query->values['before'] : null);

        return response()->view('chrono.history', [
            // Deliberate prerequisite: history.php must first join the shared chrome whitelist.
            // Only the separately reviewed protected route may supply this controller's actor.
            'page' => new AttendancePage(
                new Copy($language), (int) $actor->getAuthIdentifier(), $actor->username,
                $actor->role, $this->reads->attendanceStatus(),
                interactions: $this->interactions, activePage: 'history.php',
            ),
            'history' => new HistoryPage(
                $entries, $query->values, $errors, $labels,
                ! $errors && $query->hasBefore ? ($showRelations ? $withRelations($query->url()) : $query->url()) : null,
                count($rows) === 100 ? ($showRelations ? $withRelations($query->url((int) $rows[array_key_last($rows)]['id']))
                    : $query->url((int) $rows[array_key_last($rows)]['id'])) : null,
                ! $errors ? \chrono_history_export_url($query->values) : null,
                ! $errors ? \chrono_history_correlated_export_url($query->values) : null,
                showRelations: $showRelations, relations: $relations,
                relationsUrl: ! $errors ? ($showRelations ? $pageUrl : $withRelations($pageUrl)) : null,
                operations: $query->operations,
            ),
        ], $errors ? 400 : 200, $showRelations ? ['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff'] : []);
    }
}
