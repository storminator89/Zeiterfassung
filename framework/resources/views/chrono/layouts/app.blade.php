{{-- Existing header.php markup; no legacy PHP bootstrap, data or network scripts. --}}
<!DOCTYPE html>
<html lang="{{ $page->copy->language }}" data-theme="{{ $page->theme }}">

<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $page->copy->text('TITLE') }}</title>

    <link rel="icon" href="assets/kolibri_icon.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />


    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@400;600&display=swap" rel="stylesheet">


    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">


    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <!-- SweetAlert2 JS -->


    <!-- Shepherd.js für die Tour -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/shepherd.js@10.0.1/dist/css/shepherd.css"/>


    <link rel="stylesheet" type="text/css" href="./assets/css/main.css">
    <link rel="stylesheet" href="assets/css/app_ui.css">
    <style>
        @media (max-width: 640px) {
            .table {
                font-size: 0.8rem;
            }

            .table th,
            .table td {
                padding: 0.5rem;
            }

            .input,
            .select {
                font-size: 0.8rem;
                padding: 0.3rem;
            }
        }

        .flatpickr-calendar {
            z-index: 9999 !important;
        }

        .editable-datetime {
            cursor: pointer;
        }

        /* Styles for the time records table */
        .summary-row {
            border-bottom: 1px solid #eee;
        }

        .summary-row:hover {
            background-color: rgba(0,0,0,0.05);
        }

        .rotate-90 {
            transform: rotate(90deg);
        }

        .details-row {
            background-color: rgba(0,0,0,0.02);
        }

        .details-row table {
            margin: 0;
            background: transparent;
        }

        .details-row td {
            border-top: none;
        }

        /* Ensure proper spacing in nested table */
        .details-row .table th,
        .details-row .table td {
            padding: 0.75rem;
        }

        /* Add subtle divider between days */
        .summary-row {
            border-top: 2px solid #eee;
        }

        /* Improved table styles */
        .summary-row {
            position: relative;
        }

        .summary-row:hover .fa-chevron-right {
            color: var(--primary);
            transform: scale(1.1);
        }

        .summary-row .fa-chevron-right {
            transition: all 0.2s ease-in-out;
        }

        .rotate-90 {
            transform: rotate(90deg) !important;
        }

        .details-row {
            background: linear-gradient(to right, var(--primary-content/0.02), transparent);
        }

        /* Improved input styles */
        .input-bordered {
            transition: all 0.2s ease-in-out;
            border-width: 2px;
        }

        .input-bordered:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--primary/0.1);
        }

        /* Highlight animation */
        @keyframes highlight {
            0% { background-color: var(--primary/0.1); }
            100% { background-color: transparent; }
        }

        .highlight {
            animation: highlight 1s ease-out;
        }

        /* Improved button styles */
        .edit-datetime-btn {
            opacity: 0;
            transition: all 0.2s ease-in-out;
        }

        tr:hover .edit-datetime-btn {
            opacity: 1;
        }

        .btn-ghost:hover {
            background-color: var(--primary/0.1);
            color: var(--primary);
        }

        /* Responsive improvements */
        @media (max-width: 640px) {
            .table {
                font-size: 0.875rem;
            }

            .table td {
                padding: 0.75rem 0.5rem;
            }

            .details-row .table td {
                padding: 0.5rem 0.25rem;
            }
        }

        /* Accessibility improvements */
        .summary-row:focus-visible {
            outline: 2px solid var(--primary);
            outline-offset: -2px;
        }

        /* Loading state */
        .loading-overlay {
            position: absolute;
            inset: 0;
            background: var(--base-100/0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(2px);
        }

        /* Tooltip styles */
        [data-tooltip] {
            position: relative;
        }

        [data-tooltip]:hover::before {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            padding: 0.5rem;
            background: var(--base-content);
            color: var(--base-100);
            border-radius: 0.25rem;
            font-size: 0.75rem;
            white-space: nowrap;
            z-index: 10;
        }

        /* Updated Sidebar Styles */
        .sidebar {
            height: 100vh;
            width: 250px;
            position: fixed;
            left: 0;
            top: 0;
            background: var(--base-300);
            transition: transform 0.3s ease;
            z-index: 100;
            border-right: 1px solid var(--base-content/0.1);
        }

        .sidebar.collapsed {
            transform: translateX(-250px);
        }

        .main-content {
            margin-left: 250px;
            transition: margin-left 0.3s ease;
            padding: 1rem;
            padding-top: 4rem;
        }

        .main-content.expanded {
            margin-left: 0;
        }

        .navbar {
            position: fixed;
            top: 0;
            right: 0;
            width: calc(100% - 250px);
            transition: width 0.3s ease, margin-left 0.3s ease;
            background: var(--base-300);
            border-bottom: 1px solid var(--base-content/0.1);
            z-index: 50;
        }

        .navbar.expanded {
            width: 100% !important;
            margin-left: 0;
        }

        /* Remove media query that was auto-collapsing on mobile */
        @media (max-width: 768px) {
            .main-content {
                transition: margin-left 0.3s ease;
            }
        }
    </style>
</head>

<body class="chrono-local-ui" data-attendance-state="{{ $page->state }}" data-attendance-user="{{ $page->userId }}">
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="p-4">
            <nav class="space-y-2">
                <a href="index.php" class="sidebar-link active">
                    <i class="fas fa-home w-6"></i>
                    <span>{{ $page->copy->text('NAV_HOME') }}</span>
                </a>
                <a href="dashboard.php" class="sidebar-link ">
                    <i class="fas fa-tachometer-alt w-6"></i>
                    <span>{{ $page->copy->text('NAV_DASHBOARD') }}</span>
                </a>
                <a href="settings.php" class="sidebar-link ">
                    <i class="fas fa-cog w-6"></i>
                    <span>{{ $page->copy->text('NAV_SETTINGS') }}</span>
                </a>
                @if ($page->role === 'admin')
                    <a href="admin.php" class="sidebar-link ">
                        <i class="fas fa-user-shield w-6"></i>
                        <span>Admin</span>
                    </a>
                @endif
                @if (in_array($page->role, ['supervisor', 'admin'], true))
                    <a href="supervisor.php" class="sidebar-link ">
                        <i class="fas fa-user-tie w-6"></i>
                        <span>{{ $page->copy->text('NAV_SUPERVISOR') }}</span>
                    </a>
                @endif
                <button type="button" class="sidebar-link w-full" onclick="showAboutModal()">
                    <i class="fas fa-info-circle w-6"></i>
                    <span>{{ $page->copy->text('NAV_ABOUT') }}</span>
                </button>
                <form action="logout.php" method="post">
                    @csrf
                    <button type="submit" disabled class="sidebar-link w-full" title="Laravel-Abmeldung noch nicht aktiviert">
                        <i class="fas fa-sign-out-alt w-6"></i>
                        <span>{{ $page->copy->text('NAV_LOGOUT') }}</span>
                    </button>
                </form>

                <a href="adjustments.php" class="sidebar-link "><i class="fas fa-pen-to-square w-6"></i><span>Korrekturanträge</span></a>
                <a href="history.php" class="sidebar-link "><i class="fas fa-history w-6"></i><span>Änderungshistorie</span></a>
                <a href="work_models.php" class="sidebar-link "><i class="fas fa-business-time w-6"></i><span>Arbeitszeitmodelle</span></a>
                @if ($page->role === 'admin')<a href="terminals.php" class="sidebar-link "><i class="fas fa-desktop w-6"></i><span>Praxis-Terminals</span></a>@endif
            </nav>
        </div>
    </div>

    <!-- Top navbar -->
    <div class="navbar fixed top-0 right-0 z-50" style="width: calc(100% - 250px);" id="navbar">
    <div class="navbar-start">
        <button id="sidebar-toggle" type="button" class="btn btn-ghost btn-circle" aria-label="Menü öffnen oder schließen" aria-controls="sidebar" aria-expanded="true">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    <div class="navbar-center"> <!-- Changed from navbar-end -->
        <div class="flex items-center gap-2">
            <img src="assets/kolibri_icon.png" alt="Time Tracking" class="h-8 w-8">
            <span class="text-xl font-semibold whitespace-nowrap">{{ $page->copy->text('TITLE') }}</span> <!-- Added whitespace-nowrap -->
        </div>
    </div>
    <div class="navbar-end">
        <div id="timer" data-attendance-status class="hidden lg:inline-block text-sm mr-3" role="status" aria-live="polite">{{ $page->statusLabel() }}</div>
        <div class="hidden lg:flex items-center gap-2 mr-3" aria-label="Arbeitszeit buchen">
            <button id="startButton" type="button" disabled data-attendance-action="start" class="btn btn-primary btn-sm" @if ($page->state !== 'absent') hidden @endif><i class="fas fa-sign-in-alt"></i><span>Kommen</span></button>
            <button id="pauseStartButton" type="button" disabled data-attendance-action="pause_start" class="btn btn-outline btn-sm" @if ($page->state !== 'working') hidden @endif><i class="fas fa-pause"></i><span>Pause</span></button>
            <button id="pauseEndButton" type="button" disabled data-attendance-action="pause_end" class="btn btn-outline btn-sm" @if ($page->state !== 'paused') hidden @endif><i class="fas fa-play"></i><span>Pause beenden</span></button>
            <button id="endButton" type="button" disabled data-attendance-action="end" class="btn btn-secondary btn-sm" @if ($page->state === 'absent') hidden @endif><i class="fas fa-sign-out-alt"></i><span>Gehen</span></button>
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

    <!-- About Modal -->
    <dialog id="aboutModal" class="modal">
        <div class="modal-box">
            <h3 class="font-bold text-lg">Über Quodara Chrono</h3>
            <p class="py-4">Quodara Chrono ist eine umfassende Zeiterfassungssoftware, die entwickelt wurde, um Ihre Arbeitszeiten effizient zu verwalten und zu verfolgen. Mit benutzerfreundlichen Funktionen und einem intuitiven Design hilft es Ihnen, Ihre Produktivität zu maximieren.</p>
            <div class="modal-action">
                <button class="btn" onclick="closeAboutModal()">Schließen</button>
            </div>
        </div>
    </dialog>

    <p id="attendance-message" class="attendance-feedback text-sm" role="status" aria-live="polite"></p>

    <!-- Main content wrapper -->
    <div id="main-content" class="main-content">
        <aside class="alert alert-info mb-4" role="note">
            Inaktive Laravel-Ansicht: Anmeldung, Buchungen, Abwesenheiten und Abmeldung sind noch nicht verbunden.
            Navigationslinks behalten die bestehenden PHP-Ziele; diese Seiten sind weiterhin Legacy.
        </aside>
        @yield('content')

    <script>
        function showAboutModal() {
            document.getElementById('aboutModal').showModal();
        }

        function closeAboutModal() { document.getElementById('aboutModal').close(); }

        // Theme toggle functionality
        const themeToggle = document.getElementById('theme-toggle');
        const html = document.documentElement;

        // Function to set the theme
        function setTheme(theme) {
            html.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
        }

        // Check for saved theme preference or use the system preference
        const savedTheme = localStorage.getItem('theme');
        const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        const theme = savedTheme || systemTheme;

        // Set initial theme
        setTheme(theme);
        themeToggle.checked = theme === 'dark';

        // Toggle theme when the checkbox is clicked
        themeToggle.addEventListener('change', () => {
            const newTheme = themeToggle.checked ? 'dark' : 'light';
            setTheme(newTheme);
        });

        // Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
            if (!localStorage.getItem('theme')) {
                const newTheme = e.matches ? 'dark' : 'light';
                setTheme(newTheme);
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

        // Sidebar toggle functionality
        const sidebar = document.getElementById('sidebar');
        const navbar = document.getElementById('navbar');
        const mainContent = document.getElementById('main-content');
        const sidebarToggle = document.getElementById('sidebar-toggle');

        function setSidebarCollapsed(collapsed) {
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
            localStorage.setItem('sidebarCollapsed', String(collapsed));
        }

        // Off-canvas links must not remain in the keyboard order on narrow screens.
        setSidebarCollapsed(window.innerWidth <= 768 || localStorage.getItem('sidebarCollapsed') === 'true');
        sidebarToggle.addEventListener('click', toggleSidebar);
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768 && !sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                setSidebarCollapsed(true);
            }
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !sidebar.classList.contains('collapsed') && window.innerWidth <= 768) {
                setSidebarCollapsed(true);
                sidebarToggle.focus();
            }
        });
        window.addEventListener('resize', function() {
            setSidebarCollapsed(window.innerWidth <= 768 || localStorage.getItem('sidebarCollapsed') === 'true');
        });

    </script>



</div>
</body>
</html>
