<?php

namespace App\Http\Controllers;

use App\Chrono\ChronoReads;
use App\View\ExistingUi\AttendancePage;
use App\View\ExistingUi\Copy;
use App\View\ExistingUi\Interactions;
use App\View\ExistingUi\TerminalDevicesPage;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Existing device table only: no terminal actions, protocol, credentials or keys. */
final class LocalWebTerminalDevicesController extends Controller
{
    public function __construct(
        private ChronoReads $reads,
        private Factory $auth,
        private Interactions $interactions,
    ) {}

    public function index(Request $request): Response
    {
        // The snapshot independently checks the current local Admin before data.
        $projection = $this->reads->adminTerminalDevices();
        $actor = $this->auth->guard('web')->user();
        require_once dirname(__DIR__, 4).'/lib/language.php';
        $language = \valid_language($request->query('lang', $request->session()->get('lang', 'de')));
        $request->session()->put('lang', $language);

        return response()->view('chrono.terminal-devices', [
            'page' => new AttendancePage(
                new Copy($language), (int) $actor->getAuthIdentifier(), $actor->username,
                $actor->role, $this->reads->attendanceStatus(),
                interactions: $this->interactions, activePage: 'terminals.php',
            ),
            'terminalDevices' => new TerminalDevicesPage($projection['devices'], $projection['contact_max_age']),
        ]);
    }
}
