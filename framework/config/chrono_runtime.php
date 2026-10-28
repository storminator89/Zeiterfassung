<?php

// Trusted deployment metadata only. Both switches remain closed by default;
// loading/caching this file never opens a database or adopts an existing file.
// Record this manifest only after the separately reviewed offline profile admission.
$admission = [
    'path' => env('FRAMEWORK_CHRONO_DB_PATH'),
    'device' => env('FRAMEWORK_CHRONO_DB_DEVICE'),
    'inode' => env('FRAMEWORK_CHRONO_DB_INODE'),
    'schema_version' => env('FRAMEWORK_CHRONO_DB_SCHEMA_VERSION'),
    'schema_signature' => env('FRAMEWORK_CHRONO_DB_SCHEMA_SIGNATURE'),
    'foreign_keys' => env('FRAMEWORK_CHRONO_DB_FOREIGN_KEYS'),
    'journal_mode' => env('FRAMEWORK_CHRONO_DB_JOURNAL_MODE'),
    'synchronous' => env('FRAMEWORK_CHRONO_DB_SYNCHRONOUS'),
];
// A private sentinel distinguishes absent settings from explicitly configured null.
$absent = new \stdClass();
$profile = env('FRAMEWORK_CHRONO_DB_SCHEMA_PROFILE', $absent);
$baseSignature = env('FRAMEWORK_CHRONO_DB_BASE_SCHEMA_SIGNATURE', $absent);
if ($profile !== $absent || $baseSignature !== $absent) {
    $admission['schema_profile'] = $profile === $absent ? null : $profile;
    $admission['base_schema_signature'] = $baseSignature === $absent ? null : $baseSignature;
}
$userAuditSignature = env('FRAMEWORK_CHRONO_DB_USER_AUDIT_SCHEMA_SIGNATURE', $absent);
if ($userAuditSignature !== $absent) {
    // V4/V5 only: an extra/missing/null field on other profiles fails pure validation.
    $admission['user_audit_schema_signature'] = $userAuditSignature;
}

$intakePolicySignature = env('FRAMEWORK_CHRONO_DB_INTAKE_POLICY_SCHEMA_SIGNATURE', $absent);
if ($intakePolicySignature !== $absent) {
    // V5 only; the earlier V4 signature is an explicit separate predecessor.
    $admission['intake_policy_schema_signature'] = $intakePolicySignature;
}

return [
    'read_enabled' => env('FRAMEWORK_CHRONO_READ_ENABLED', false),
    'write_enabled' => env('FRAMEWORK_CHRONO_WRITE_ENABLED', false),
    'admission' => $admission,
];
