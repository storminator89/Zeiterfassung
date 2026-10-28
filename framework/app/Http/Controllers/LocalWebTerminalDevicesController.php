<?php

namespace App\Http\Controllers;

use App\Chrono\ChronoReads;
use App\Chrono\LocalWebTerminalSites;
use AttendanceError;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;
use Throwable;
use App\View\ExistingUi\AttendancePage;
use App\View\ExistingUi\Copy;
use App\View\ExistingUi\Interactions;
use App\View\ExistingUi\TerminalDevicesPage;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Existing device/access display and separately opt-in site form; never secrets or device actions. */
final class LocalWebTerminalDevicesController extends Controller
{
    public function __construct(
        private ChronoReads $reads,
        private Factory $auth,
        private Interactions $interactions,
        private LocalWebTerminalSites $sites,
    ) {}

    public function index(Request $request): Response
    {
        return $this->page($request);
    }

    /** Only the separately opted-in POST route may reach the fixed site command. */
    public function store(Request $request): Response|RedirectResponse
    {
        $command = array_diff_key($request->request->all(), array_flip(['_token', 'csrf_token']));
        if (($command['action'] ?? null) !== 'site'
            || array_diff(array_keys($command), LocalWebTerminalSites::COMMAND_FIELDS) !== []) {
            return new Response('Diese Terminalaktion ist in der Laravel-Ansicht nicht verbunden.', 400,
                ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
        try {
            $this->sites->create($command);

            return new RedirectResponse('terminals.php?saved=1');
        } catch (AuthenticationException|AuthorizationException $error) {
            throw $error;
        } catch (AttendanceError|InvalidArgumentException $error) {
            return $this->page($request, $error->getMessage());
        } catch (Throwable) {
            return $this->page($request, 'Standort konnte nicht bestätigt werden. Bitte neu laden und den gespeicherten Stand prüfen.');
        }
    }

    private function page(Request $request, ?string $error = null): Response
    {
        // The snapshot independently checks the current local Admin before data.
        $projection = $this->reads->adminTerminalDevices();
        $credentials = $this->reads->adminTerminalCredentials();
        $actor = $this->auth->guard('web')->user();
        require_once dirname(__DIR__, 4).'/lib/language.php';
        $language = \valid_language($request->query('lang', $request->session()->get('lang', 'de')));
        $request->session()->put('lang', $language);

        return response()->view('chrono.terminal-devices', [
            'page' => new AttendancePage(
                new Copy($language), (int) $actor->getAuthIdentifier(), $actor->username,
                $actor->role, $this->reads->attendanceStatus(),
                interactions: $this->interactions, activePage: 'terminals.php',
            ),
            'terminalDevices' => new TerminalDevicesPage(
                $projection['devices'], $projection['contact_max_age'], $this->interactions->terminalSites,
                $error, $this->interactions->terminalSites && $request->query->has('saved'),
                credentials: $credentials,
            ),
        ]);
    }
}
