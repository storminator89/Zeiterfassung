<?php

namespace App\Auth;

use InvalidArgumentException;

/** Shared first-installation fields only; never authorizes an operator or a write. */
final class FirstAdminSetupFields
{
    /** @return array{string, string, string} Chosen username, email and unnormalized password. */
    public static function validate(#[\SensitiveParameter] array $form): array
    {
        if (array_diff(array_keys($form), ['username', 'email', 'password', 'password_confirmation']) !== []) {
            throw new InvalidArgumentException('Invalid setup fields.');
        }
        foreach (['username', 'email', 'password', 'password_confirmation'] as $field) {
            if (! is_string($form[$field] ?? null)) {
                throw new InvalidArgumentException('Invalid setup fields.');
            }
        }
        $username = trim($form['username']);
        $email = trim($form['email']);
        $password = $form['password']; // Never trim or normalize a chosen password.
        if ($username === '' || strlen($username) > 100 || ! mb_check_encoding($username, 'UTF-8')
            || preg_match('/[\x00-\x1f\x7f]/', $username)
            || strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || strlen($password) < 12 || strlen($password) > 72 || $password === 'defaultpassword'
            || preg_match('/[\x00-\x1f\x7f]/', $password)
            || strlen($form['password_confirmation']) > 72
            || ! hash_equals($password, $form['password_confirmation'])) {
            throw new InvalidArgumentException('Invalid setup fields.');
        }

        return [$username, $email, $password];
    }
}
