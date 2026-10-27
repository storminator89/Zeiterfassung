<?php

use App\Terminal\LegacyTerminalHttpIngress;
use Illuminate\Support\Facades\Route;

// Loaded only by the explicit HTTP opt-in. No web/session/CSRF or API guard group.
Route::post('/terminal_api.php', LegacyTerminalHttpIngress::class);
