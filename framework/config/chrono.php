<?php

// Handoff metadata only. The foundation never opens, creates or reads these
// legacy files. The data/auth packages must validate private absolute paths.
$dataDir = env('APP_DATA_DIR');

return [
    'data_dir' => $dataDir,
    'database_path' => env('CHRONO_DB_PATH', $dataDir ? $dataDir.'/timetracking.sqlite' : null),
    'jwt_key_path' => env('CHRONO_JWT_KEY_PATH', $dataDir ? $dataDir.'/jwt.key' : null),
    'encryption_key_path' => env('CHRONO_ENCRYPTION_KEY_PATH', $dataDir ? $dataDir.'/encryption.key' : null),
];
