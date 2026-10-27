<?php

// Explicit CLI-server router only, never a production bootstrap or repo docroot.
if (PHP_SAPI !== 'cli-server' || ! in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
    || ! in_array($_SERVER['HTTP_HOST'] ?? '', ['127.0.0.1:18090', 'localhost:18090'], true)) {
    http_response_code(404);
    exit;
}

use App\Fixtures\LocalWebFixture;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

umask(0077);
$root = dirname(__DIR__, 2);
require dirname(__DIR__).'/vendor/autoload.php';

try {
    $manifest = getenv('CHRONO_LOCAL_WEB_FIXTURE_MANIFEST');
    $fixture = LocalWebFixture::fromManifest(is_string($manifest) ? $manifest : '', $root);
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $assets = [
        '/assets/kolibri_icon.png' => [$root.'/assets/kolibri_icon.png', 'image/png'],
        '/assets/css/main.css' => [$root.'/assets/css/main.css', 'text/css; charset=UTF-8'],
        '/assets/css/app_ui.css' => [$root.'/assets/css/app_ui.css', 'text/css; charset=UTF-8'],
        '/assets/js/attendance.js' => [$root.'/assets/js/attendance.js', 'text/javascript; charset=UTF-8'],
        '/assets/js/main.js' => [$root.'/assets/js/main.js', 'text/javascript; charset=UTF-8'],
        '/assets/js/chrono-local-web.js' => [$root.'/framework/resources/js/chrono-local-web.js', 'text/javascript; charset=UTF-8'],
        '/assets/vendor/chartjs/4.6.1/chart.umd.min.js' => [$root.'/framework/public/assets/vendor/chartjs/4.6.1/chart.umd.min.js', 'text/javascript; charset=UTF-8'],
        '/vendor/fullcalendar/7.1.3/all/global.js' => [$root.'/framework/public/vendor/fullcalendar/7.1.3/all/global.js', 'text/javascript; charset=UTF-8'],
        '/vendor/fullcalendar/7.1.3/themes/classic/global.js' => [$root.'/framework/public/vendor/fullcalendar/7.1.3/themes/classic/global.js', 'text/javascript; charset=UTF-8'],
        '/vendor/fullcalendar/7.1.3/locales/de/global.js' => [$root.'/framework/public/vendor/fullcalendar/7.1.3/locales/de/global.js', 'text/javascript; charset=UTF-8'],
        '/vendor/fullcalendar/7.1.3/locales/zh-tw/global.js' => [$root.'/framework/public/vendor/fullcalendar/7.1.3/locales/zh-tw/global.js', 'text/javascript; charset=UTF-8'],
        '/vendor/fullcalendar/7.1.3/skeleton.css' => [$root.'/framework/public/vendor/fullcalendar/7.1.3/skeleton.css', 'text/css; charset=UTF-8'],
        '/vendor/fullcalendar/7.1.3/themes/classic/theme.css' => [$root.'/framework/public/vendor/fullcalendar/7.1.3/themes/classic/theme.css', 'text/css; charset=UTF-8'],
        '/vendor/fullcalendar/7.1.3/themes/classic/palette.css' => [$root.'/framework/public/vendor/fullcalendar/7.1.3/themes/classic/palette.css', 'text/css; charset=UTF-8'],
        '/assets/js/chrono-calendar.js' => [$root.'/framework/public/assets/js/chrono-calendar.js', 'text/javascript; charset=UTF-8'],
    ];
    if (is_string($path) && isset($assets[$path]) && in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) {
        if (! is_file($assets[$path][0]) || ! is_readable($assets[$path][0])) {
            throw new RuntimeException('A required preview asset is unavailable.');
        }
        header('Content-Type: '.$assets[$path][1]);
        header('X-Content-Type-Options: nosniff');
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if (@readfile($assets[$path][0]) === false) {
                throw new RuntimeException('A required preview asset could not be served.');
            }
        }
        exit;
    }
    if (! in_array($path, ['/', '/index.php', '/attendance.php', '/adjustments.php', '/get_time_records.php', '/save.php', '/work_models.php', '/history.php', '/supervisor.php', '/dashboard.php', '/export_csv.php', '/settings.php', '/admin.php', '/login.php', '/logout.php'], true)) {
        http_response_code(410);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Not available in the synthetic local preview.';
        exit;
    }
    $app = require dirname(__DIR__).'/bootstrap/app.php';
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();
    $fixture->configure($app);
    $request = Request::capture();
    $response = $kernel->handle($request);
    $response->send();
    $kernel->terminate($request, $response);
} catch (Throwable) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Synthetic local preview unavailable.';
}
exit;
