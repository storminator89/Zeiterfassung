<?php

namespace App\Http\Controllers;

use App\Auth\LocalSession;
use App\View\ExistingUi\Copy;
use App\View\ExistingUi\Interactions;
use App\View\ExistingUi\LoginPage;
use Illuminate\Contracts\View\View;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/** Existing local login transport only; its registrar must supply session/CSRF/throttle. */
final class LocalWebSessionController extends Controller
{
    public function __construct(private LocalSession $sessions, private Interactions $interactions, private Repository $config) {}

    public function create(Request $request): View
    {
        return $this->page($request);
    }

    public function store(Request $request): View|RedirectResponse|Response
    {
        try {
            if ($this->sessions->attempt($request)) {
                return new RedirectResponse('index.php');
            }
        } catch (ServiceUnavailableHttpException) {
            return new Response($this->page($request, 'Anmeldung derzeit nicht bestätigt. Bitte später erneut versuchen.'), 503);
        }

        $copy = $this->copy($request);
        $username = $request->input('username');
        $password = $request->input('password');
        $key = ! is_string($username) || ! is_string($password) || trim($username) === '' || $password === ''
            ? 'ERROR_ALL_FIELDS_REQUIRED' : 'ERROR_INVALID_CREDENTIALS';

        return $this->page($request, $copy->text($key));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->sessions->logout($request);

        return new RedirectResponse('login.php');
    }

    private function page(Request $request, ?string $error = null): View
    {
        $copy = $this->copy($request);
        $registrationEnabled = $this->config->get('chrono_registration.enabled', false) === true;
        $setupEnabled = $this->config->get('chrono_setup.enabled', false) === true
            && Route::has('chrono.first-admin-setup');
        return view('chrono.login', ['page' => new LoginPage(
            $copy, error: $error, interactions: $this->interactions,
            showRegistration: $registrationEnabled,
            showSetup: $setupEnabled,
            successMessage: $setupEnabled && $request->query('setup') === 'complete'
                ? $copy->text('SETUP_RESULT') : ($registrationEnabled && $request->query('registration') === 'complete'
                ? $copy->text('REGISTRATION_RESULT') : null),
        )]);
    }

    private function copy(Request $request): Copy
    {
        // Definitions only. Keep explicit/stored Chinese, but preserve native
        // de/en-only initial browser detection and its stored-choice precedence.
        require_once dirname(__DIR__, 4).'/lib/language.php';
        $language = \valid_language($request->query('lang', \initial_language(
            $request->session()->get('lang'), $request->header('Accept-Language', 'de'),
        )));
        $request->session()->put('lang', $language);

        return new Copy($language);
    }
}
