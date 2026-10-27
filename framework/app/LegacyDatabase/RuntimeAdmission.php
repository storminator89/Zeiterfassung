<?php

namespace App\LegacyDatabase;

use LogicException;
use PDO;
use Pdo\Sqlite;

/** Trusted operator manifest, not a request DTO or automatic admission claim. */
final readonly class RuntimeAdmission
{
    private function __construct(
        public string $path,
        public int $device,
        public int $inode,
        public int $schemaVersion,
        public string $schemaSignature,
        public int $foreignKeys,
        public string $journalMode,
        public int $synchronous,
    ) {}

    // Pure configuration validation: no stat, file read, PDO or source bootstrap.
    public static function fromConfiguration(array $manifest): self
    {
        $keys = ['path', 'device', 'inode', 'schema_version', 'schema_signature', 'foreign_keys', 'journal_mode', 'synchronous'];
        if (count($manifest) !== count($keys) || array_diff($keys, array_keys($manifest)) !== []) {
            throw new LogicException('A complete explicit runtime database admission manifest is required.');
        }
        $path = $manifest['path'];
        $signature = $manifest['schema_signature'];
        if (! is_string($path) || $path === '' || $path[0] !== '/' || str_contains($path, "\0")
            || array_intersect(explode('/', substr($path, 1)), ['', '.', '..']) !== []
            || ! is_string($signature) || ! preg_match('/^[a-f0-9]{64}$/D', $signature)
            || ! in_array($manifest['journal_mode'], ['delete', 'truncate', 'persist'], true)) {
            throw new LogicException('Only an explicit canonical known-v2 rollback-journal admission is supported.');
        }

        $device = self::integer($manifest['device']);
        $inode = self::integer($manifest['inode']);
        $version = self::integer($manifest['schema_version']);
        $foreignKeys = self::integer($manifest['foreign_keys']);
        $synchronous = self::integer($manifest['synchronous']);
        if ($inode < 1 || $version < 1 || ! in_array($foreignKeys, [0, 1], true)
            || ! in_array($synchronous, [0, 1, 2, 3], true)) {
            throw new LogicException('The runtime database identity and approved settings are invalid.');
        }

        return new self($path, $device, $inode, $version, $signature, $foreignKeys, $manifest['journal_mode'], $synchronous);
    }

    public function configuration(bool $write = false): array
    {
        return [
            'driver' => 'sqlite',
            'database' => $this->path,
            'prefix' => '',
            'busy_timeout' => 5000,
            // Do not change journal/FK/synchronous policy while connecting.
            'options' => [
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                Sqlite::ATTR_OPEN_FLAGS => $write ? Sqlite::OPEN_READWRITE : Sqlite::OPEN_READONLY,
            ],
            'transaction_mode' => $write ? 'IMMEDIATE' : 'DEFERRED',
            'name' => $write ? 'chrono_runtime_writer' : 'chrono',
        ];
    }

    private static function integer(mixed $value): int
    {
        if (is_string($value) && preg_match('/^(0|[1-9][0-9]*)$/D', $value)
            && strlen($value) <= strlen((string) PHP_INT_MAX)
            && (strlen($value) < strlen((string) PHP_INT_MAX) || strcmp($value, (string) PHP_INT_MAX) <= 0)) {
            $value = (int) $value;
        }
        if (! is_int($value) || $value < 0) {
            throw new LogicException('Admission numbers must be explicit nonnegative integers.');
        }

        return $value;
    }
}
