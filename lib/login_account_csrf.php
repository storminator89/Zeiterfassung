<?php

/** After successful authentication only. No session opener, credential/token generation or authority change. */
function native_login_account_csrf(array &$session, int $authenticatedUser): void
{
    if ($authenticatedUser < 1) {
        throw new LogicException('A successfully authenticated positive account identifier is required.');
    }
    if (!array_key_exists('user_id', $session)) {
        return; // First sign-in keeps the already validated anonymous form token.
    }
    $previous = $session['user_id'];
    if ((is_int($previous) && $previous === $authenticatedUser)
        || (is_string($previous) && $previous === (string)$authenticatedUser)) {
        return; // Exact same account; never coerce floats/rounded or malformed IDs.
    }
    // security.php creates the next token on the redirected request. An old
    // account's tabs/forms must not inherit authority merely because their
    // CSRF token survived session_regenerate_id(). Keep all other state intact.
    unset($session['csrf_token']);
}
