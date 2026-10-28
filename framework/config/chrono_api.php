<?php

// Trusted operator selection only. This loads/creates no key, database or route.
// Optional NEW-only CLI-produced metadata transport. Base64 is not encryption:
// keep its EnvironmentFile private, never dump config/environment into logs.
$enabled = env('FRAMEWORK_ADMITTED_LEGACY_API_ENABLED', false);
$admission = null;
if ($enabled === true) {
    try {
        $encoded = env('FRAMEWORK_FRESH_API_KEY_ADMISSION');
        if ($encoded !== null) {
            if (! is_string($encoded) || $encoded === '' || strlen($encoded) > 8192) { throw new \LogicException; }
            $json = base64_decode($encoded, true);
            if (! is_string($json) || base64_encode($json) !== $encoded) { throw new \LogicException; }
            $admission = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
            if (! is_array($admission) || $json !== json_encode($admission, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) {
                throw new \LogicException;
            }
        }
    } catch (\Throwable) {
        throw new \LogicException('The private NEW API key metadata transport is not admitted.');
    } finally { unset($encoded, $json); }
}
return [
    'enabled' => $enabled,
    // Existing composition enforces all ten typed fields + the SAME DB profile.
    'existing_key_admission' => $admission,
];
