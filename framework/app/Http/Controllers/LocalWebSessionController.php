<?php

namespace App\Http\Controllers;

use App\Auth\LocalSession;
use App\View\ExistingUi\Copy;
use App\View\ExistingUi\Interactions;
use App\View\ExistingUi\LoginPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Existing local login transport only; its registrar must supply session/CSRF/throttle. */
final class LocalWebSessionController extends Controller
{
    public function __construct(private LocalSession $sessions, private Interactions $interactions) {}

    public function create(Request $request): View
    {
        return $this->page($request);
    }

    public function store(Request $request): View|RedirectResponse
    {
        if ($this->sessions->attempt($request)) {
            return new RedirectResponse('index.php');
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
        return view('chrono.login', ['page' => new LoginPage(
            $this->copy($request), error: $error, interactions: $this->interactions,
        )]);
    }

    private function copy(Request $request): Copy
    {
        $language = $request->query('lang', $request->session()->get('lang', substr($request->header('Accept-Language', 'de'), 0, 2)));
        $language = is_string($language) && in_array($language, ['de', 'en'], true) ? $language : 'de';
        $request->session()->put('lang', $language);

        return new Copy($language);
    }
}
