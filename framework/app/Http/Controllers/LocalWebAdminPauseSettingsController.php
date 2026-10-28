<?php

namespace App\Http\Controllers;

use App\Chrono\ChronoReads;
use App\Chrono\LocalWebDepartments;
use App\Chrono\LocalWebAdminUsers;
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

/** Original Admin cards, bounded create and read-only details; no native dispatch. */
final class LocalWebAdminPauseSettingsController extends Controller
{
    public function __construct(
        private ChronoReads $reads,
        private LocalWebPauseSettings $pauseSettings,
        private LocalWebDepartments $departments,
        private LocalWebAdminUsers $adminUsers,
        private Factory $auth,
        private Interactions $interactions,
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
        $form = $request->request->all();
        $supported = ['update_pause_settings' => LocalWebPauseSettings::COMMAND_FIELDS,
            ...LocalWebDepartments::COMMAND_FIELDS, ...LocalWebAdminUsers::COMMAND_FIELDS];
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

        if ($actions[0] === 'add_user') {
            return $this->createUser($request, $form);
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

    private function createUser(Request $request, array $form): Response
    {
        try {
            if (! $this->adminUsers->create(array_diff_key($form, array_flip(['_token', 'csrf_token'])))) {
                return $this->page($request, errorKey: 'ERROR_ALL_FIELDS_REQUIRED');
            }

            // Native create renders after commit; no redirect/retry/credential echo.
            return $this->page($request, successKey: 'USER_CREATED_SUCCESS');
        } catch (AuthenticationException|AuthorizationException $error) {
            throw $error;
        } catch (InvalidArgumentException $error) {
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

    private function page(Request $request, ?string $error = null, ?string $errorKey = null, ?string $successKey = null): Response
    {
        // This reader independently reloads and checks the current local Admin
        // before returning even a read-only configuration row.
        $rules = $this->reads->adminPauseSettings();
        $departments = $this->reads->adminDepartments();
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
        $actor = $this->auth->guard('web')->user();
        require_once dirname(__DIR__, 4).'/lib/language.php';
        $language = \valid_language($request->query('lang', $request->session()->get('lang', 'de')));
        $request->session()->put('lang', $language);
        $copy = new Copy($language);

        return response()->view('chrono.admin-pause-settings', [
            'page' => new AttendancePage(
                $copy, (int) $actor->getAuthIdentifier(), $actor->username,
                $actor->role, $this->reads->attendanceStatus(),
                interactions: $this->interactions, activePage: 'admin.php',
            ),
            'adminPauseSettings' => new AdminPauseSettingsPage(
                $rules, $errorKey === null ? $error : $copy->text($errorKey),
                $this->interactions->adminPauseSettings, $departments, $users, $supervisors,
                $successKey === null ? null : $copy->text($successKey),
            ),
        ], 200, ['Cache-Control' => 'no-store']);
    }
}
