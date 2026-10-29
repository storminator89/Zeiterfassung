<?php

namespace App\Http\Controllers;

use App\Chrono\ChronoReads;
use App\Chrono\LocalWebApiManagement;
use App\Api\Management\ApiPolicyChange;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Chrono\DepartmentIntegrityReads;
use App\Chrono\LocalWebAccountLifecycle;
use App\Chrono\LocalWebLdapSync;
use App\Chrono\LocalWebLdapSessionRevocations;
use App\Http\AdminCredentialDocument;
use App\View\ExistingUi\LdapSyncForm;
use App\View\ExistingUi\LdapSyncOutcome;
use Illuminate\Contracts\Config\Repository;
use App\Accounts\IntakePolicyAccess;
use App\Chrono\LocalWebDepartments;
use App\Chrono\LocalWebAdminUsers;
use App\Chrono\LocalWebAdminUserMutations;
use App\Auth\LocalSession;
use App\Auth\LegacyUser;
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
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use DomainException;
use Throwable;

/** Original Admin cards and fixed reviewed operations; never native dispatch. */
final class LocalWebAdminPauseSettingsController extends Controller
{
    public function __construct(
        private ChronoReads $reads,
        private LocalWebPauseSettings $pauseSettings,
        private LocalWebDepartments $departments,
        private LocalWebAdminUsers $adminUsers,
        private Factory $auth,
        private Interactions $interactions,
        private ?IntakePolicyAccess $intake = null,
        private ?LocalWebLdapSync $ldapSync = null,
        private ?Repository $config = null,
        private ?LocalWebAdminUserMutations $userMutations = null,
        private ?LocalSession $sessions = null,
        private ?LocalWebLdapSessionRevocations $ldapSessions = null,
        private ?LocalWebAccountLifecycle $accountLifecycle = null,
        private ?LocalWebApiManagement $apiManagement = null,
    ) {}

    public function index(Request $request): Response
    {
        return $this->page($request);
    }

    /** Existing get_user.php GET, reduced to fields actually used by the original UI. */
    public function user(Request $request): JsonResponse|Response
    {
        $headers = ['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff'];
        try {
            $query = $request->query->all();
            $user = $this->reads->adminUserDetail($query['id'] ?? null);
            if ($user === []) {
                return new Response('Benutzer nicht gefunden', 404,
                    ['Content-Type' => 'text/plain; charset=UTF-8', ...$headers]);
            }

            return new JsonResponse($user, 200, $headers);
        } catch (AuthenticationException $error) {
            throw $error;
        } catch (AuthorizationException) {
            return new Response('Nicht autorisiert', 403,
                ['Content-Type' => 'text/plain; charset=UTF-8', ...$headers]);
        } catch (InvalidArgumentException $error) {
            return new Response($error->getMessage(), 400,
                ['Content-Type' => 'text/plain; charset=UTF-8', ...$headers]);
        } catch (Throwable) {
            return new Response('Datenbankfehler: Benutzerdetails nicht verfügbar.', 500,
                ['Content-Type' => 'text/plain; charset=UTF-8', ...$headers]);
        }
    }

    // Only the separately registered POST route can reach this mutation.
    public function store(Request $request): Response|RedirectResponse
    {
        $actor = $this->adminActor();
        $form = $request->request->all();
        $supported = ['update_pause_settings' => LocalWebPauseSettings::COMMAND_FIELDS,
            ...LocalWebDepartments::COMMAND_FIELDS, ...LocalWebAdminUsers::COMMAND_FIELDS];
        if ($this->userMutationsEnabled()) {
            $supported += LocalWebAdminUserMutations::COMMAND_FIELDS;
        }
        if ($this->intake?->isV4() === true || $this->intake?->isV5() === true) {
            $supported['update_intake_policy'] = ['update_intake_policy', 'revision', 'manual_enabled', 'ldap_sync_enabled', 'registration_enabled'];
            if ($this->ldapHttpEnabled()) { $supported['sync_ldap'] = LocalWebLdapSync::COMMAND_FIELDS; }
        }
        if ($this->ldapSessionsEnabled()) {
            $supported['revoke_ldap_sessions'] = LocalWebLdapSessionRevocations::COMMAND_FIELDS;
        }
        if ($this->accountLifecycleEnabled()) {
            $supported['change_account_lifecycle'] = LocalWebAccountLifecycle::COMMAND_FIELDS;
        }
        if ($this->apiManagement !== null) { $supported['api_management'] = ApiPolicyChange::FIELDS; }
        $actions = [];
        foreach ($supported as $flag => $fields) {
            if (isset($form[$flag])) {
                $actions[] = $flag;
            }
        }
        if (count($actions) !== 1
            || array_diff(array_keys($form), [...$supported[$actions[0]], '_token', 'csrf_token']) !== []) {
            // Reject all other/mixed Admin actions before reads, writes or preferences.
            return new Response('Diese Verwaltungsaktion ist in der Laravel-Ansicht noch nicht verbunden.', 400,
                ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        // UI visibility is not authorization. Directory administration keeps
        // ordinary business controls, never these sensitive local operations.
        if ($actor->auth_source !== 'local' && ! in_array($actions[0], [
            'update_pause_settings', 'add_department', 'edit_department', 'delete_department',
        ], true)) {
            throw new AuthorizationException('Diese Sicherheitsverwaltung erfordert lokale Administration.');
        }

        if ($actions[0] === 'change_account_lifecycle') {
            return $this->changeAccountLifecycle($request, $form);
        }

        if ($actions[0] === 'revoke_ldap_sessions') {
            return $this->revokeLdapSessions($request, $form);
        }

        if ($actions[0] === 'sync_ldap') {
            return $this->synchronizeLdap($request, $form);
        }

        if ($actions[0] === 'api_management') { return $this->changeApi($request, $form); }
        if ($actions[0] === 'update_intake_policy') {
            return $this->intakePolicy($request, $form);
        }

        if ($actions[0] === 'add_user') {
            return $this->createUser($request, $form);
        }

        if (in_array($actions[0], ['edit_user', 'delete_user'], true)) {
            return $this->mutateUser($request, $form, $actions[0]);
        }

        if ($actions[0] !== 'update_pause_settings') {
            return $this->department($request, $form, $actions[0]);
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

    /** Prepared FreshWeb V5 binding follows its unchanged Runtime/route gate; no default binding. */
    private function accountLifecycleEnabled(): bool
    {
        return $this->accountLifecycle !== null && $this->sessions !== null
            && $this->config?->get('app.debug') === false && $this->interactions->adminPauseSettings;
    }

    private function changeAccountLifecycle(Request $request, #[\SensitiveParameter] array $form): Response|RedirectResponse
    {
        try {
            $actor = $this->auth->guard('web')->user();
            $result = $this->accountLifecycle->change(array_diff_key($form, array_flip(['_token', 'csrf_token'])));
        } catch (AuthenticationException|AuthorizationException $error) {
            throw $error;
        } catch (\AttendanceError $error) {
            return $this->userMutationText($error->getMessage(), $error->status);
        } catch (InvalidArgumentException $error) {
            return $this->userMutationText($error->getMessage(), 400);
        } catch (Throwable) {
            // Outcome may be unknown; do not issue another form or retry.
            try { $this->sessions->logout($request); } catch (Throwable) {}
            return $this->userMutationText('Kontostatusänderung nicht bestätigt. Nicht automatisch wiederholen. Bitte neu anmelden und den gespeicherten Stand prüfen.', 503);
        }
        if ($actor === null || $result->target === (int) $actor->getAuthIdentifier()) {
            try { $this->sessions->logout($request); }
            catch (Throwable) {
                return $this->userMutationText('Kontostatus gespeichert; Abmeldung nicht vollständig bestätigt. Bitte erneut anmelden.', 503);
            }
            return (new RedirectResponse('login.php', 303))->header('Cache-Control', 'no-store');
        }
        try {
            // Minimal confirmed receipt, no confirmation token, hash or reason.
            $request->session()->put('chrono_account_lifecycle_saved', [
                'actor' => (int) $actor->getAuthIdentifier(), 'target' => $result->target,
                'status' => $result->status,
            ]);
        } catch (Throwable) {
            return $this->userMutationText('Kontostatus gespeichert. Bitte neu laden und den gespeicherten Stand prüfen; nicht erneut senden.', 200);
        }
        return (new RedirectResponse('admin.php#user-management', 303))->header('Cache-Control', 'no-store');
    }

    private function ldapSessionsEnabled(): bool
    {
        return ($this->intake?->isV4() === true || $this->intake?->isV5() === true) && $this->config?->get('app.debug') === false
            && $this->config?->get('chrono_ldap_sessions.http_enabled', false) === true
            && $this->interactions->adminPauseSettings && $this->interactions->ldapSessionRevocations
            && $this->ldapSessions !== null;
    }

    private function revokeLdapSessions(Request $request, #[\SensitiveParameter] array $form): Response|RedirectResponse
    {
        try {
            $this->ldapSessions->revoke(array_diff_key($form, array_flip(['_token', 'csrf_token'])));
        } catch (AuthenticationException|AuthorizationException $error) {
            throw $error;
        } catch (\AttendanceError $error) {
            return $this->userMutationText($error->getMessage(), $error->status);
        } catch (InvalidArgumentException $error) {
            return $this->userMutationText($error->getMessage(), 400);
        } catch (Throwable) {
            return $this->userMutationText('Sitzungsänderung nicht bestätigt. Bitte den Stand prüfen; nicht automatisch wiederholen.', 503);
        }
        // Only after confirmed COMMIT. A lost response or replay cannot rotate
        // the same version twice; no retry or new ticket is issued on this POST.
        try {
            $actor = $this->auth->guard('web')->user();
            $request->session()->flash('chrono_ldap_sessions_revoked', (int) $actor->getAuthIdentifier());
            return new RedirectResponse('admin.php#user-management', 303,
                ['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer']);
        } catch (Throwable) {
            return $this->userMutationText('Sitzungsänderung gespeichert. Die Antwort konnte nicht abgeschlossen werden; nicht erneut senden.', 503);
        }
    }

    private function ldapHttpEnabled(): bool
    {
        return ($this->intake?->isV4() === true || $this->intake?->isV5() === true) && $this->ldapSync !== null
            && $this->config?->get('chrono_ldap_sync.http_enabled', false) === true;
    }

    /** Only the admitted private credential-document branch may expose password editing. */
    private function userMutationsEnabled(): bool
    {
        return ($this->intake?->isV4() === true || $this->intake?->isV5() === true)
            && $this->config?->get('app.debug') === false
            && $this->interactions->adminPauseSettings && $this->userMutations !== null;
    }

    private function mutateUser(#[\SensitiveParameter] Request $request,
        #[\SensitiveParameter] array $form, string $action): Response|RedirectResponse
    {
        if (! $this->userMutationsEnabled()) {
            return $this->userMutationText('Benutzerverwaltung ist in diesem Profil nicht verfügbar.', 503);
        }
        try {
            $result = $this->userMutations->apply(array_diff_key($form, array_flip(['_token', 'csrf_token'])));
        } catch (AuthenticationException|AuthorizationException $error) {
            throw $error;
        } catch (\AttendanceError $error) {
            return $this->userMutationText($error->getMessage(), $error->status);
        } catch (InvalidArgumentException $error) {
            return $this->page($request, $error->getMessage());
        } catch (Throwable) {
            return $this->userMutationText('Benutzeränderung nicht bestätigt. Bitte neu laden und den gespeicherten Stand prüfen; nicht automatisch wiederholen.', 503);
        }

        // The service returned only after COMMIT. Never put logout or a later
        // privileged page read inside the mutation-failure catch above.
        if ($result->reauthenticate) {
            try {
                if ($this->sessions === null) { throw new \LogicException; }
                $this->sessions->logout($request);
            } catch (Throwable) {
                return $this->userMutationText('Änderung gespeichert. Abmeldung konnte nicht bestätigt werden. Bitte erneut anmelden.', 503);
            }

            return new RedirectResponse('login.php', 303, ['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer']);
        }
        try {
            return $this->page($request, successKey: $action === 'delete_user' ? 'USER_DELETED_SUCCESS' : 'USER_UPDATED_SUCCESS');
        } catch (Throwable) {
            return $this->userMutationText('Änderung gespeichert. Die Verwaltungsansicht konnte nicht neu geladen werden. Bitte neu laden oder erneut anmelden.', 503);
        }
    }

    private function userMutationText(string $text, int $status): Response
    {
        return new Response($text, $status, ['Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'no-referrer']);
    }

    private function synchronizeLdap(#[\SensitiveParameter] Request $request, #[\SensitiveParameter] array $form): Response|RedirectResponse
    {
        // Never feed a secret-bearing error into a debug exception page.
        if (! $this->ldapHttpEnabled() || $this->config?->get('app.debug') !== false) {
            return $this->ldapFailure('LDAP synchronization HTTP composition is unavailable.', 503);
        }
        try {
            $plan = $this->ldapSync->synchronize(array_diff_key($form, array_flip(['_token', 'csrf_token'])));
        } catch (AuthenticationException $error) {
            throw $error;
        } catch (AuthorizationException) {
            return $this->ldapFailure('LDAP synchronization is not authorized or is disabled.', 403);
        } catch (\AttendanceError $error) {
            return $this->ldapFailure('Please sign in again.', $error->status === 401 ? 401 : 503);
        } catch (InvalidArgumentException) {
            return $this->ldapFailure('Invalid LDAP synchronization fields or target.', 400);
        } catch (DomainException) {
            return $this->ldapFailure('LDAP synchronization refused: reload the current policy and inspect account-name conflicts.', 409);
        } catch (Throwable) {
            return $this->ldapFailure('LDAP synchronization could not be confirmed. Reload and inspect the stored state; do not retry automatically.', 503);
        }
        try {
            // The service has returned after COMMIT. Every subsequent failure is
            // unconfirmed, never a validation/permission failure or rollback claim.
            $outcome = LdapSyncOutcome::fromCommittedPlan($plan);
            $actor = $this->auth->guard('web')->user();
            $id = $actor === null ? 0 : (int) $actor->getAuthIdentifier();
            if ($id < 1) { throw new \RuntimeException; }
            $request->session()->flash('chrono_ldap_sync_result', ['actor_id' => $id, 'display' => $outcome->toDisplay()]);

            return new RedirectResponse('admin.php#ldap-sync', 303, ['Cache-Control' => 'no-store']);
        } catch (Throwable) {
            return $this->ldapFailure('LDAP synchronization could not be confirmed. Reload and inspect the stored state; do not retry automatically.', 503);
        }
    }

    private function ldapFailure(string $text, int $status): Response
    {
        return new Response($text, $status, ['Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'no-referrer']);
    }

    private function intakePolicy(Request $request, array $form): Response|RedirectResponse
    {
        if (($form['update_intake_policy'] ?? null) !== '1') {
            return new Response('Ungültige Auswahl der Aufnahmewege.', 400,
                ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
        }
        try {
            $this->intake->change(array_diff_key($form, array_flip(['_token', 'csrf_token', 'update_intake_policy'])));

            return new RedirectResponse('admin.php#user-intake-policy');
        } catch (AuthenticationException|AuthorizationException $error) {
            throw $error;
        } catch (InvalidArgumentException|DomainException $error) {
            return $this->page($request, $error->getMessage());
        } catch (Throwable) {
            return $this->page($request, 'Die Aufnahmewege konnten nicht bestätigt werden. Bitte neu laden und den gespeicherten Stand prüfen.');
        }
    }

    private function createUser(Request $request, array $form): Response
    {
        try {
            if (! $this->adminUsers->create(array_diff_key($form, array_flip(['_token', 'csrf_token'])))) {
                // Contracted required-field FALSE is before any user mutation.
                // Do not infer a safe rejection from an exception/unknown outcome.
                require_once dirname(__DIR__, 4).'/lib/admin_create_user_draft.php';
                return $this->page($request, errorKey: 'ERROR_ALL_FIELDS_REQUIRED',
                    createUserDraft: \admin_create_user_rejected_draft(array_diff_key($form, array_flip(['_token', 'csrf_token']))));
            }

            // Native create renders after commit; no redirect/retry/credential echo.
            return $this->page($request, successKey: 'USER_CREATED_SUCCESS');
        } catch (AuthenticationException|AuthorizationException $error) {
            throw $error;
        } catch (InvalidArgumentException|DomainException $error) {
            return $this->page($request, $error->getMessage());
        } catch (Throwable) {
            // Never expose PDO/constraint/account/path details or claim rollback.
            return $this->page($request,
                'Benutzer konnte nicht bestätigt werden. Bitte neu laden und den gespeicherten Stand prüfen.');
        }
    }

    private function department(Request $request, array $form, string $action): Response|RedirectResponse
    {
        try {
            if (! $this->departments->apply(array_diff_key($form, array_flip(['_token', 'csrf_token'])))) {
                return $this->page($request, errorKey: 'ERROR_ALL_FIELDS_REQUIRED');
            }

            return new RedirectResponse('admin.php#department-management');
        } catch (AuthenticationException|AuthorizationException $error) {
            throw $error;
        } catch (InvalidArgumentException|DomainException $error) {
            return $this->page($request, $error->getMessage());
        } catch (Throwable) {
            // Exact existing translated error prefix, never PDO/path diagnostics.
            return $this->page($request, errorKey: match ($action) {
                'add_department' => 'DEPARTMENT_CREATED_ERROR',
                'edit_department' => 'DEPARTMENT_UPDATED_ERROR',
                'delete_department' => 'DEPARTMENT_DELETED_ERROR',
            });
        }
    }

    private function adminActor(): LegacyUser
    {
        $actor = $this->auth->guard('web')->user();
        if (! $actor instanceof LegacyUser || ! in_array($actor->auth_source, ['local', 'ldap'], true)) {
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }
        if ($actor->role !== 'admin') {
            throw new AuthorizationException('Nur die aktuelle Verwaltung darf diese Ansicht verwenden.');
        }
        return $actor;
    }

    private function changeApi(Request $request, #[\SensitiveParameter] array $form): Response|RedirectResponse
    {
        if ($this->apiManagement === null) { throw new AuthorizationException('API-Verwaltung nicht zugelassen.'); }
        try {
            $state = $this->apiManagement->change($form, (string) ($request->server('REMOTE_ADDR') ?? 'local'));
        } catch (AuthenticationException|AuthorizationException|HttpResponseException $error) { throw $error; }
        catch (InvalidArgumentException $error) { return $this->page($request, error: $error->getMessage())->setStatusCode(400); }
        catch (DomainException $error) { return $this->page($request, error: $error->getMessage())->setStatusCode(409); }
        catch (Throwable) {
            return $this->ldapFailure('API-Ergebnis nicht bestätigt. Schlüssel oder Freigabe können bereits gespeichert sein. Stand neu laden, nicht automatisch wiederholen oder Dateien zurücksetzen.', 503);
        }
        // No raw form/password, key metadata or secret is flashed or echoed.
        $request->session()->flash('chrono_api_notice', $state->enabled ? 'enabled' : 'disabled');
        return (new RedirectResponse('admin.php#api-management', 303))->header('Cache-Control', 'no-store');
    }

    private function page(Request $request, ?string $error = null, ?string $errorKey = null, ?string $successKey = null, ?array $createUserDraft = null): Response
    {
        $actor = $this->adminActor();
        $localSecurityControls = $actor->auth_source === 'local';
        $v4 = $this->intake?->isV4() === true;
        $v5 = $this->intake?->isV5() === true;
        if (($v4 || $v5) && $this->config?->get('app.debug') !== false) {
            return $this->ldapFailure('Private non-debug Admin document composition is required.', 503);
        }
        $apiManagement = null; $apiManagementUnavailable = false;
        if ($localSecurityControls && $this->apiManagement !== null) {
            $notice = $request->session()->pull('chrono_api_notice');
            $notice = in_array($notice, ['enabled','disabled','unchanged'], true) ? $notice : null;
            $apiLanguage = $request->session()->get('lang', 'de');
            $apiLanguage = is_string($apiLanguage) && in_array($apiLanguage, ['de','en','zh'], true) ? $apiLanguage : 'de';
            try { $apiManagement = $this->apiManagement->status($notice, $apiLanguage); }
            catch (AuthenticationException|AuthorizationException $error) { throw $error; }
            catch (Throwable) { $apiManagementUnavailable = true; }
        }
        $departmentIntegrity = null;
        $departmentIntegrityRequested = false;
        $query = $request->query->all();
        if ($request->isMethod('GET') && (array_key_exists('department_integrity', $query)
            || array_key_exists('department_after', $query))) {
            if (! $localSecurityControls) {
                throw new AuthorizationException('Nur lokale Administration darf Benutzerzuordnungen prüfen.');
            }
            if (($query['department_integrity'] ?? null) !== '1'
                || (array_key_exists('department_after', $query) && ! is_string($query['department_after']))) {
                return new Response('Ungültige Prüfseite.', 400,
                    ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
            }
            $departmentIntegrityRequested = true;
            try {
                if ($this->reads instanceof DepartmentIntegrityReads) {
                    $departmentIntegrity = $this->reads->departmentIntegrity($query['department_after'] ?? null);
                }
            } catch (AuthenticationException|AuthorizationException $error) {
                throw $error;
            } catch (InvalidArgumentException) {
                return new Response('Ungültige Prüfseite.', 400,
                    ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
            } catch (Throwable) {
                // Unavailable is not an empty or healthy inventory; no raw diagnostics.
                $departmentIntegrity = null;
            }
        }
        // Business readers reload current role/source/version/generation before
        // returning rows. Sensitive local readers are not called for LDAP Admins.
        $rules = $this->reads->adminPauseSettings();
        $departments = $this->reads->adminDepartments();
        $supervisors = []; $users = []; $intakePolicy = null;
        $ldapForm = null; $ldapOutcome = null; $ldapNotice = null;
        $ldapSessionConfirmations = []; $ldapSessionNotice = null;
        $lifecycleUsers = []; $lifecycleNotice = null;
        if ($localSecurityControls) {
            $supervisors = $this->reads->adminSupervisorOptions();
            $query = $request->query->all();
            $search = $query['search'] ?? '';
            $requestedPage = $query['page'] ?? 1;
            if (! is_string($search) || ! is_scalar($requestedPage)) {
                return new Response('Ungültige Benutzersuche oder Seite.', 400,
                    ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
            }
            // Native scalar cast, including zero/negative/out-of-range pages; no new clamping.
            $users = $this->reads->adminUsersPage($search, (int) $requestedPage);
            $policy = $this->intake?->current();
            $ldapForm = null;
            $ldapOutcome = null;
            $ldapNotice = null;
            if ($policy !== null) {
                if (! $policy->ldapSyncEnabled) {
                    $ldapNotice = 'disabled'; // All-off Admin recovery must remain reachable, no factory call.
                } elseif (! $this->ldapHttpEnabled()) {
                    $ldapNotice = 'not_ready';
                } else {
                    try {
                        $ldapForm = LdapSyncForm::fromTarget($this->ldapSync->targetForForm());
                    } catch (AuthenticationException $error) {
                        throw $error;
                    } catch (AuthorizationException $error) {
                        // Distinguish a policy-off race from lost Admin authority.
                        $policy = $this->intake->current();
                        if ($policy === null || $policy->ldapSyncEnabled) { throw $error; }
                        $ldapNotice = 'disabled';
                    } catch (Throwable) {
                        $ldapNotice = 'not_ready'; // Fixed text, no private resolver diagnostics.
                    }
                }
            }
            $intakePolicy = $policy === null ? null : [
                'revision' => $policy->revision, 'manual_enabled' => $policy->manualEnabled,
                'ldap_sync_enabled' => $policy->ldapSyncEnabled, 'registration_enabled' => $policy->registrationEnabled,
            ];
            if ($v4 || $v5) {
                $result = $request->session()->pull('chrono_ldap_sync_result');
                if (is_array($result) && count($result) === 2 && ($result['actor_id'] ?? null) === (int) $actor->getAuthIdentifier()
                    && is_array($result['display'] ?? null)) {
                    try { $ldapOutcome = LdapSyncOutcome::fromDisplay($result['display']); }
                    catch (Throwable) { $ldapNotice = 'unconfirmed'; }
                }
            }
            $ldapSessionConfirmations = [];
            $ldapSessionNotice = null;
            if ($this->ldapSessionsEnabled()) {
                // Preserve a known committed outcome even if a new form cannot be issued.
                if ($request->session()->pull('chrono_ldap_sessions_revoked') === (int) $actor->getAuthIdentifier()) {
                    $ldapSessionNotice = 'Sitzungswiderruf gespeichert. Bestehende Sitzungen werden beim nächsten autoritativen Check abgewiesen. Erneute LDAP-Anmeldung bleibt möglich; Terminal-Zugänge bleiben unverändert.';
                }
                try {
                    // Only server-projected ten-row IDs, never extra request fields.
                    $ldapSessionConfirmations = $this->ldapSessions->confirmations(
                        array_map(static fn (array $user): int => (int) $user['id'], $users['users']));
                } catch (AuthenticationException|AuthorizationException $error) {
                    throw $error;
                } catch (Throwable) {
                    $ldapSessionNotice = ($ldapSessionNotice === null ? '' : $ldapSessionNotice.' ')
                        .'Neue Bestätigungsformulare sind derzeit nicht verfügbar. Bitte den gespeicherten Stand prüfen; nicht erneut senden.';
                }
            }
            $lifecycleUsers = [];
            $lifecycleNotice = null;
            if ($this->accountLifecycleEnabled()) {
                $receipt = $request->session()->pull('chrono_account_lifecycle_saved');
                if (is_array($receipt) && array_keys($receipt) === ['actor', 'target', 'status']
                    && $receipt['actor'] === (int) $actor->getAuthIdentifier() && is_int($receipt['target'])
                    && in_array($receipt['status'], ['active', 'inactive', 'departed'], true)) {
                    $lifecycleNotice = 'Kontostatus gespeichert. Der unten angezeigte Stand wird neu gelesen; Historie und offene Arbeitszeiten bleiben erhalten.';
                }
                try {
                    $lifecycleUsers = $this->accountLifecycle->forUsers(
                        array_map(static fn (array $user): int => (int) $user['id'], $users['users']));
                } catch (AuthenticationException|AuthorizationException $error) {
                    throw $error;
                } catch (Throwable) {
                    $lifecycleNotice = ($lifecycleNotice === null ? '' : $lifecycleNotice.' ')
                        .'Neue Kontostatusformulare sind nicht verfügbar. Bitte den gespeicherten Stand prüfen; nicht erneut senden.';
                }
            }
        }
        $nonce = ($v4 || $v5 || $this->accountLifecycleEnabled()) ? AdminCredentialDocument::nonce() : null;
        require_once dirname(__DIR__, 4).'/lib/language.php';
        $language = \valid_language($request->query('lang', $request->session()->get('lang', 'de')));
        $request->session()->put('lang', $language);
        $copy = new Copy($language);

        $response = response()->view('chrono.admin-pause-settings', [
            'managedUsersEnabled' => $localSecurityControls && $this->userMutationsEnabled(),
            'apiManagement' => $apiManagement, 'apiManagementUnavailable' => $apiManagementUnavailable,
            'page' => new AttendancePage(
                $copy, (int) $actor->getAuthIdentifier(), $actor->username,
                $actor->role, $this->reads->attendanceStatus(),
                interactions: $this->interactions, activePage: 'admin.php',
                cspNonce: $nonce,
            ),
            'adminPauseSettings' => new AdminPauseSettingsPage(
                $rules, $errorKey === null ? $error : $copy->text($errorKey),
                $this->interactions->adminPauseSettings, $departments, $users, $supervisors,
                $successKey === null ? null : $copy->text($successKey),
                intakePolicy: $intakePolicy,
                ldapSyncForm: $ldapForm, ldapSyncOutcome: $ldapOutcome, ldapSyncNotice: $ldapNotice,
                ldapSessionConfirmations: $ldapSessionConfirmations, ldapSessionNotice: $ldapSessionNotice,
                lifecycleUsers: $lifecycleUsers, lifecycleNotice: $lifecycleNotice,
                accountLifecycleV5: $v5,
                createUserDraft: $localSecurityControls ? $createUserDraft : null,
                localSecurityControls: $localSecurityControls,
                departmentIntegrity: $departmentIntegrity,
                departmentIntegrityRequested: $departmentIntegrityRequested,
            ),
        ], 200, ['Cache-Control' => 'no-store']);
        if ($nonce !== null) {
            foreach (AdminCredentialDocument::headers($nonce) as $name => $value) {
                // Multiple CSPs intersect: never replace an existing stricter one.
                $response->headers->set($name, $value, $name !== 'Content-Security-Policy');
            }
        }

        return $response;
    }
}
