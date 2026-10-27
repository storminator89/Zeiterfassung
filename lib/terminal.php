<?php
require_once __DIR__.'/terminal_core.php';

// Native compatibility wrappers retain their existing lazy key-loader behavior.
// Framework callers must not load security.php/config.php or use this orchestrator.
function terminal_key(array $device): string { return terminal_key_from_source($device,static fn(): string => app_secret('terminal-devices')); }
function terminal_card(string $card): string { return terminal_card_from_source($card,static fn(): string => app_secret('terminal-cards')); }
function terminal_auth(PDO $db, string $body): array {
    return terminal_auth_core($db,$body,$_SERVER,static fn(): string => app_secret('terminal-devices'));
}
function terminal_handle(PDO $db, array $input, string $body): array {
    attendance_require_enabled($db);
    if (($input['op'] ?? '')==='pair') {
        terminal_limit($db,'pair:'.($_SERVER['REMOTE_ADDR']??''),20);
        $db->exec('BEGIN IMMEDIATE');
        try {
            $device=terminal_pair_locked($db,$input);
            $db->exec('COMMIT');
            return terminal_pair_response($device,static fn(): string => app_secret('terminal-devices'));
        } catch (Throwable $e) { $db->exec('ROLLBACK'); throw $e; }
    }
    $device=terminal_auth($db,$body);
    $op=$input['op']??'';
    if ($op==='identify') {
        return terminal_identify_core($db,$device,$input,static fn(): string => app_secret('terminal-cards'));
    }
    $db->exec('BEGIN IMMEDIATE');
    try {
        $r=terminal_dispatch_locked($db,$device,$input);
        $db->exec('COMMIT'); return $r;
    } catch (Throwable $e) { $db->exec('ROLLBACK'); throw $e; }
}
