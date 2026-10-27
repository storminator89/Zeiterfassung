<?php
// Isolated locale proof: no application bootstrap, session, database or HTTP.
require_once __DIR__ . '/../lib/language.php';

function expect_language(string $name, $stored, $accept, string $expected): void {
    $actual = initial_language($stored, $accept);
    if ($actual !== $expected) {
        throw new RuntimeException($name . ': expected ' . $expected . ', got ' . $actual);
    }
    echo "PASS $name\n";
}

expect_language('fresh English browser preference', null, 'en-GB,en;q=0.9,de;q=0.8', 'en');
expect_language('case-insensitive browser language', null, 'EN-US,en;q=0.9', 'en');
expect_language('stored manual German wins over English browser', 'de', 'en-US', 'de');
expect_language('stored English wins over German browser', 'en', 'de-DE', 'en');
expect_language('existing explicit Chinese selection retained', 'zh', 'en-US', 'zh');
expect_language('missing browser language defaults to German', null, null, 'de');
expect_language('unsupported browser language defaults to German', null, 'fr-FR,en;q=0.9', 'de');
expect_language('Chinese is not newly auto-detected', null, 'zh-CN', 'de');
expect_language('invalid stored path is rejected', '../config', 'en-US', 'de');
expect_language('invalid browser path is rejected', null, '../config', 'de');
expect_language('non-string stored language is rejected', ['en'], 'en-US', 'de');
expect_language('non-string browser language is rejected', null, ['en'], 'de');

// Render a small fragment using the real English dictionary, not the login app.
$lang = initial_language(null, 'en-GB,en;q=0.9');
require __DIR__ . "/../languages/$lang.php";
$fragment = '<html lang="' . $lang . '"><label>' . USERNAME_LABEL . '</label><label>' . PASSWORD_LABEL . '</label></html>';
if ($fragment !== '<html lang="en"><label>Username</label><label>Password</label></html>') {
    throw new RuntimeException('fresh English dictionary fragment did not render as expected');
}
echo "PASS fresh English dictionary fragment\n";
