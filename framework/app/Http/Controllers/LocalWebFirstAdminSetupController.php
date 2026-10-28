<?php

namespace App\Http\Controllers;

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

/** Token-gated first-admin form, never native register.php or an auth/session bypass. */
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
            return $this->page($request, 'SETUP_UNCONFIRMED', 503);
        }
    }

    private function page(#[\SensitiveParameter] Request $request, ?string $errorKey = null, int $status = 200): Response
    {
        $this->setup->assertConfigured();
        require_once dirname(__DIR__, 4).'/lib/language.php';
        $language = \valid_language($request->query('lang', \initial_language(
            $request->session()->get('lang'), $request->header('Accept-Language', 'de'),
        )));
        $request->session()->put('lang', $language);
        $copy = new Copy($language);

        return response()->view('chrono.setup', [
            'page' => new FirstAdminSetupPage($copy, $errorKey === null ? null : $copy->text($errorKey)),
        ], $status, ['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'no-referrer']);
    }
}
