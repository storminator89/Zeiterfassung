<?php

namespace App\Http\Controllers;

use App\Chrono\ChronoReads;
use App\Chrono\LocalWebWorkModels;
use App\View\ExistingUi\AttendancePage;
use App\View\ExistingUi\Copy;
use App\View\ExistingUi\Interactions;
use App\View\ExistingUi\WorkModelsPage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Native model forms only; validation, admin authority, SQL and audit stay in #78. */
final class LocalWebWorkModelsController extends Controller
{
    public function __construct(
        private ChronoReads $reads,
        private LocalWebWorkModels $workModels,
        private Factory $auth,
        private Interactions $interactions,
    ) {}

    public function index(Request $request): View
    {
        return $this->page($request);
    }

    // Only the POST route calls this method; GET/method override never writes.
    public function store(Request $request): View|Response
    {
        try {
            // Raw form bag only; user_id is the target, never the actor or audit identity.
            $command = array_intersect_key($request->request->all(), array_flip([
                'user_id', 'valid_from', 'day1', 'day2', 'day3', 'day4', 'day5', 'day6', 'day7', 'reason',
            ]));
            $this->workModels->apply($command);

            return new RedirectResponse('work_models.php?saved=1');
        } catch (AuthenticationException $error) {
            throw $error; // Preserve the registrar's protected-route auth response.
        } catch (AuthorizationException $error) {
            // The shared service rejects nonadmins before resolving any writer.
            // Native work_models.php likewise aborts before BEGIN, outside its HTML catch.
            return new Response($error->getMessage(), 403, ['Content-Type' => 'text/plain; charset=UTF-8']);
        } catch (\AttendanceError $error) {
            return $this->page($request, $error->getMessage());
        } catch (Throwable) {
            return $this->page($request, 'Modell konnte nicht gespeichert werden.');
        }
    }

    private function page(Request $request, ?string $error = null): View
    {
        // The opt-in registrar must already have refreshed the local web account.
        $actor = $this->auth->guard('web')->user();
        $language = $request->query('lang', $request->session()->get('lang', 'de'));
        $language = is_string($language) && in_array($language, ['de', 'en', 'zh'], true) ? $language : 'de';
        $request->session()->put('lang', $language);

        // Native query parsing only. The shared snapshot owns scope, paging,
        // selected-owner lookup and effective-today precedence, not this view.
        $query = $request->query->all();
        $requestedPage = filter_var($query['model_page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        $selectedId = null;
        if (array_key_exists('model_id', $query)) {
            $rawId = $query['model_id'];
            $selectedId = is_string($rawId) && preg_match('/^[1-9][0-9]*$/D', $rawId)
                ? (filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0) : 0;
        }
        $projection = $this->reads->workModelsPage($requestedPage, $selectedId);
        $display = static fn (array $row): array => [
            'id' => (int) $row['id'], 'username' => $row['username'], 'valid_from' => $row['valid_from'],
            'minutes' => array_map(static fn ($value): int => (int) $value, json_decode($row['minutes_json'], true)),
            'reason' => $row['reason'], 'effective_id' => isset($row['effective_id']) ? (int) $row['effective_id'] : null,
        ];
        $models = array_map($display, $projection['models']);
        // This parameterless API must independently enforce current-admin scope in its snapshot.
        $targets = $actor->role === 'admin' ? $this->reads->workModelTargets() : [];

        return view('chrono.work-models', [
            'page' => new AttendancePage(
                new Copy($language), (int) $actor->getAuthIdentifier(), $actor->username,
                $actor->role, $this->reads->attendanceStatus(),
                interactions: $this->interactions, activePage: 'work_models.php',
            ),
            'workModels' => new WorkModelsPage(
                $models, $targets,
                $projection['today'], $error, $request->query->has('saved'), $this->interactions->workModels,
                total: $projection['total'], page: $projection['page'], pages: $projection['pages'],
                pageSize: $projection['page_size'], selectedModelId: $projection['selected_model_id'],
                selectedModel: $projection['selected_model'] === null ? null : $display($projection['selected_model']),
                effectiveModel: $projection['effective_model'] === null ? null : $display($projection['effective_model']),
                selectionError: $projection['selection_error'],
            ),
        ]);
    }
}
