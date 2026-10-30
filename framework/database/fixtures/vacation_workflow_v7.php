<?php

use App\Vacation\VacationSchema;
use App\LegacyDatabase\MemoryOnly;

// Anonymous canonical reference only; no file, account, credentials or activation.
return static function (PDO $pdo, array $mapping): void {
    MemoryOnly::connection($pdo);
    $build = require __DIR__.'/account_lifecycle_v5.php';
    $build($pdo, $mapping);
    $pdo->beginTransaction();
    try {
        VacationSchema::initializeFreshLocked($pdo, $mapping);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $error;
    }
};
