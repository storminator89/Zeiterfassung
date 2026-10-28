<?php

namespace App\Http\Controllers;

use App\Auth\LegacyUser;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Http\Response;

/** Native denial contracts only; never reads command/upload fields or resolves a writer. */
final class LocalWebLegacyMutationDeniedController extends Controller
{
    public function __construct(private Factory $auth) {}

    public function delete(): Response
    {
        $actor = $this->auth->guard('web')->user();
        if (! $actor instanceof LegacyUser || (int) $actor->getKey() < 1) {
            return $this->text('Nicht angemeldet.', 401);
        }

        return $this->text('Stornierungen bitte über einen begründeten Korrekturantrag vornehmen.', 409);
    }

    public function import(): Response
    {
        // The registrar's existing RefreshLocalAccount/auth.session stack reloads
        // identity/role and revokes stale credentials before this fixed response.
        $actor = $this->auth->guard('web')->user();
        if (! $actor instanceof LegacyUser || (int) $actor->getKey() < 1
            || $actor->auth_source !== 'local' || $actor->role !== 'admin') {
            return $this->text('Administrator access required.', 403);
        }

        return $this->text('Eine Teilwiederherstellung würde die Änderungshistorie zerstören. Vollständiges privates Datenverzeichnis offline wiederherstellen; siehe docs/TERMINAL.md.', 409);
    }

    private function text(string $message, int $status): Response
    {
        return new Response($message, $status, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
