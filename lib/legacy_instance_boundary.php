<?php
// Definitions only. Fixed Laravel instance names, never a database/profile opener.
function assert_native_laravel_instance_boundary(string $canonicalDataDirectory): void {
    if (!is_readable($canonicalDataDirectory)
        || (PHP_OS_FAMILY !== 'Windows' && !is_executable($canonicalDataDirectory))) {
        throw new RuntimeException('Database profile unavailable.');
    }
    // Inspect names in the allowed directory, not resolved artifact paths:
    // lstat() may return false for an existing symlink whose target lies
    // outside open_basedir. Complete/partial files, directories and dangling
    // links all reserve this namespace. Never open an artifact or its target.
    $entries = @scandir($canonicalDataDirectory, SCANDIR_SORT_NONE);
    if (!is_array($entries)) {
        throw new RuntimeException('Database profile unavailable.');
    }
    foreach (['application.sqlite', 'runtime-admission.json'] as $artifact) {
        if (in_array($artifact, $entries, true)) {
            throw new RuntimeException('Database profile unavailable.');
        }
    }
}
