<?php
session_start();

$theme_mode = $theme_mode ?? ($_COOKIE['theme'] ?? 'light');

if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
}

$lang = $_SESSION['lang'] ?? 'de';
require_once "languages/$lang.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once 'functions.php';

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

$conn = new PDO("sqlite:assets/db/timetracking.sqlite");

$kolibri_icon = 'assets/kolibri_icon.png';
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" data-theme="auto">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= TITLE ?></title>

    <link rel="icon" href="assets/kolibri_icon.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@400;600&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/locale/de.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/de.js"></script>
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Shepherd.js für die Tour -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/shepherd.js@10.0.1/dist/css/shepherd.css"/>
    <script src="https://cdn.jsdelivr.net/npm/shepherd.js@10.0.1/dist/js/shepherd.min.js"></script>

    <link rel="stylesheet" type="text/css" href="./assets/css/main.css">
    <script src="./assets/js/main.js"></script>
    <script>
        // Add this line before the tailwind config
        const currentLang = '<?= $lang ?>';

        tailwind.config = {
            darkMode: 'media',
            daisyui: {
                themes: ["light", "dark"],
            },
        }
    </script>
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

<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="p-4">
            <nav class="space-y-2">
                <a href="index.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">
                    <i class="fas fa-home w-6"></i>
                    <span><?= NAV_HOME ?></span>
                </a>
                <a href="dashboard.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fas fa-tachometer-alt w-6"></i>
                    <span><?= NAV_DASHBOARD ?></span>
                </a>
                <a href="settings.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : '' ?>">
                    <i class="fas fa-cog w-6"></i>
                    <span><?= NAV_SETTINGS ?></span>
                </a>
                <?php if ($user_role === 'admin') : ?>
                    <a href="admin.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'admin.php' ? 'active' : '' ?>">
                        <i class="fas fa-user-shield w-6"></i>
                        <span>Admin</span>
                    </a>
                <?php endif; ?>
                <?php if ($user_role === 'supervisor' || $user_role === 'admin') : ?>
                    <a href="supervisor.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'supervisor.php' ? 'active' : '' ?>">
                        <i class="fas fa-user-tie w-6"></i>
                        <span><?= NAV_SUPERVISOR ?></span>
                    </a>
                <?php endif; ?>
                <a href="#" class="sidebar-link" onclick="showAboutModal()">
                    <i class="fas fa-info-circle w-6"></i>
                    <span><?= NAV_ABOUT ?></span>
                </a>
                <a href="logout.php" class="sidebar-link">
                    <i class="fas fa-sign-out-alt w-6"></i>
                    <span><?= NAV_LOGOUT ?></span>
                </a>
            </nav>
        </div>
    </div>

    <!-- Top navbar -->
    <div class="navbar fixed top-0 right-0 z-50" style="width: calc(100% - 250px);" id="navbar">
    <div class="navbar-start">
        <button id="sidebar-toggle" class="btn btn-ghost btn-circle">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    <div class="navbar-center"> <!-- Changed from navbar-end -->
        <div class="flex items-center gap-2">
            <img src="<?= $kolibri_icon ?>" alt="Time Tracking" class="h-8 w-8">
            <span class="text-xl font-semibold whitespace-nowrap"><?= TITLE ?></span> <!-- Added whitespace-nowrap -->
        </div>
    </div>
    <div class="navbar-end">
        <div id="timer" class="mr-4 hidden lg:inline-block <?php echo $activeSession ? '' : 'hidden'; ?>">00:00:00</div>
        <div class="hidden lg:block">
            <button id="startButton" class="btn btn-primary btn-sm mr-2">
                <i class="fas fa-sign-in-alt mr-2"></i><span>Kommen</span>
            </button>
            <button id="endButton" class="btn btn-secondary btn-sm mr-4" style="display: none;">
                <i class="fas fa-sign-out-alt mr-2"></i><span>Gehen</span>
            </button>
        </div>
        <label class="swap swap-rotate mr-4">
            <input type="checkbox" id="theme-toggle" />
            <svg class="swap-on fill-current w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M5.64,17l-.71.71a1,1,0,0,0,0,1.41,1,1,0,0,0,1.41,0l.71-.71A1,1,0,0,0,5.64,17ZM5,12a1,1,0,0,0-1-1H3a1,1,0,0,0,0,2H4A1,1,0,0,0,5,12Zm7-7a1,1,0,0,0,1-1V3a1,1,0,0,0-2,0V4A1,1,0,0,0,12,5ZM5.64,7.05a1,1,0,0,0,.7.29,1,1,0,0,0,.71-.29,1,1,0,0,0,0-1.41l-.71-.71A1,1,0,0,0,4.93,6.34Zm12,.29a1,1,0,0,0,.7-.29l.71-.71a1,1,0,1,0-1.41-1.41L17,5.64a1,1,0,0,0,0,1.41A1,1,0,0,0,17.66,7.34ZM21,11H20a1,1,0,0,0,0,2h1a1,1,0,0,0,0-2Zm-9,8a1,1,0,0,0-1,1v1a1,1,0,0,0,2,0V20A1,1,0,0,0,12,19ZM18.36,17A1,1,0,0,0,17,18.36l.71.71a1,1,0,0,0,1.41,0,1,1,0,0,0,0-1.41ZM12,6.5A5.5,5.5,0,1,0,17.5,12,5.51,5.51,0,0,0,12,6.5Zm0,9A3.5,3.5,0,1,1,15.5,12,3.5,3.5,0,0,1,12,15.5Z" />
            </svg>
            <svg class="swap-off fill-current w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M21.64,13a1,1,0,0,0-1.05-.14,8.05,8.05,0,0,1-3.37.73A8.15,8.15,0,0,1,9.08,5.49a8.59,8.59,0,0,1,.25-2A1,1,0,0,0,8,2.36,10.14,10.14,0,1,0,22,14.05,1,1,0,0,0,21.64,13Zm-9.5,6.69A8.14,8.14,0,0,1,7.08,5.22v.27A10.15,10.15,0,0,0,17.22,15.63a9.79,9.79,0,0,0,2.1-.22A8.11,8.11,0,0,1,12.14,19.73Z" />
            </svg>
        </label>
        <button id="tourButton" class="btn btn-ghost btn-circle" title="<?= TOUR_START ?>">
            <i class="fas fa-question-circle"></i>
        </button>
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

    <!-- Main content wrapper -->
    <div id="main-content" class="main-content">
        <!-- Your existing content goes here -->

    <script>
        // Add this to your existing JavaScript
        document.getElementById('sidebar-toggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebar-toggle');
            
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });

        // Function to show the About modal
        function showAboutModal() {
            document.getElementById('aboutModal').showModal();
        }

        // Function to close the About modal
        function closeAboutModal() {
            document.getElementById('aboutModal').close();
        }

        // ... your existing JavaScript ...
    </script>

    <script>
        function showAboutModal() {
            document.getElementById('aboutModal').showModal();
        }

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

        // Delete row functionality
        document.querySelectorAll('.deleteRow').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.dataset.id;
                Swal.fire({
                    title: 'Sind Sie sicher?',
                    text: "Dieser Eintrag wird unwiderruflich gelöscht!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Ja, löschen!',
                    cancelButtonText: 'Abbrechen'
                }).then((result) => {
                    if (result.isConfirmed) {
                        fetch('save.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `delete=true&id=${id}`,
                        })
                        .then(response => response.text())
                        .then(data => {
                            if (data === "Successfully deleted") {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Gelöscht!',
                                    text: 'Der Eintrag wurde erfolgreich gelöscht.',
                                    showConfirmButton: false,
                                    timer: 1500
                                });
                                updateTimeRecordsTable();
                                updateLatestTimeRecord();
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Fehler',
                                    text: data
                                });
                            }
                        })
                        .catch((error) => {
                            console.error('Error:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Fehler',
                                text: 'Fehler beim Löschen des Eintrags'
                            });
                        });
                    }
                });
            });
        });

        // Sidebar toggle functionality
        const sidebar = document.getElementById('sidebar');
        const navbar = document.getElementById('navbar');
        const mainContent = document.getElementById('main-content');
        const sidebarToggle = document.getElementById('sidebar-toggle');

        function toggleSidebar() {
            sidebar.classList.toggle('collapsed');
            navbar.classList.toggle('expanded');
            mainContent.classList.toggle('expanded');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        }

        // Initialize sidebar state
        const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (sidebarCollapsed) {
            sidebar.classList.add('collapsed');
            navbar.classList.add('expanded');
            mainContent.classList.add('expanded');
        }

        // Add click event for toggle button
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', toggleSidebar);
        }

        // Handle clicks outside sidebar on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                    sidebar.classList.add('collapsed');
                    navbar.classList.add('expanded');
                    mainContent.classList.add('expanded');
                }
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth <= 768) {
                sidebar.classList.add('collapsed');
                navbar.classList.add('expanded');
                mainContent.classList.add('expanded');
            } else {
                if (!localStorage.getItem('sidebarCollapsed')) {
                    sidebar.classList.remove('collapsed');
                    navbar.classList.remove('expanded');
                    mainContent.classList.remove('expanded');
                }
            }
        });
    </script>   

    <script src="assets/js/tour.js"></script>

</body>

</html>