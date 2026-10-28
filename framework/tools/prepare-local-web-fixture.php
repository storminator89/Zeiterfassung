<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
$ldapCaFile = null;
if ($argc !== 1) {
    if ($argc !== 3 || $argv[1] !== '--ldap-lab' || ! str_starts_with($argv[2], '--ca=')) {
        http_response_code(404);
        exit(1);
    }
    $ldapCaFile = substr($argv[2], 5); // Public CA path only, never a password.
}

// Preparation only: never starts a listener or accepts an existing DB/key path.
use App\Fixtures\LocalWebFixture;
use Illuminate\Contracts\Console\Kernel;

umask(0077);
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    echo json_encode(LocalWebFixture::prepare($app, $ldapCaFile), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    // No native SQL, keys, account fields, exception messages or input paths.
    fwrite(STDERR, 'Synthetic local-web preparation failed ('.$error::class.").\n");
    exit(1);
}
