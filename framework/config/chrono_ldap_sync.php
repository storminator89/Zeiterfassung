<?php

// Technical HTTP flag is set only by the explicit registrar, never request data.
// Fresh Sync metadata is a separate trusted operator profile, NOT LDAP login settings.
// The operator must supply complete reviewed existing-file/DB-bound metadata before opt-in;
// no discovery, ENV key bytes, default target/CA, root creation or file access here.
return ['http_enabled' => false, 'fresh' => ['enabled' => false]];
