<?php
// Local PHP server only. Apache uses .htaccess instead.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$target = realpath(__DIR__ . $path);
$allowedPages = ['index.php','login.php','logout.php','register.php','admin.php','settings.php','supervisor.php','dashboard.php','save.php','delete.php','download.php','import.php','export_csv.php','get_user.php','get_time_records.php','get_latest_record.php','api.php','apidoc.html','swagger.json'];
if (preg_match('#^/api\.php(?:/|$)#', $path)) { $_SERVER['SCRIPT_NAME'] = '/api.php'; require __DIR__ . '/api.php'; return true; }
if ($path === '/') { require __DIR__ . '/index.php'; return true; }
if ($target && str_starts_with($target, __DIR__ . '/') && is_file($target)) {
    if (in_array(ltrim($path, '/'), $allowedPages, true)) { return false; }
    if (preg_match('#^/(assets|swagger-ui)/#', $path) && preg_match('/\.(css|js|png|jpg|jpeg|svg|ico|woff2?|ttf)$/i', $path)) { return false; }
}
http_response_code(404);
echo 'Not found';
