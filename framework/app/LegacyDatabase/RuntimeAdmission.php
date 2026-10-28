<?php

namespace App\LegacyDatabase;

use App\Accounts\IntakePolicy;
use LogicException;
use PDO;
use Pdo\Sqlite;

/** Trusted operator manifest, not a request DTO or automatic admission claim. */
final readonly class RuntimeAdmission
{
    public const INTAKE_POLICY_SQL = 'SELECT id,manual_enabled,ldap_sync_enabled,registration_enabled,revision,updated_at,updated_by FROM user_intake_policy ORDER BY id LIMIT 2';

    private function __construct(
        public string $path,
        public int $device,
        public int $inode,
        public int $schemaVersion,
        public string $schemaSignature,
        public int $foreignKeys,
        public string $journalMode,
        public int $synchronous,
        public ?string $schemaProfile = null,
        public ?string $baseSchemaSignature = null,
        public ?string $userAuditSchemaSignature = null,
    ) {}

    // Pure configuration validation: no stat, file read, PDO or source bootstrap.
    public static function fromConfiguration(array $manifest): self
    {
        $keys = ['path', 'device', 'inode', 'schema_version', 'schema_signature', 'foreign_keys', 'journal_mode', 'synchronous'];
        $profile = null;
        $baseSignature = null;
        $userAuditSignature = null;
        if (array_key_exists('schema_profile', $manifest) || array_key_exists('base_schema_signature', $manifest)
            || array_key_exists('user_audit_schema_signature', $manifest)) {
            $keys = [...$keys, 'schema_profile', 'base_schema_signature'];
            $profile = $manifest['schema_profile'] ?? null;
            $baseSignature = $manifest['base_schema_signature'] ?? null;
            if ($profile === 'intake-policy-v4') {
                $keys[] = 'user_audit_schema_signature';
                $userAuditSignature = $manifest['user_audit_schema_signature'] ?? null;
                if (! is_string($baseSignature) || ! preg_match('/^[a-f0-9]{64}$/D', $baseSignature)
                    || ! is_string($userAuditSignature) || ! preg_match('/^[a-f0-9]{64}$/D', $userAuditSignature)
                    || $baseSignature === $userAuditSignature || $baseSignature === ($manifest['schema_signature'] ?? null)
                    || $userAuditSignature === ($manifest['schema_signature'] ?? null)) {
                    throw new LogicException('The complete distinct V2/V3/V4 signature chain is required.');
                }
            } elseif ($profile !== 'user-audit-v3' || ! is_string($baseSignature)
                || ! preg_match('/^[a-f0-9]{64}$/D', $baseSignature)
                || $baseSignature === ($manifest['schema_signature'] ?? null)) {
                throw new LogicException('An explicit complete user-audit-v3 profile and distinct base signature are required.');
            }
        }
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

        return new self($path, $device, $inode, $version, $signature, $foreignKeys, $manifest['journal_mode'], $synchronous, $profile, $baseSignature, $userAuditSignature);
    }

    /** Pure exact ledger projection shared by all PDO/SQLite3 admission sites. */
    public function expectedLedgerRows(): array
    {
        return match ($this->schemaProfile) {
            'user-audit-v3' => self::userAuditV3LedgerRows($this->baseSchemaSignature, $this->schemaSignature),
            'intake-policy-v4' => self::intakePolicyV4LedgerRows($this->baseSchemaSignature, $this->userAuditSchemaSignature, $this->schemaSignature),
            default => [['id' => 1, 'migration' => V2Adoption::migrationName($this->schemaSignature), 'batch' => 1]],
        };
    }

    public static function intakePolicyV4LedgerRows(string $baseSignature, string $userAuditSignature, string $postSignature): array
    {
        if (! preg_match('/^[a-f0-9]{64}$/D', $postSignature)
            || in_array($postSignature, [$baseSignature, $userAuditSignature], true)) {
            throw new LogicException('A distinct complete intake-policy-v4 signature is required.');
        }

        return [...self::userAuditV3LedgerRows($baseSignature, $userAuditSignature),
            ['id' => 3, 'migration' => '2026_10_28_000003_intake_policy_v4_'.$postSignature, 'batch' => 3]];
    }

    /** Pure row validation shared by PDO and native SQLite snapshot ingress. */
    public function assertIntakePolicyState(array $versions, array $rows): void
    {
        if ($this->schemaProfile !== 'intake-policy-v4' || $versions !== [1, 2, 3, 4] || count($rows) !== 1) {
            throw new LogicException('The complete admitted intake-policy-v4 state is required.');
        }
        IntakePolicy::fromRow($rows[0]);
    }

    public static function userAuditV3MigrationName(string $signature): string
    {
        if (! preg_match('/^[a-f0-9]{64}$/D', $signature)) {
            throw new LogicException('A complete user-audit-v3 schema signature is required.');
        }

        return '2026_10_28_000002_user_audit_v3_'.$signature;
    }

    public static function userAuditV3LedgerRows(string $baseSignature, string $postSignature): array
    {
        if (! preg_match('/^[a-f0-9]{64}$/D', $baseSignature) || $baseSignature === $postSignature) {
            throw new LogicException('A distinct complete original V2 signature is required.');
        }

        return [
            ['id' => 1, 'migration' => V2Adoption::migrationName($baseSignature), 'batch' => 1],
            ['id' => 2, 'migration' => self::userAuditV3MigrationName($postSignature), 'batch' => 2],
        ];
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
