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
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/de.js"></script>

    <link rel="stylesheet" type="text/css" href="./assets/css/main.css">
    <script src="./assets/js/main.js"></script>
    <script>
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
    </style>
</head>

<body>
    <div class="navbar bg-base-300 fixed top-0 left-0 right-0 z-50">
        <div class="navbar-start">
            <div class="dropdown lg:hidden">
                <label tabindex="0" class="btn btn-ghost">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16" />
                    </svg>
                </label>
                <ul tabindex="0" class="menu menu-compact dropdown-content mt-3 p-2 shadow bg-base-100 rounded-box w-52">
                    <li><a href="index.php"><i class="fas fa-home mr-2"></i><?= NAV_HOME ?></a></li>
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt mr-2"></i><?= NAV_DASHBOARD ?></a></li>
                    <li><a href="settings.php"><i class="fas fa-cog mr-2"></i><?= NAV_SETTINGS ?></a></li>
                    <?php if ($user_role === 'admin') : ?>
                        <li><a href="admin.php"><i class="fas fa-user-shield mr-2"></i>Admin</a></li>
                    <?php endif; ?>
                    <?php if ($user_role === 'supervisor' || $user_role === 'admin') : ?>
                        <li><a href="supervisor.php"><i class="fas fa-user-tie mr-2"></i><?= NAV_SUPERVISOR ?></a></li>
                    <?php endif; ?>
                    <li><a onclick="showAboutModal()"><i class="fas fa-info-circle mr-2"></i><?= NAV_ABOUT ?></a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt mr-2"></i><?= NAV_LOGOUT ?></a></li>
                </ul>
            </div>
            <a class="btn btn-ghost normal-case text-xl" href="index.php">
                <img src="<?= $kolibri_icon ?>" alt="Time Tracking" class="h-8 w-8 mr-2">
                <span class="hidden sm:inline"><?= TITLE ?></span>
            </a>
        </div>
        <div class="navbar-center hidden lg:flex">
            <ul class="menu menu-horizontal px-1">
                <li><a href="index.php"><i class="fas fa-home mr-2"></i><?= NAV_HOME ?></a></li>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt mr-2"></i><?= NAV_DASHBOARD ?></a></li>
            </ul>
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
            <div id="settingsDropdown" class="dropdown dropdown-end">
                <label tabindex="0" class="btn btn-ghost btn-circle">
                    <i class="fas fa-cog"></i>
                </label>
                <ul tabindex="0" class="menu menu-compact dropdown-content mt-3 p-2 shadow bg-base-100 rounded-box w-52">
                    <li><a href="settings.php"><i class="fas fa-cog mr-2"></i><?= NAV_SETTINGS ?></a></li>
                    <?php if ($user_role === 'admin') : ?>
                        <li><a href="admin.php"><i class="fas fa-user-shield mr-2"></i>Admin</a></li>
                    <?php endif; ?>
                    <?php if ($user_role === 'supervisor' || $user_role === 'admin') : ?>
                        <li><a href="supervisor.php"><i class="fas fa-user-tie mr-2"></i><?= NAV_SUPERVISOR ?></a></li>
                    <?php endif; ?>
                    <li><a onclick="showAboutModal()"><i class="fas fa-info-circle mr-2"></i><?= NAV_ABOUT ?></a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt mr-2"></i><?= NAV_LOGOUT ?></a></li>
                </ul>
            </div>
        </div>
    </div>


    <!-- About Modal -->
    <dialog id="aboutModal" class="modal">
        <form method="dialog" class="modal-box">
            <h3 class="font-bold text-lg"><?= NAV_ABOUT ?></h3>
            <p class="py-4"><?= ABOUT_TOOL_TEXT ?></p>
            <div class="modal-action">
                <button class="btn">Close</button>
            </div>
        </form>
    </dialog>

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
            if (window.innerWidth < 1024) { // 1024px ist der Standardwert für lg in Tailwind
                settingsDropdown.style.display = 'none';
            } else {
                settingsDropdown.style.display = 'block';
            }
        }

        // Führe die Funktion beim Laden der Seite aus
        updateSettingsVisibility();

        // Führe die Funktion aus, wenn die Fenstergröße geändert wird
        window.addEventListener('resize', updateSettingsVisibility);
    </script>
</body>

</html>