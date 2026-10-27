<?php

namespace App\Http\Controllers;

use App\Chrono\ChronoReads;
use App\Chrono\LocalWebPauseSettings;
use App\View\ExistingUi\AdminPauseSettingsPage;
use App\View\ExistingUi\AttendancePage;
use App\View\ExistingUi\Copy;
use App\View\ExistingUi\Interactions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;
use Throwable;

/** Only the original Admin pause card; never include or dispatch native admin.php. */
final class LocalWebAdminPauseSettingsController extends Controller
{
    public function __construct(
        private ChronoReads $reads,
        private LocalWebPauseSettings $pauseSettings,
        private Factory $auth,
        private Interactions $interactions,
    ) {}

    public function index(Request $request): Response
    {
        return $this->page($request);
    }

    // Only the separately registered POST route can reach this mutation.
    public function store(Request $request): Response|RedirectResponse
    {
        $form = $request->request->all();
        if (! isset($form['update_pause_settings'])
            || array_diff(array_keys($form), [...LocalWebPauseSettings::COMMAND_FIELDS, '_token', 'csrf_token']) !== []) {
            // Reject all other/mixed Admin actions before reads, writes or preferences.
            return new Response('Diese Verwaltungsaktion ist in der Laravel-Ansicht noch nicht verbunden.', 400,
                ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        try {
            $this->pauseSettings->apply(array_diff_key($form, array_flip(['_token', 'csrf_token'])));

            // The native handler redirects after commit; no invented flash success.
            return new RedirectResponse('admin.php#pause-settings');
        } catch (AuthenticationException|AuthorizationException $error) {
            throw $error;
        } catch (InvalidArgumentException $error) {
            return $this->page($request, $error->getMessage());
        } catch (Throwable) {
            return $this->page($request,
                'Pauseneinstellungen konnten nicht gespeichert werden. Bitte neu laden und den gespeicherten Stand prüfen.');
        }
    }

    private function page(Request $request, ?string $error = null): Response
    {
        // This reader independently reloads and checks the current local Admin
        // before returning even a read-only configuration row.
        $rules = $this->reads->adminPauseSettings();
        $actor = $this->auth->guard('web')->user();
        require_once dirname(__DIR__, 4).'/lib/language.php';
        $language = \valid_language($request->query('lang', $request->session()->get('lang', 'de')));
        $request->session()->put('lang', $language);

        return response()->view('chrono.admin-pause-settings', [
            'page' => new AttendancePage(
                new Copy($language), (int) $actor->getAuthIdentifier(), $actor->username,
                $actor->role, $this->reads->attendanceStatus(),
                interactions: $this->interactions, activePage: 'admin.php',
            ),
            'adminPauseSettings' => new AdminPauseSettingsPage($rules, $error, $this->interactions->adminPauseSettings),
        ]);
    }
}
