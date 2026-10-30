<?php

namespace App\Http\Controllers;

use App\Chrono\ChronoReads;
use App\Chrono\LocalWebTerminalSites;
use App\Chrono\LocalWebTerminalDevicePairings;
use App\Chrono\TerminalDevicePairing;
use App\Http\AdminCredentialDocument;
use App\Chrono\LocalWebTerminalModuleSettings;
use App\Chrono\LocalWebTerminalCredentialRevocations;
use App\Chrono\LocalWebTerminalDeviceRevocations;
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

/** Existing device/access UI with independently selected fixed Admin actions and ephemeral enrollment. */
final class LocalWebTerminalDevicesController extends Controller
{
    public function __construct(
        private ChronoReads $reads,
        private Factory $auth,
        private Interactions $interactions,
        private LocalWebTerminalSites $sites,
        private ?LocalWebTerminalCredentialRevocations $revocations = null,
        private ?Repository $config = null,
        private ?LocalWebTerminalDeviceRevocations $deviceRevocations = null,
        private ?LocalWebTerminalModuleSettings $moduleSettings = null,
        private ?LocalWebTerminalDevicePairings $devicePairings = null,
    ) {}

    public function index(Request $request): Response
    {
        return $this->page($request);
    }

    /** Only separately selected server capabilities admit their fixed commands. */
    public function store(Request $request): Response|RedirectResponse
    {
        $command = array_diff_key($request->request->all(), array_flip(['_token', 'csrf_token']));
        if (($command['action'] ?? null) === 'device') {
            if (! $this->devicePairingsEnabled()
                || array_diff(array_keys($command), LocalWebTerminalDevicePairings::COMMAND_FIELDS) !== []) {
                return new Response('Diese Terminalaktion ist in diesem Profil nicht verbunden.', 400,
                    ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
            }
            return $this->createDevicePairing($request, $command);
        }
        if (($command['action'] ?? null) === 'toggle') {
            if (! $this->moduleSettingsEnabled()
                || array_diff(array_keys($command), LocalWebTerminalModuleSettings::COMMAND_FIELDS) !== []) {
                return new Response('Diese Terminalaktion ist in diesem Profil nicht verbunden.', 400,
                    ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
            }
            return $this->saveModuleSettings($request, $command);
        }
        if (($command['action'] ?? null) === 'revoke_device') {
            if (! $this->deviceRevocationsEnabled()
                || array_diff(array_keys($command), LocalWebTerminalDeviceRevocations::COMMAND_FIELDS) !== []) {
                return new Response('Diese Terminalaktion ist in diesem Profil nicht verbunden.', 400,
                    ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
            }
            return $this->revokeDevice($request, $command);
        }
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

    private function deviceRevocationsEnabled(): bool
    {
        return $this->deviceRevocations !== null
            && $this->config?->get('chrono_terminal_admin.device_revocations_enabled', false) === true;
    }

    private function revokeDevice(Request $request, array $command): Response|RedirectResponse
    {
        try {
            $this->deviceRevocations->revoke($command);
            // No response claims success before the fixed writer confirms COMMIT.
            return new RedirectResponse('terminals.php?saved=1');
        } catch (AuthenticationException|AuthorizationException $error) {
            throw $error;
        } catch (AttendanceError|InvalidArgumentException $error) {
            return $this->page($request, $error->getMessage());
        } catch (Throwable) {
            return $this->page($request, 'Gerätesperrung konnte nicht bestätigt werden. Bitte neu laden und den gespeicherten Stand prüfen; nicht automatisch wiederholen.');
        }
    }

    private function moduleSettingsEnabled(): bool
    {
        return $this->moduleSettings !== null
            && $this->config?->get('chrono_terminal_admin.module_settings_enabled', false) === true;
    }

    private function saveModuleSettings(Request $request, array $command): Response|RedirectResponse
    {
        try {
            $this->moduleSettings->save($command);
            // No success claim before the same-PDO writer confirms COMMIT/idle.
            return new RedirectResponse('terminals.php?saved=1');
        } catch (AuthenticationException|AuthorizationException $error) {
            throw $error;
        } catch (AttendanceError|InvalidArgumentException $error) {
            return $this->page($request, $error->getMessage());
        } catch (Throwable) {
            return $this->page($request, 'Moduländerung konnte nicht bestätigt werden. Bitte neu laden und den gespeicherten Stand prüfen; nicht automatisch wiederholen.');
        }
    }

    private function devicePairingsEnabled(): bool
    {
        return $this->devicePairings !== null
            && $this->config?->get('app.debug') === false
            && $this->config->get('chrono_terminal_admin.device_pairings_enabled', false) === true;
    }

    private function createDevicePairing(Request $request, array $command): Response
    {
        try {
            $pairing = $this->devicePairings->create($command);
            // Deliberately no PRG/flash/session/URL handoff for this secret.
            // The page rechecks current local Admin authority before rendering.
            return $this->page($request, pairing: $pairing, allowSavedQuery: false);
        } catch (AuthenticationException|AuthorizationException $error) {
            throw $error;
        } catch (AttendanceError|InvalidArgumentException $error) {
            return $this->page($request, $error->getMessage(), allowSavedQuery: false);
        } catch (Throwable) {
            // A commit or response failure must never trigger regeneration/retry
            // or disclose a code whose creation was not confirmed.
            return $this->page($request, 'Geräteanlage oder Codeausgabe konnte nicht bestätigt werden. Bitte neu laden und den gespeicherten Stand prüfen; nicht automatisch wiederholen.', allowSavedQuery: false);
        }
    }

    private function page(Request $request, ?string $error = null, #[\SensitiveParameter] ?TerminalDevicePairing $pairing = null, bool $allowSavedQuery = true): Response
    {
        // The snapshot independently checks the current local Admin before data.
        $projection = $this->reads->adminTerminalDevices();
        $allowPairings = $this->devicePairingsEnabled() && $this->interactions->terminalDevicePairings;
        $pairingSites = $allowPairings ? $this->reads->adminTerminalPairingSites() : [];
        $allowModuleSettings = $this->moduleSettingsEnabled() && $this->interactions->terminalModuleSettings;
        $module = $allowModuleSettings ? $this->reads->adminTerminalModuleSettings() : [];
        $allowDeviceRevocations = $this->deviceRevocationsEnabled() && $this->interactions->terminalDeviceRevocations;
        $allowRevocations = $this->revocationsEnabled() && $this->interactions->terminalCredentialRevocations;
        $credentials = $allowRevocations ? $this->reads->adminTerminalCredentialRevocationTargets()
            : $this->reads->adminTerminalCredentials();
        $actor = $this->auth->guard('web')->user();
        require_once dirname(__DIR__, 4).'/lib/language.php';
        $language = \valid_language($request->query('lang', $request->session()->get('lang', 'de')));
        $request->session()->put('lang', $language);

        $nonce = $this->devicePairingsEnabled() ? AdminCredentialDocument::nonce() : null;
        $response = response()->view('chrono.terminal-devices', [
            'page' => new AttendancePage(
                new Copy($language), (int) $actor->getAuthIdentifier(), $actor->username,
                $actor->role, $this->reads->attendanceStatus(),
                interactions: $this->interactions, activePage: 'terminals.php', cspNonce: $nonce,
            ),
            'terminalDevices' => new TerminalDevicesPage(
                $projection['devices'], $projection['contact_max_age'], $this->interactions->terminalSites,
                $error, $error === null && ($this->interactions->terminalSites || $allowRevocations || $allowDeviceRevocations || $allowModuleSettings) && $allowSavedQuery && $request->query->has('saved'),
                credentials: $credentials,
                credentialRevocationsEnabled: $allowRevocations,
                deviceRevocationsEnabled: $allowDeviceRevocations,
                moduleSettingsEnabled: $allowModuleSettings,
                moduleServerEnabled: $module['server_enabled'] ?? false,
                moduleEnabled: $module['enabled'] ?? false,
                devicePairingsEnabled: $allowPairings,
                pairingSites: $pairingSites,
                pairing: $this->devicePairingsEnabled() ? $pairing : null,
            ),
        ], 200, ($allowRevocations || $allowDeviceRevocations || $allowModuleSettings || $this->devicePairingsEnabled()) ? ['Cache-Control' => 'no-store'] : []);
        if ($nonce !== null) {
            foreach (AdminCredentialDocument::headers($nonce) as $name => $value) {
                // Intersect with any stricter upstream CSP, exactly as the existing Admin document.
                $response->headers->set($name, $value, $name !== 'Content-Security-Policy');
            }
        }

        return $response;
    }
}
