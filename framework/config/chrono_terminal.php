<?php

// Separate trusted opt-ins, both false by default. Metadata contains no key bytes.
// Base64 is transport, not encryption: keep operator EnvironmentFiles private.
$keysEnabled = env('FRAMEWORK_EXISTING_TERMINAL_KEYS_ENABLED', false);
$admissions = null;
if ($keysEnabled === true) {
    try {
        $encoded = env('FRAMEWORK_EXISTING_TERMINAL_KEY_ADMISSIONS');
        if ($encoded !== null) {
            if (! is_string($encoded) || $encoded === '' || strlen($encoded) > 16384) { throw new \LogicException; }
            $json = base64_decode($encoded, true);
            if (! is_string($json) || base64_encode($json) !== $encoded) { throw new \LogicException; }
            $admissions = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
            if (! is_array($admissions) || $json !== json_encode($admissions, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) {
                throw new \LogicException;
            }
        }
    } catch (\Throwable) {
        throw new \LogicException('The existing terminal root metadata transport is not admitted.');
    } finally { unset($encoded, $json); }
}
return [
    'http_enabled' => env('FRAMEWORK_CHRONO_TERMINAL_HTTP_ENABLED', false),
    'existing_keys_enabled' => $keysEnabled,
    'existing_key_admissions' => $admissions,
];
