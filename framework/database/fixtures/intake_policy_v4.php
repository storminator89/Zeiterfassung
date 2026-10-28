<?php

use App\Accounts\IntakePolicySchema;
use App\LegacyDatabase\MemoryOnly;

// Pure new empty anonymous-memory source, not a file/fixture preparation command.
// It creates no user, credential, directory configuration or runtime admission.
return static function (PDO $pdo, array $mapping): void {
    MemoryOnly::connection($pdo);
    $build = require __DIR__.'/legacy_v2.php';
    $build($pdo, $mapping, false);
    require_once dirname(__DIR__, 3).'/lib/user_audit_v3.php';

    $pdo->beginTransaction();
    try {
        user_audit_v3_apply_locked($pdo);
        IntakePolicySchema::initializeFreshLocked($pdo);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
};
