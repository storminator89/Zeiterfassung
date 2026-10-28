<?php

namespace App\Api;

use App\LegacyDatabase\RuntimeAdmission;
use JsonSerializable;
use LogicException;
use Throwable;

/** Pure immutable EXISTING-key metadata; physical admission occurs only at read. */
final readonly class ExistingApiKeyAdmission implements JsonSerializable
{
    private function __construct(
        public string $path,
        public int $device,
        public int $inode,
        public int $owner,
        public int $mode,
        public int $parentDevice,
        public int $parentInode,
        public string $sha256,
        public string $databaseProfileSha256,
    ) {}

    /** No file/DB/key access, defaults, path discovery or permission repair. */
    public static function fromConfiguration(#[\SensitiveParameter] array $metadata, #[\SensitiveParameter] RuntimeAdmission $database): self
    {
        try {
            $keys = ['version', 'path', 'device', 'inode', 'owner', 'mode', 'parent_device', 'parent_inode', 'sha256', 'database_profile_sha256'];
            if (count($metadata) !== count($keys) || array_diff($keys, array_keys($metadata)) !== []
                || $metadata['version'] !== 1 || ! is_string($metadata['path']) || $metadata['path'] === ''
                || $metadata['path'][0] !== '/' || str_contains($metadata['path'], "\0")
                || array_intersect(explode('/', substr($metadata['path'], 1)), ['', '.', '..']) !== []
                || basename($metadata['path']) !== 'jwt.key') { throw new LogicException; }
            foreach (['device', 'inode', 'owner', 'mode', 'parent_device', 'parent_inode'] as $key) {
                if (! is_int($metadata[$key]) || $metadata[$key] < 0) { throw new LogicException; }
            }
            if ($metadata['inode'] < 1 || $metadata['parent_inode'] < 1 || ! in_array($metadata['mode'], [0400, 0600], true)) {
                throw new LogicException;
            }
            foreach (['sha256', 'database_profile_sha256'] as $key) {
                if (! is_string($metadata[$key]) || preg_match('/^[a-f0-9]{64}$/D', $metadata[$key]) !== 1) { throw new LogicException; }
            }
            if (! hash_equals(self::databaseProfileDigest($database), $metadata['database_profile_sha256'])) {
                throw new LogicException;
            }
            return new self($metadata['path'], $metadata['device'], $metadata['inode'], $metadata['owner'], $metadata['mode'],
                $metadata['parent_device'], $metadata['parent_inode'], $metadata['sha256'], $metadata['database_profile_sha256']);
        } catch (Throwable) {
            throw new LogicException('Existing API signing key metadata is not admitted.');
        }
    }

    /** Versioned canonical PUBLIC admission metadata, not key bytes or raw manifest JSON. */
    public static function databaseProfileDigest(#[\SensitiveParameter] RuntimeAdmission $database): string
    {
        return hash('sha256', json_encode([
            'version' => 1, 'path' => $database->path, 'device' => $database->device, 'inode' => $database->inode,
            'schema_version' => $database->schemaVersion, 'schema_signature' => $database->schemaSignature,
            'foreign_keys' => $database->foreignKeys, 'journal_mode' => $database->journalMode, 'synchronous' => $database->synchronous,
            'schema_profile' => $database->schemaProfile, 'base_schema_signature' => $database->baseSchemaSignature,
            'user_audit_schema_signature' => $database->userAuditSchemaSignature,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function __debugInfo(): array
    {
        return ['source' => 'explicit existing API signing key metadata'];
    }

    public function jsonSerialize(): array
    {
        return $this->__debugInfo();
    }

    public function __serialize(): array
    {
        throw new LogicException('API signing key admission must not be serialized.');
    }
}
