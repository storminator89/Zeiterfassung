<?php

// Trusted operator selection only. This loads/creates no key, database or route.
// Supply an exact typed ExistingApiKeyAdmission metadata array separately;
// never key bytes, a request-selected path or automatically adopted metadata.
return [
    'enabled' => env('FRAMEWORK_ADMITTED_LEGACY_API_ENABLED', false),
    'existing_key_admission' => null,
];
