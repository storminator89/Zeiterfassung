<?php

namespace App\Http\Controllers;

use App\Chrono\ChronoReads;
use App\View\ExistingUi\AttendancePage;
use App\View\ExistingUi\Copy;
use App\View\ExistingUi\Interactions;
use App\View\ExistingUi\SettingsPage;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Ordinary own preferences only: existing session, never an account/backup writer. */
final class LocalWebSettingsController extends Controller
{
    public function __construct(
        private ChronoReads $reads,
        private Factory $auth,
        private Interactions $interactions,
    ) {}

    public function index(Request $request): Response
    {
        return $this->page($request);
    }

    public function store(Request $request): Response
    {
        require_once dirname(__DIR__, 4).'/lib/language.php';
        $form = $request->request->all();
        // This financial/account action is NOT part of ordinary preferences.
        // Refuse BEFORE either form or query can change any preference value.
        if (array_key_exists('ueberstunden', $form)) {
            return $this->page($request,
                'Frühere Überstunden sind in dieser Laravel-Ansicht noch nicht zur Änderung angebunden. Es wurden keine Einstellungen oder Kontodaten geändert.',
                status: 409, allowQueryPreference: false,
            );
        }
        if (isset($form['lang'])) {
            $request->session()->put('lang', \valid_language($form['lang']));
        }
        if (isset($form['theme_mode'])) {
            $mode = in_array($form['theme_mode'], ['light', 'dark', 'system'], true) ? $form['theme_mode'] : 'light';
            $request->session()->put('theme_mode', $mode);
        }

        // Preserve native same-page POST/success-modal semantics, not a new PRG.
        return $this->page($request, saved: true);
    }

    private function page(Request $request, ?string $error = null, bool $saved = false, int $status = 200, bool $allowQueryPreference = true): Response
    {
        require_once dirname(__DIR__, 4).'/lib/language.php';
        // Native header applies an explicit query language AFTER form handling.
        // Unsupported carry-in requests must not partially save even this choice.
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

        return response()->view('chrono.settings', [
            'page' => new AttendancePage(
                new Copy($language), (int) $actor->getAuthIdentifier(), $actor->username,
                $actor->role, $this->reads->attendanceStatus(),
                interactions: $this->interactions, activePage: 'settings.php',
            ),
            'settings' => new SettingsPage(
                $theme, $actor->regelarbeitszeit, $actor->role === 'admin' ? $actor->ueberstunden : null,
                $error, $saved,
            ),
        ], $status);
    }
}
