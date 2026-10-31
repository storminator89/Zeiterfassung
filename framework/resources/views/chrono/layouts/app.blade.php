{{-- Existing header.php markup; no legacy PHP bootstrap, data or network scripts. --}}
@php
    $navPage = basename(parse_url(request()->getRequestUri(), PHP_URL_PATH) ?: '') ?: 'index.php';
@endphp
<!DOCTYPE html>
<html lang="{{ $page->copy->language }}" data-theme="{{ $page->theme }}" data-bs-theme="{{ $page->theme }}">

<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $page->copy->text('TITLE') }}</title>

    <link rel="icon" href="assets/kolibri_icon.png" type="image/png">
    <link rel="stylesheet" href="assets/vendor/tabler/1.7.1/tabler.min.css">
    <link rel="stylesheet" href="assets/css/app_ui.css?v={{ substr(hash_file('sha256', public_path('assets/css/app_ui.css')),0,12) }}">
    @if ($page->interactions->home)<script src="assets/js/main.js?v={{ substr(hash_file('sha256', public_path('assets/js/main.js')),0,12) }}" defer></script>@endif
    @if ($page->interactions->attendance)<script src="assets/js/chrono-local-web.js" defer></script>@endif
    @if ($navPage === 'supervisor.php')<script src="assets/vendor/chartjs/4.6.1/chart.umd.min.js" defer></script>@endif
</head>

<body class="chrono-local-ui chrono-calm{{ $page->cspNonce !== null ? ' chrono-admin-credential' : '' }}" data-attendance-state="{{ $page->state }}" data-attendance-user="{{ $page->userId }}" data-attendance-record="{{ $page->expectedRecord ?? '' }}">
    <header class="chrono-header">
        <div class="chrono-header-inner">
            <button id="sidebar-toggle" type="button" class="btn btn-ghost chrono-mobile-menu" aria-label="Menü öffnen oder schließen" aria-controls="sidebar" aria-expanded="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
            <a href="index.php" class="sidebar-brand-row" aria-label="Quodara Chrono, Heute"><span class="sidebar-brand-mark"><img src="assets/kolibri_icon.png" alt="" aria-hidden="true"></span><span>Quodara Chrono</span></a>
            <nav id="sidebar" class="sidebar-navigation" aria-label="{{ $page->copy->text('NAV_MAIN_MENU') }}">
                <button type="button" class="sidebar-close btn btn-ghost" id="sidebar-close" aria-label="Menü schließen">Schließen</button><div class="sidebar-items chrono-primary-nav">
                    <a href="index.php" class="sidebar-link {{ in_array($navPage, ['index.php', 'attendance.php'], true) ? 'active' : '' }}" @if (in_array($navPage, ['index.php', 'attendance.php'], true)) aria-current="page" @endif><x-chrono-icon name="clock" size="20" />
                            <span>Heute</span>
                        </a>
<a href="dashboard.php" class="sidebar-link {{ $navPage === 'dashboard.php' ? 'active' : '' }}" @if ($navPage === 'dashboard.php') aria-current="page" @endif><x-chrono-icon name="chart-bar" size="20" />
                            <span>Übersicht</span>
                        </a>
                    @if (app('router')->has('chrono.vacation'))
                    <a href="vacation.php" class="sidebar-link {{ $navPage === 'vacation.php' ? 'active' : '' }}" @if ($navPage === 'vacation.php') aria-current="page" @endif><x-chrono-icon name="calendar-check" size="20" /><span>Urlaub</span></a>
                    @endif
                </div>
                <details class="chrono-nav-more">
                    <summary>Mehr</summary>
                    <div class="sidebar-items">
                    <a href="adjustments.php" class="sidebar-link {{ $navPage === 'adjustments.php' ? 'active' : '' }}" @if ($navPage === 'adjustments.php') aria-current="page" @endif><x-chrono-icon name="pencil" size="20" /><span>Korrekturen</span></a>
<a href="work_models.php" class="sidebar-link {{ $navPage === 'work_models.php' ? 'active' : '' }}" @if ($navPage === 'work_models.php') aria-current="page" @endif><x-chrono-icon name="briefcase" size="20" /><span>Arbeitszeitmodell</span></a>
<a href="history.php" class="sidebar-link {{ $navPage === 'history.php' ? 'active' : '' }}" @if ($navPage === 'history.php') aria-current="page" @endif><x-chrono-icon name="history" size="20" /><span>Verlauf</span></a>
                    @if (in_array($page->role, ['supervisor', 'admin'], true))
                    <a href="supervisor.php" class="sidebar-link {{ $navPage === 'supervisor.php' ? 'active' : '' }}" @if ($navPage === 'supervisor.php') aria-current="page" @endif><x-chrono-icon name="users" size="20" />
                            <span>Team</span>
                        </a>
                    @endif
                    @if ($page->role === 'admin')
                    <a href="admin.php" class="sidebar-link {{ $navPage === 'admin.php' ? 'active' : '' }}" @if ($navPage === 'admin.php') aria-current="page" @endif><x-chrono-icon name="settings" size="20" />
                            <span>Verwaltung</span>
                        </a>
<a href="terminals.php" class="sidebar-link {{ $navPage === 'terminals.php' ? 'active' : '' }}" @if ($navPage === 'terminals.php') aria-current="page" @endif><x-chrono-icon name="device-desktop" size="20" /><span>Terminals</span></a>
                    @endif
                    <a href="settings.php" class="sidebar-link {{ $navPage === 'settings.php' ? 'active' : '' }}" @if ($navPage === 'settings.php') aria-current="page" @endif><x-chrono-icon name="user" size="20" />
                            <span>{{ $page->copy->text('NAV_SETTINGS') }}</span>
                        </a>
                    </div>
                </details>

            </nav>
            <div id="navbar" class="chrono-header-actions">
                            <details class="chrono-account-menu"><summary>Konto</summary><div class="sidebar-items">
                                            <button type="button" class="sidebar-link w-full" @if ($page->cspNonce === null) onclick="showAboutModal()" @else data-local-about="open" @endif>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 10v7M12 6v1M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20"/></svg>
                            <span>{{ $page->copy->text('NAV_ABOUT') }}</span>
                        </button>
                        <form action="logout.php" method="post">
                            @csrf
                            <button type="submit" @disabled(! $page->interactions->logout) class="sidebar-link w-full">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 3H3v18h6M8 12h13M16 7l5 5-5 5"/></svg>
                                <span>{{ $page->copy->text('NAV_LOGOUT') }}</span>
                            </button>
                        </form>

                </div></details>
    <div class="navbar-end" @if(in_array($navPage,['index.php','attendance.php'],true)) data-home-header @endif>
        <div id="timer" data-attendance-status class="hidden lg:inline-block text-sm mr-3" role="status" aria-live="polite">{{ $page->statusLabel() }}</div>
        <div class="hidden lg:flex items-center gap-2 mr-3" aria-label="Arbeitszeit buchen">
            <button id="startButton" type="button" disabled data-attendance-action="start" class="btn btn-primary btn-sm" @if ($page->state !== 'absent') hidden @endif><i class="fas fa-sign-in-alt" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3h7v18h-7M3 12h14M11 6l6 6-6 6"/></svg></i><span>Kommen</span></button>
            <button id="pauseStartButton" type="button" disabled data-attendance-action="pause_start" class="btn btn-outline-secondary btn-sm" @if ($page->state !== 'working') hidden @endif><i class="fas fa-pause" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M8 5v14M16 5v14"/></svg></i><span>Pause</span></button>
            <button id="pauseEndButton" type="button" disabled data-attendance-action="pause_end" class="btn btn-outline-secondary btn-sm" @if ($page->state !== 'paused') hidden @endif><i class="fas fa-play" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m7 4 14 8-14 8z"/></svg></i><span>Pause beenden</span></button>
            <button id="endButton" type="button" disabled data-attendance-action="end" class="btn btn-secondary btn-sm" @if ($page->state === 'absent') hidden @endif><i class="fas fa-sign-out-alt" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3H3v18h6M8 12h13M16 7l5 5-5 5"/></svg></i><span>Gehen</span></button>
        </div>
        <label class="swap swap-rotate mr-4">
            <input type="checkbox" id="theme-toggle" aria-label="Dunkles Design umschalten" />
            <svg class="swap-on fill-current w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M5.64,17l-.71.71a1,1,0,0,0,0,1.41,1,1,0,0,0,1.41,0l.71-.71A1,1,0,0,0,5.64,17ZM5,12a1,1,0,0,0-1-1H3a1,1,0,0,0,0,2H4A1,1,0,0,0,5,12Zm7-7a1,1,0,0,0,1-1V3a1,1,0,0,0-2,0V4A1,1,0,0,0,12,5ZM5.64,7.05a1,1,0,0,0,.7.29,1,1,0,0,0,.71-.29,1,1,0,0,0,0-1.41l-.71-.71A1,1,0,0,0,4.93,6.34Zm12,.29a1,1,0,0,0,.7-.29l.71-.71a1,1,0,1,0-1.41-1.41L17,5.64a1,1,0,0,0,0,1.41A1,1,0,0,0,17.66,7.34ZM21,11H20a1,1,0,0,0,0,2h1a1,1,0,0,0,0-2Zm-9,8a1,1,0,0,0-1,1v1a1,1,0,0,0,2,0V20A1,1,0,0,0,12,19ZM18.36,17A1,1,0,0,0,17,18.36l.71.71a1,1,0,0,0,1.41,0,1,1,0,0,0,0-1.41ZM12,6.5A5.5,5.5,0,1,0,17.5,12,5.51,5.51,0,0,0,12,6.5Zm0,9A3.5,3.5,0,1,1,15.5,12,3.5,3.5,0,0,1,12,15.5Z" />
            </svg>
            <svg class="swap-off fill-current w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M21.64,13a1,1,0,0,0-1.05-.14,8.05,8.05,0,0,1-3.37.73A8.15,8.15,0,0,1,9.08,5.49a8.59,8.59,0,0,1,.25-2A1,1,0,0,0,8,2.36,10.14,10.14,0,1,0,22,14.05,1,1,0,0,0,21.64,13Zm-9.5,6.69A8.14,8.14,0,0,1,7.08,5.22v.27A10.15,10.15,0,0,0,17.22,15.63a9.79,9.79,0,0,0,2.1-.22A8.11,8.11,0,0,1,12.14,19.73Z" />
            </svg>
        </label>
            </div>
            </div>
        </div>
    </header>

    <!-- About Modal -->
    <dialog id="aboutModal" class="modal">
        <div class="modal-box">
            <h3 class="font-bold text-lg">Über Quodara Chrono</h3>
            <p class="py-4">Quodara Chrono, Ihre Zeiterfassung.</p>
            <div class="modal-action">
                <button class="btn" @if ($page->cspNonce === null) onclick="closeAboutModal()" @else data-local-about="close" @endif>Schließen</button>
            </div>
        </div>
    </dialog>

    <p id="attendance-message" class="attendance-feedback text-sm" role="status" aria-live="polite"></p>

    <!-- Main content wrapper -->
    <div id="main-content" class="main-content page-wrapper">

        <div id="report-finality-notice" class="alert alert-warning mb-4" role="alert" @if (! $page->reportUnresolved) hidden @endif>
            <span data-report-finality-message><strong>Klärung offen: Arbeitszeit überschneidet sich mit ganztägiger Abwesenheit.</strong>
            Betroffene Tages- und Zeitraumssummen sind bis zur manuellen Klärung nicht abschließend und nicht zur Abrechnung geeignet.
            Tatsächliche Arbeits- und Pausenzeiten sowie Gutschriften bleiben unverändert sichtbar; nichts wurde automatisch gelöscht oder gekürzt.
            Bitte über einen Korrekturantrag mit Freigabe durch eine andere berechtigte Person klären.</span>
            <a class="link" href="adjustments.php">Korrekturanträge</a>
        </div>
        @if ($page->reportInvalidCount > 0)
        <div class="alert alert-warning mb-4" role="alert">
            <div><p><strong>Datenqualität: {{ $page->reportInvalidCount }} ungültige eigene Zeiteinträge.</strong>
            Arbeitszeit-, Überstunden- und Berichtssummen sind unvollständig und nicht zur Abrechnung geeignet; auch die Kalenderdarstellung kann unvollständig sein.
            Bitte die betroffenen Einträge in der Arbeitszeitliste über „Korrektur / Storno“ prüfen. Es wurden keine Bestandsdaten geändert.
            <a class="link" href="adjustments.php">Korrekturanträge</a></p>
            @if ($page->interactions->corrections && $page->reportInvalidRecordIds !== [])
            <details class="mt-2"><summary class="cursor-pointer font-semibold">Betroffene eigene Einträge direkt prüfen (max. 10)</summary>
                <ul class="list-disc pl-5 mt-2">
                @foreach ($page->reportInvalidRecordIds as $qualityId)
                    <li><a class="link" target="_blank" rel="noopener noreferrer" href="adjustments.php?record_id={{ rawurlencode((string) $qualityId) }}">Eintrag #{{ $qualityId }} prüfen (neuer Tab)</a></li>
                @endforeach
                </ul>
                @if ($page->reportInvalidCount > 10)<p class="text-sm mt-2">Weitere betroffene Einträge sind hier nicht aufgeführt. Die Arbeitszeitliste und der bisherige Korrekturweg bleiben verfügbar.</p>@endif
            </details>
            @endif</div>
        </div>
        @endif
        @yield('content')

    <script @if ($page->cspNonce !== null) nonce="{{ $page->cspNonce }}" @endif>
        function showAboutModal() {
            document.getElementById('aboutModal').showModal();
        }

        function closeAboutModal() { document.getElementById('aboutModal').close(); }
        @if ($page->cspNonce !== null)
        document.querySelectorAll('[data-local-about]').forEach(function (button) {
            button.addEventListener('click', function () { button.dataset.localAbout === 'open' ? showAboutModal() : closeAboutModal(); });
        });
        @endif

        // Theme toggle functionality
        const themeToggle = document.getElementById('theme-toggle');
        const html = document.documentElement;

        // Storage may be blocked or full. Keep this document usable without
        // claiming the preference was persisted across navigation.
        const chronoPreferenceFallback = new Map();
        function chronoReadPreference(key) {
            if (chronoPreferenceFallback.has(key)) {
                return chronoPreferenceFallback.get(key);
            }
            try { return window.localStorage.getItem(key); } catch (_) { return null; }
        }
        function chronoWritePreference(key, value) {
            chronoPreferenceFallback.set(key, value);
            try {
                window.localStorage.setItem(key, value);
                chronoPreferenceFallback.delete(key);
            } catch (_) { /* Document-local preference only. */ }
        }

        // Function to set the theme
        function setTheme(theme) {
            // Keep the existing browser preference key/precedence, but do not
            // confuse the raw System choice with a renderable DaisyUI theme.
            const preference = ['light', 'dark', 'system'].includes(theme) ? theme : 'light';
            const resolved = preference === 'system'
                ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                : preference;
            html.setAttribute('data-theme', resolved);
            html.setAttribute('data-bs-theme', resolved);
            chronoWritePreference('theme', preference);
            themeToggle.checked = resolved === 'dark';
            // Settings must show the same raw browser choice, not a stale
            // server-side preference or the resolved System colour.
            const settingsTheme = document.getElementById('theme_mode');
            if (settingsTheme) settingsTheme.value = preference;
        }

        // Check for saved theme preference or use the system preference
        const savedTheme = chronoReadPreference('theme');
        const theme = savedTheme || 'system';

        // Set initial theme
        setTheme(theme);
        themeToggle.checked = html.getAttribute('data-theme') === 'dark';

        // Toggle theme when the checkbox is clicked
        themeToggle.addEventListener('change', () => {
            const newTheme = themeToggle.checked ? 'dark' : 'light';
            setTheme(newTheme);
        });

        // Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
            const preference = chronoReadPreference('theme');
            if (!preference || preference === 'system') {
                setTheme('system');
                themeToggle.checked = e.matches;
            }
        });

        function updateSettingsVisibility() {
            var settingsDropdown = document.getElementById('settingsDropdown');
            if (settingsDropdown) { // Nullprüfung hinzugefügt
                if (window.innerWidth < 1024) { // 1024px ist der Standardwert für lg in Tailwind
                    settingsDropdown.style.display = 'none';
                } else {
                    settingsDropdown.style.display = 'block';
                }
            }
        }

        // Führe die Funktion beim Laden der Seite aus
        updateSettingsVisibility();

        // Führe die Funktion aus, wenn die Fenstergröße geändert wird
        window.addEventListener('resize', updateSettingsVisibility);

        // Ensure event selection fields are hidden on load
        document.addEventListener('DOMContentLoaded', function() {
            const eventFields = document.querySelector('.event-selection-fields');
            if (eventFields) {
                eventFields.style.display = 'none';
            }
        });

        // Prevent any other scripts from showing the event selection fields

        // Record changes and cancellations are handled by approved correction requests.

        document.querySelectorAll('.chrono-header details, .chrono-person-menu').forEach(menu => {
            document.addEventListener('click', e => { if (!menu.contains(e.target)) menu.open = false; });
            menu.addEventListener('keydown', e => { if (e.key === 'Escape') { menu.open = false; menu.querySelector('summary').focus(); } });
        });

        // Sidebar toggle functionality
        const sidebar = document.getElementById('sidebar');
        const navbar = document.getElementById('navbar');
        const mainContent = document.getElementById('main-content');
        const sidebarToggle = document.getElementById('sidebar-toggle');

        function setSidebarCollapsed(collapsed) {
            if (window.innerWidth >= 960) collapsed = false;
            sidebar.classList.toggle('collapsed', collapsed);
            navbar.classList.toggle('expanded', collapsed);
            mainContent.classList.toggle('expanded', collapsed);
            sidebarToggle.setAttribute('aria-expanded', String(!collapsed));
            sidebar.setAttribute('aria-hidden', String(collapsed));
            sidebar.inert = collapsed;
        }

        function toggleSidebar() {
            const collapsed = !sidebar.classList.contains('collapsed');
            setSidebarCollapsed(collapsed);
            chronoWritePreference('sidebarCollapsed', String(collapsed));
        }

        // Off-canvas links must not remain in the keyboard order on narrow screens.
        setSidebarCollapsed(window.innerWidth < 960);
        sidebarToggle.addEventListener('click', toggleSidebar);
        document.getElementById('sidebar-close').addEventListener('click', function() {
            setSidebarCollapsed(true);
            if (sidebarToggle.getClientRects().length > 0 && getComputedStyle(sidebarToggle).visibility === 'visible') {
                sidebarToggle.focus();
            }
        });
        document.addEventListener('click', function(e) {
            if (window.innerWidth < 960 && !sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                setSidebarCollapsed(true);
            }
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !sidebar.classList.contains('collapsed') && window.innerWidth < 960) {
                setSidebarCollapsed(true);
                sidebarToggle.focus();
            }
        });
        window.addEventListener('resize', function() {
            setSidebarCollapsed(window.innerWidth < 960);
        });

    </script>



</div>
</body>
</html>
