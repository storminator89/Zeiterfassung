<?php

// One coarse, synthetic static render. Never starts HTTP or reads a database.
use App\View\ExistingUi\AttendancePage;
use App\View\ExistingUi\Copy;
use App\View\ExistingUi\LoginPage;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$config = $app->make('config');
$database = $app->make('db');
$nativeSession = $GLOBALS['_SESSION'] ?? null;

if ($argc !== 1 || $config->has('database.connections.chrono') || $database->getConnections() !== []) {
    throw new LogicException('Static preview requires an inactive framework without database connections or arguments.');
}

$output = sys_get_temp_dir().'/chrono-existing-ui-'.bin2hex(random_bytes(8));
mkdir($output, 0700);
mkdir($output.'/compiled', 0700);
mkdir($output.'/assets', 0700);
mkdir($output.'/assets/css', 0700);
$config->set('view.compiled', $output.'/compiled');
$config->set('app.key', 'base64:'.base64_encode(random_bytes(32))); // Process-only, never saved.
$config->set('session.driver', 'array');
$app->make('session')->put('_token', 'synthetic-static-preview-not-a-session-credential');

$status = ['success' => true, 'records' => [], 'state' => 'paused', 'record_id' => 1];
$pages = [
    'login-de' => ['chrono.login', new LoginPage(new Copy('de'))],
    'login-en' => ['chrono.login', new LoginPage(new Copy('en'), error: 'Synthetic preview error <not markup>')],
    'attendance-admin-de' => ['chrono.attendance', new AttendancePage(
        new Copy('de'), 1, 'Vorschau <synthetisch>', 'admin', $status,
        workingDaysThisMonth: 21, currentMonthName: 'Oktober', totalOverHoursFormatted: '02:30',
        positiveOvertime: true, dailyHoursFormatted: '08:00',
        holidaysThisWeek: [['date' => '31.10.2026', 'name' => 'Synthetischer Feiertag']],
    )],
    'attendance-user-en' => ['chrono.attendance', new AttendancePage(
        new Copy('en'), 2, 'Synthetic preview', 'user', array_replace($status, ['state' => 'working']),
    )],
];

foreach ($pages as $name => [$view, $page]) {
    file_put_contents($output.'/'.$name.'.html', $app->make('view')->make($view, ['page' => $page])->render());
}

foreach (['kolibri_icon.png', 'css/main.css', 'css/app_ui.css'] as $asset) {
    copy(dirname(__DIR__, 2).'/assets/'.$asset, $output.'/assets/'.$asset);
}

if ($database->getConnections() !== [] || ($GLOBALS['_SESSION'] ?? null) !== $nativeSession) {
    throw new RuntimeException('Static preview unexpectedly changed database or native session state.');
}

echo json_encode([
    'result' => 'rendered', 'directory' => $output, 'pages' => array_keys($pages),
    'database_connections' => 0, 'native_session_unchanged' => true,
    'http_started' => false, 'forms' => 'disabled',
    'assets' => 'unchanged copies of existing logo/main.css/app_ui.css; existing remote styles/fonts remain referenced',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
