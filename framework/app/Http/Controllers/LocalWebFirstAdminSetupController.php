<?php

namespace App\Http\Controllers;

use App\Http\InitialWebLanguage;

use App\Auth\FirstAdminSetup;
use App\View\ExistingUi\Copy;
use App\View\ExistingUi\FirstAdminSetupPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/** Existing first-admin card, selected only by its trusted local-install or token profile. */
final class LocalWebFirstAdminSetupController extends Controller
{
    public function __construct(private FirstAdminSetup $setup) {}

    public function create(#[\SensitiveParameter] Request $request): Response
    {
        return $this->page($request);
    }

    public function store(#[\SensitiveParameter] Request $request): Response|RedirectResponse
    {
        $this->setup->assertConfigured();
        $localFirstUser = ! $this->setup->requiresOperatorToken();
        try {
            $this->setup->create(array_diff_key($request->request->all(), array_flip(['_token', 'csrf_token'])));

            return new RedirectResponse('login.php?setup=complete', 303, ['Cache-Control' => 'no-store']);
        } catch (NotFoundHttpException $error) {
            throw $error;
        } catch (AccessDeniedHttpException) {
            return $this->page($request, 'SETUP_TOKEN_INVALID', 403);
        } catch (InvalidArgumentException) {
            return $this->page($request, 'SETUP_INVALID', 400);
        } catch (Throwable) {
            if ($localFirstUser) {
                // Closure may already exist after a late error. Do not reopen
                // the form or turn a confirmed/uncertain COMMIT into a 404/rollback claim.
                return new Response('Ersteinrichtung nicht bestätigt. Bitte gespeicherten Stand und login.php?setup=complete prüfen; nicht automatisch wiederholen.', 503,
                    ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
            }
            return $this->page($request, 'SETUP_UNCONFIRMED', 503);
        }
    }

    private function page(#[\SensitiveParameter] Request $request, ?string $errorKey = null, int $status = 200): Response
    {
        $this->setup->assertConfigured();
        require_once dirname(__DIR__, 4).'/lib/language.php';
        $language = \valid_language($request->query('lang', InitialWebLanguage::resolve(
            $request->session()->get('lang'), $request->header('Accept-Language', 'de'),
        )));
        $request->session()->put('lang', $language);
        $copy = new Copy($language);

        return response()->view('chrono.setup', [
            'page' => new FirstAdminSetupPage($copy, $errorKey === null ? null : $copy->text($errorKey), $this->setup->requiresOperatorToken()),
        ], $status, ['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'no-referrer']);
    }
}
