<?php

// Explicit trusted provisioning only. No native APP_SETUP_TOKEN/ENV fallback,
// automatic token generation, public first-admin mode or existing-account reset.
return ['enabled' => false, 'token_sha256' => null];
