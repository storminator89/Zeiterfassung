<?php

use App\Accounts\AccountLifecycleSchema;
use App\LegacyDatabase\MemoryOnly;

// NEW anonymous source only. No file, account, key, directory or runtime admission.
return static function (PDO $pdo, array $mapping): void {
    MemoryOnly::connection($pdo);
    $build = require __DIR__.'/intake_policy_v4.php';
    $build($pdo, $mapping);
    $pdo->beginTransaction();
    try {
        AccountLifecycleSchema::initializeFreshLocked($pdo, $mapping);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
};
