<?php

return [
    // No worker or external service. No jobs are shipped by the foundation.
    'default' => 'sync',
    'connections' => ['sync' => ['driver' => 'sync']],
    'failed' => ['driver' => 'null'],
];
