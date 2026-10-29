<?php

namespace App\Api\Management;

use InvalidArgumentException;

/** Explicit action and CAS revision only. Raw current password is never retained. */
final readonly class ApiPolicyChange
{
    public const FIELDS = ['api_management','api_action','api_revision','api_confirm','api_create_key','current_password'];
    private function __construct(public bool $enabled, public bool $allowNewKey, public int $expectedRevision) {}

    public static function fromForm(#[\SensitiveParameter] array $form): self
    {
        if (array_diff(array_keys($form), [...self::FIELDS, '_token', 'csrf_token']) !== []
            || ($form['api_management'] ?? null) !== '1' || ($form['api_confirm'] ?? null) !== '1'
            || ! in_array($form['api_action'] ?? null, ['enable','disable'], true)
            || (array_key_exists('api_create_key', $form) && $form['api_create_key'] !== '1')
            || ! is_string($form['current_password'] ?? null) || $form['current_password'] === ''
            || strlen($form['current_password']) > 4096 || $form['current_password'] === 'defaultpassword') {
            throw new InvalidArgumentException('API-Aktion, Bestätigung und aktuelles Passwort prüfen.');
        }
        $revision = $form['api_revision'] ?? null;
        $max = (string) PHP_INT_MAX;
        if (! is_string($revision) || preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $revision) !== 1
            || strlen($revision) > strlen($max) || (strlen($revision) === strlen($max) && strcmp($revision, $max) > 0)) {
            throw new InvalidArgumentException('API-Stand neu laden und erneut bewusst wählen.');
        }
        if ($form['api_action'] === 'disable' && isset($form['api_create_key'])) {
            throw new InvalidArgumentException('Ausschalten legt keinen Schlüssel an.');
        }
        return new self($form['api_action'] === 'enable', isset($form['api_create_key']), (int) $revision);
    }
}
