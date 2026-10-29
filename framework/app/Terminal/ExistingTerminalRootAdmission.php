<?php

namespace App\Terminal;

use App\Api\ExistingApiKeyAdmission;

use App\LegacyDatabase\RuntimeAdmission;
use JsonSerializable;
use LogicException;
use Throwable;

/** Pure immutable EXISTING-key metadata; physical admission occurs only at read. */
final readonly class ExistingTerminalRootAdmission implements JsonSerializable
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
    public static function fromConfiguration(#[\SensitiveParameter] array $metadata, #[\SensitiveParameter] RuntimeAdmission $database, string $purpose): self
    {
        try {
            $basename = match ($purpose) {
                'device' => 'terminal-devices.key', 'card' => 'terminal-cards.key',
                default => throw new LogicException,
            };
            $keys = ['version', 'path', 'device', 'inode', 'owner', 'mode', 'parent_device', 'parent_inode', 'sha256', 'database_profile_sha256'];
            if (count($metadata) !== count($keys) || array_diff($keys, array_keys($metadata)) !== []
                || $metadata['version'] !== 1 || ! is_string($metadata['path']) || $metadata['path'] === ''
                || $metadata['path'][0] !== '/' || str_contains($metadata['path'], "\0")
                || array_intersect(explode('/', substr($metadata['path'], 1)), ['', '.', '..']) !== []
                || basename($metadata['path']) !== $basename) { throw new LogicException; }
            foreach (['device', 'inode', 'owner', 'mode', 'parent_device', 'parent_inode'] as $key) {
                if (! is_int($metadata[$key]) || $metadata[$key] < 0) { throw new LogicException; }
            }
            if ($metadata['inode'] < 1 || $metadata['parent_inode'] < 1 || ! in_array($metadata['mode'], [0400, 0600], true)) {
                throw new LogicException;
            }
            foreach (['sha256', 'database_profile_sha256'] as $key) {
                if (! is_string($metadata[$key]) || preg_match('/^[a-f0-9]{64}$/D', $metadata[$key]) !== 1) { throw new LogicException; }
            }
            if (! hash_equals(ExistingApiKeyAdmission::databaseProfileDigest($database), $metadata['database_profile_sha256'])) {
                throw new LogicException;
            }
            return new self($metadata['path'], $metadata['device'], $metadata['inode'], $metadata['owner'], $metadata['mode'],
                $metadata['parent_device'], $metadata['parent_inode'], $metadata['sha256'], $metadata['database_profile_sha256']);
        } catch (Throwable) {
            throw new LogicException('Existing terminal root metadata is not admitted.');
        }
    }

    public function __debugInfo(): array
    {
        return ['source' => 'explicit existing terminal root metadata'];
    }

    public function jsonSerialize(): array
    {
        return $this->__debugInfo();
    }

    public function __serialize(): array
    {
        throw new LogicException('terminal root admission must not be serialized.');
    }
}
