<?php

// Server capability only, NOT an effective API enable bit or key source.
// Default root NEW-V5 Web composition opts in; older compositions remain off.
return ['enabled' => env('FRAMEWORK_API_UI_MANAGEMENT_ENABLED', false)];
