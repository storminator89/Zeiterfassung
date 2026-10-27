<?php

namespace App\Http\Controllers;

use App\Chrono\ChronoReads;
use App\Chrono\LocalWebWorkModels;
use App\View\ExistingUi\AttendancePage;
use App\View\ExistingUi\Copy;
use App\View\ExistingUi\Interactions;
use App\View\ExistingUi\WorkModelsPage;
use DateTimeImmutable;
use DateTimeZone;
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
        $language = is_string($language) && in_array($language, ['de', 'en'], true) ? $language : 'de';
        $request->session()->put('lang', $language);

        // Existing own/admin history order and 200-row cap come from the shared reader.
        $models = array_map(static fn (array $row): array => [
            'username' => $row['username'], 'valid_from' => $row['valid_from'],
            'minutes' => array_map(static fn ($value): int => (int) $value, json_decode($row['minutes_json'], true)),
            'reason' => $row['reason'],
        ], $this->reads->workModels());
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
                // Native config.php sets Berlin; do not inherit Laravel's UTC display date.
                (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format('Y-m-d'),
                $error, $request->query->has('saved'), $this->interactions->workModels,
            ),
        ]);
    }
}
