<?php

namespace App\Http\Controllers;

use App\Chrono\ChronoReads;
use App\Chrono\LocalWebCarryIn;
use App\Chrono\OwnSettingsProfileReads;
use App\Auth\LegacyUser;
use App\Auth\OwnPasswordChange;
use App\Auth\LocalSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\View\ExistingUi\AttendancePage;
use App\View\ExistingUi\Copy;
use App\View\ExistingUi\Interactions;
use App\View\ExistingUi\SettingsPage;
use App\View\ExistingUi\OwnSettingsProfile;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;
use Throwable;

/** Own preferences and one explicit own-Admin carry-in operation; never account CRUD. */
final class LocalWebSettingsController extends Controller
{
    public function __construct(
        private ChronoReads $reads,
        private Factory $auth,
        private Interactions $interactions,
        private LocalWebCarryIn $carryIn,
        private OwnPasswordChange $passwords,
        private LocalSession $sessions,
    ) {}

    public function index(Request $request): Response
    {
        return $this->page($request);
    }

    public function store(Request $request): Response|RedirectResponse
    {
        require_once dirname(__DIR__, 4).'/lib/language.php';
        $form = $request->request->all();
        if (array_key_exists('session_action', $form)) {
            try {
                $this->passwords->revokeWebApiSessions($form, (string) ($request->server('REMOTE_ADDR') ?? 'local'));
            } catch (AuthenticationException|AuthorizationException|HttpResponseException $error) {
                throw $error;
            } catch (InvalidArgumentException $error) {
                return $this->page($request, $error->getMessage(), status: 400, allowQueryPreference: false);
            } catch (Throwable) {
                try { $this->sessions->logout($request); } catch (Throwable) {}
                return new Response('Abmeldung bisheriger Web/API-Anmeldungen nicht bestätigt. Nicht automatisch wiederholen. Bitte neu anmelden und den Anmeldestand prüfen.', 503,
                    ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
            }
            try { $this->sessions->logout($request); }
            catch (Throwable) {
                return new Response('Bisherige Web/API-Anmeldungen widerrufen; aktuelle Abmeldung nicht vollständig bestätigt. Bitte erneut anmelden.', 503,
                    ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
            }
            return (new RedirectResponse('login.php', 303))->header('Cache-Control', 'no-store');
        }
        if (array_key_exists('password_action', $form)) {
            try {
                // Strict separate payload; no preference is applied or secret flashed.
                $this->passwords->change($form, (string) ($request->server('REMOTE_ADDR') ?? 'local'));
            } catch (AuthenticationException|AuthorizationException|HttpResponseException $error) {
                throw $error;
            } catch (InvalidArgumentException $error) {
                return $this->page($request, $error->getMessage(), status: 400, allowQueryPreference: false);
            } catch (Throwable) {
                // The writer's COMMIT outcome may be unknown. No automatic retry,
                // success notice or continuation with an old cached identity.
                try { $this->sessions->logout($request); } catch (Throwable) {}
                return new Response('Passwortänderung nicht bestätigt. Nicht automatisch wiederholen. Bitte den Anmeldestand prüfen.', 503,
                    ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
            }
            // Only a confirmed COMMIT reaches the existing full session logout.
            try { $this->sessions->logout($request); }
            catch (Throwable) {
                return new Response('Passwort geändert; Abmeldung nicht vollständig bestätigt. Bitte erneut anmelden.', 503,
                    ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
            }
            return (new RedirectResponse('login.php', 303))->header('Cache-Control', 'no-store');
        }
        $hasCarryIn = array_key_exists('ueberstunden', $form);
        if ($hasCarryIn) {
            $actor = $this->auth->guard('web')->user();
            if (! $actor instanceof LegacyUser || (int) $actor->getKey() < 1 || ! in_array($actor->auth_source, ['local', 'ldap'], true)) {
                throw new AuthenticationException('Unauthenticated.', ['web']);
            }
            if ($actor->role !== 'admin') {
                return $this->page($request, 'Nur die Verwaltung darf den eigenen Überstunden-Übertrag ändern.',
                    status: 403, allowQueryPreference: false);
            }
        }
        if (isset($form['lang'])) {
            $request->session()->put('lang', \valid_language($form['lang']));
        }
        $savedCarryIn = null;
        if ($hasCarryIn) {
            try {
                // Deliberately native order: language already assigned; carry-in before theme.
                $savedCarryIn = $this->carryIn->replace($form['ueberstunden']);
            } catch (AuthenticationException|AuthorizationException $error) {
                throw $error;
            } catch (InvalidArgumentException $error) {
                return $this->page($request, $error->getMessage().' Die Sprache kann bereits übernommen sein; Design und Übertrag wurden nicht gespeichert.',
                    status: 400, allowQueryPreference: false);
            } catch (Throwable) {
                return $this->page($request, 'Der Übertrag konnte nicht bestätigt werden. Bitte neu laden und den gespeicherten Stand prüfen. Die Sprache kann bereits übernommen sein; das Design wurde nicht gespeichert.',
                    status: 500, allowQueryPreference: false);
            }
        }
        if (isset($form['theme_mode'])) {
            $mode = in_array($form['theme_mode'], ['light', 'dark', 'system'], true) ? $form['theme_mode'] : 'light';
            $request->session()->put('theme_mode', $mode);
        }

        // Preserve native same-page POST/success-modal semantics, not a new PRG.
        return $this->page($request, saved: true, savedCarryIn: $savedCarryIn);
    }

    private function page(Request $request, ?string $error = null, bool $saved = false, int $status = 200, bool $allowQueryPreference = true, ?string $savedCarryIn = null): Response
    {
        require_once dirname(__DIR__, 4).'/lib/language.php';
        require_once dirname(__DIR__, 4).'/lib/settings_carry_in.php';
        // Native header applies an explicit query language AFTER form handling.
        // Carry-in failure must not subsequently apply the header's query-language choice.
        $query = $request->query->all();
        if ($allowQueryPreference && isset($query['lang'])) {
            $request->session()->put('lang', \valid_language($query['lang']));
        }
        $language = \valid_language($request->session()->get('lang', 'de'));
        $theme = $request->session()->get('theme_mode', 'light');
        $theme = in_array($theme, ['light', 'dark', 'system'], true) ? $theme : 'light';
        // Existing middleware already reloads this read-only LegacyUser account.
        // Select only own display fields, never pass a complete model to the view.
        $actor = $this->auth->guard('web')->user();

        $profile = null;
        if ($this->reads instanceof OwnSettingsProfileReads) {
            try {
                $profile = new OwnSettingsProfile($this->reads->ownSettingsProfile(), $language);
            } catch (AuthenticationException|AuthorizationException $error) {
                throw $error; // Never turn loss of current authority into cached own-profile data.
            } catch (Throwable) {
                $profile = new OwnSettingsProfile(null, $language);
            }
        }

        return response()->view('chrono.settings', [
            'page' => new AttendancePage(
                new Copy($language), (int) $actor->getAuthIdentifier(), $actor->username,
                $actor->role, $this->reads->attendanceStatus(),
                interactions: $this->interactions, activePage: 'settings.php',
            ),
            'settings' => new SettingsPage(
                $theme, $actor->regelarbeitszeit, $actor->role === 'admin'
                    ? ($savedCarryIn ?? \settings_carry_in_display($actor->ueberstunden)) : null,
                $error, $saved, $this->passwords->available(), $language,
                profile: $profile,
            ),
        ], $status, ['Cache-Control' => 'no-store']);
    }
}
