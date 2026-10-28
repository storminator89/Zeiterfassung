<?php

namespace App\Http\Controllers;

use App\Auth\SelfRegistration;
use App\View\ExistingUi\Copy;
use App\View\ExistingUi\RegistrationPage;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/** Existing UI card; ordinary registration is separate from native first-admin setup. */
final class LocalWebRegistrationController extends Controller
{
    public function __construct(private SelfRegistration $registration, private Repository $config) {}

    public function create(Request $request): Response
    {
        return $this->page($request);
    }

    public function store(#[\SensitiveParameter] Request $request): Response|RedirectResponse
    {
        $this->requireEnabled();
        try {
            $this->registration->register(array_diff_key($request->request->all(), array_flip(['_token', 'csrf_token'])));

            // Same response for accepted and duplicate identity; never auto-login.
            return new RedirectResponse('login.php?registration=complete', 303, ['Cache-Control' => 'no-store']);
        } catch (NotFoundHttpException $error) {
            throw $error;
        } catch (AuthorizationException) {
            return $this->page($request, 'REGISTRATION_NOT_READY', 503);
        } catch (InvalidArgumentException) {
            return $this->page($request, 'REGISTRATION_INVALID', 400);
        } catch (Throwable) {
            // No SQL/identity/exception message, retry or false rollback/success claim.
            return $this->page($request, 'REGISTRATION_UNCONFIRMED', 503);
        }
    }

    private function page(Request $request, ?string $errorKey = null, int $status = 200): Response
    {
        $this->requireEnabled();
        require_once dirname(__DIR__, 4).'/lib/language.php';
        $language = \valid_language($request->query('lang', \initial_language(
            $request->session()->get('lang'), $request->header('Accept-Language', 'de'),
        )));
        $request->session()->put('lang', $language);
        $copy = new Copy($language);

        return response()->view('chrono.register', [
            'page' => new RegistrationPage($copy, $errorKey === null ? null : $copy->text($errorKey)),
        ], $status, ['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function requireEnabled(): void
    {
        if ($this->config->get('chrono_registration.enabled', false) !== true) {
            throw new NotFoundHttpException;
        }
    }
}
