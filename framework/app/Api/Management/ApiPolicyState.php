<?php

namespace App\Api\Management;

use UnexpectedValueException;

/** Nonsecret effective policy. Never a signing key, token, session or user authority. */
final readonly class ApiPolicyState
{
    public function __construct(public bool $enabled, public bool $keyPrepared, public int $revision,
        public ?int $updatedBy = null, public int $updatedAt = 0)
    {
        if ($revision < 0 || $updatedAt < 0 || ($revision === 0 ? $updatedBy !== null || $updatedAt !== 0
            : $updatedBy === null || $updatedBy < 1) || ($enabled && ! $keyPrepared)) {
            throw new UnexpectedValueException('Invalid API operating policy.');
        }
    }

    public function values(): array
    {
        return ['version' => 1, 'enabled' => $this->enabled, 'key_prepared' => $this->keyPrepared,
            'revision' => $this->revision, 'updated_by' => $this->updatedBy, 'updated_at' => $this->updatedAt];
    }

    public static function fromJson(string $json): self
    {
        try { $value = json_decode($json, true, 8, JSON_THROW_ON_ERROR); }
        catch (\Throwable) { throw new UnexpectedValueException('Invalid API operating policy.'); }
        if (! is_array($value) || array_keys($value) !== ['version','enabled','key_prepared','revision','updated_by','updated_at']
            || $value['version'] !== 1 || ! is_bool($value['enabled']) || ! is_bool($value['key_prepared'])
            || ! is_int($value['revision']) || ! is_int($value['updated_at'])
            || ($value['updated_by'] !== null && ! is_int($value['updated_by']))
            || $json !== json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) {
            throw new UnexpectedValueException('Invalid API operating policy.');
        }
        return new self($value['enabled'], $value['key_prepared'], $value['revision'], $value['updated_by'], $value['updated_at']);
    }
}
