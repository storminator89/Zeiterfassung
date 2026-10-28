<?php

namespace App\Http\Controllers;

use App\Chrono\ChronoReads;
use App\Chrono\LocalWebTerminalSites;
use App\Chrono\LocalWebTerminalCredentialRevocations;
use Illuminate\Contracts\Config\Repository;
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
        private ?LocalWebTerminalCredentialRevocations $revocations = null,
        private ?Repository $config = null,
    ) {}

    public function index(Request $request): Response
    {
        return $this->page($request);
    }

    /** Only the separately opted-in POST route may reach the fixed site command. */
    public function store(Request $request): Response|RedirectResponse
    {
        $command = array_diff_key($request->request->all(), array_flip(['_token', 'csrf_token']));
        if (($command['action'] ?? null) === 'revoke_credential') {
            if (! $this->revocationsEnabled()
                || array_diff(array_keys($command), LocalWebTerminalCredentialRevocations::COMMAND_FIELDS) !== []) {
                return new Response('Diese Terminalaktion ist in diesem Profil nicht verbunden.', 400,
                    ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
            }
            return $this->revokeCredential($request, $command);
        }
        if (($command['action'] ?? null) !== 'site'
            || array_diff(array_keys($command), LocalWebTerminalSites::COMMAND_FIELDS) !== []) {
            return new Response('Diese Terminalaktion ist in der Laravel-Ansicht nicht verbunden.', 400,
                ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
        // Adding a revoke-only POST must not also grant the separate Site action.
        if ($this->config !== null
            && $this->config->get('chrono_terminal_admin.site_creation_enabled', false) !== true) {
            return new Response('Diese Terminalaktion ist in diesem Profil nicht verbunden.', 400,
                ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
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

    private function revocationsEnabled(): bool
    {
        return $this->revocations !== null
            && $this->config?->get('chrono_terminal_admin.credential_revocations_enabled', false) === true;
    }

    private function revokeCredential(Request $request, array $command): Response|RedirectResponse
    {
        try {
            $this->revocations->revoke($command);
            // The fixed writer returned only after confirmed COMMIT/idle.
            return new RedirectResponse('terminals.php?saved=1');
        } catch (AuthenticationException|AuthorizationException $error) {
            throw $error;
        } catch (AttendanceError|InvalidArgumentException $error) {
            return $this->page($request, $error->getMessage());
        } catch (Throwable) {
            return $this->page($request, 'Sperrung konnte nicht bestätigt werden. Bitte neu laden und den gespeicherten Stand prüfen; nicht automatisch wiederholen.');
        }
    }

    private function page(Request $request, ?string $error = null): Response
    {
        // The snapshot independently checks the current local Admin before data.
        $projection = $this->reads->adminTerminalDevices();
        $allowRevocations = $this->revocationsEnabled() && $this->interactions->terminalCredentialRevocations;
        $credentials = $allowRevocations ? $this->reads->adminTerminalCredentialRevocationTargets()
            : $this->reads->adminTerminalCredentials();
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
                $error, ($this->interactions->terminalSites || $allowRevocations) && $request->query->has('saved'),
                credentials: $credentials,
                credentialRevocationsEnabled: $allowRevocations,
            ),
        ], 200, $allowRevocations ? ['Cache-Control' => 'no-store'] : []);
    }
}
