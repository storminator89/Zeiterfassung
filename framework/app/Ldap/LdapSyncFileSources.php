<?php

namespace App\Ldap;

use App\LegacyDatabase\RuntimeAdmission;
use JsonSerializable;
use LogicException;
use RuntimeException;
use Throwable;

/** Pure metadata, then read-only pinned files. NEVER native bootstrap/key creation. */
final readonly class LdapSyncFileSources implements JsonSerializable
{
    private function __construct(private array $ca, private array $settingsRoot, private array $forbiddenRoots) {}

    public static function fromConfiguration(array $ca, #[\SensitiveParameter] array $settingsRoot, array $forbiddenRoots): self
    {
        if ($forbiddenRoots === []) { throw new LogicException('Explicit application/public roots are required.'); }
        foreach ($forbiddenRoots as $path) {
            if (! is_string($path) || $path === '' || $path[0] !== '/' || str_contains($path, "\0")
                || array_intersect(explode('/', substr($path, 1)), ['', '.', '..']) !== []) {
                throw new LogicException('Explicit canonical application/public roots are required.');
            }
        }
        return new self(self::metadata($ca, false), self::metadata($settingsRoot, true), $forbiddenRoots);
    }

    /** Canonical PUBLIC DB admission metadata, not a DB read or key fingerprint generator. */
    public static function databaseProfileDigest(RuntimeAdmission $database): string
    {
        $metadata = [
            'version' => 1, 'path' => $database->path, 'device' => $database->device, 'inode' => $database->inode,
            'schema_version' => $database->schemaVersion, 'schema_signature' => $database->schemaSignature,
            'foreign_keys' => $database->foreignKeys, 'journal_mode' => $database->journalMode, 'synchronous' => $database->synchronous,
            'schema_profile' => $database->schemaProfile, 'base_schema_signature' => $database->baseSchemaSignature,
            'user_audit_schema_signature' => $database->userAuditSchemaSignature,
        ];
        // V5 binds its fourth explicit lineage signature as well. Do not add
        // a null field to V2/V3/V4: their historical digest bytes stay exact.
        if ($database->schemaProfile === 'account-lifecycle-v5') {
            $metadata['intake_policy_schema_signature'] = $database->intakePolicySchemaSignature;
        }

        return hash('sha256', json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /** Call only inside the authorized collector operation; no key read. */
    public function admittedCaPath(): string
    {
        $this->read(false);
        return $this->ca['path'];
    }

    /** Native encryption.key ASCII only; the existing cipher performs hex2bin. */
    public function settingsRoot(): string
    {
        return $this->read(true);
    }

    private static function metadata(array $row, bool $root): array
    {
        try {
            $keys = ['path', 'device', 'inode', 'owner', 'mode', 'parent_device', 'parent_inode', 'sha256'];
            if (count($row) !== count($keys) || array_diff($keys, array_keys($row)) !== []
                || ! is_string($row['path']) || $row['path'] === '' || $row['path'][0] !== '/'
                || str_contains($row['path'], "\0")
                || array_intersect(explode('/', substr($row['path'], 1)), ['', '.', '..']) !== []
                || ($root && basename($row['path']) !== 'encryption.key')) { throw new LogicException; }
            foreach (['device', 'inode', 'owner', 'mode', 'parent_device', 'parent_inode'] as $key) {
                if (! is_int($row[$key]) || $row[$key] < 0) { throw new LogicException; }
            }
            if ($row['inode'] < 1 || $row['parent_inode'] < 1
                || ! in_array($row['mode'], $root ? [0400, 0600] : [0400, 0444, 0600, 0644], true)
                || ! is_string($row['sha256']) || preg_match('/^[a-f0-9]{64}$/D', $row['sha256']) !== 1) {
                throw new LogicException;
            }
            return $row;
        } catch (Throwable) { throw new LogicException('LDAP sync file metadata is not admitted.'); }
    }

    private function read(bool $root): string
    {
        $metadata = $root ? $this->settingsRoot : $this->ca;
        $handle = null;
        try {
            if (! function_exists('posix_geteuid') || ($root && $metadata['owner'] !== posix_geteuid())
                || ! in_array($metadata['owner'], [0, posix_geteuid()], true)) { throw new RuntimeException; }
            $this->requireParent($metadata, $root);
            $before = $this->fileState($metadata, $root);
            $handle = @fopen($metadata['path'], 'rb'); // No c/x/a/w, flock writer, CREATE or repair.
            if ($handle === false) { $handle = null; throw new RuntimeException; }
            if ($this->fileState($metadata, $root, $handle) !== $before) { throw new RuntimeException; }
            $bytes = @stream_get_contents($handle, $root ? 65 : 1048577);
            if (! is_string($bytes) || strlen($bytes) !== $before['size']
                || ($root && preg_match('/^[a-f0-9]{64}$/D', $bytes) !== 1)
                || ! hash_equals($metadata['sha256'], hash('sha256', $bytes))
                || $this->fileState($metadata, $root, $handle) !== $before) { throw new RuntimeException; }
            $this->requireParent($metadata, $root);
            if ($this->fileState($metadata, $root, $handle) !== $before || ! @fclose($handle)) { throw new RuntimeException; }
            $handle = null;
            return $bytes;
        } catch (Throwable) {
            // Never expose the path, hash, contents, native diagnostic or previous exception.
            throw new RuntimeException('LDAP sync existing file admission refused.');
        } finally { if (is_resource($handle)) { @fclose($handle); } }
    }

    private function requireParent(array $metadata, bool $root): void
    {
        $parent = dirname($metadata['path']);
        $current = '';
        $this->ancestor('/');
        foreach (explode('/', substr($parent, 1)) as $part) { $current .= '/'.$part; $state = $this->ancestor($current); }
        if (@realpath($parent) !== $parent || $state['dev'] !== $metadata['parent_device']
            || $state['ino'] !== $metadata['parent_inode']
            || ($root && (($state['mode'] & 07777) !== 0700 || $state['uid'] !== $metadata['owner']))) {
            throw new RuntimeException;
        }
        // Both sources must be outside the checkout/public root, never upload-selected.
        $checkout = dirname(__DIR__, 3);
        foreach ([$checkout, $checkout.'/framework/public', ...$this->forbiddenRoots] as $forbidden) {
            $forbidden = @realpath($forbidden);
            if ($forbidden === false || $metadata['path'] === $forbidden
                || str_starts_with($metadata['path'], $forbidden.'/')) { throw new RuntimeException; }
        }
    }

    private function ancestor(string $path): array
    {
        clearstatcache(true, $path);
        $state = @lstat($path);
        if ($state === false || ($state['mode'] & 0170000) !== 0040000
            || ! in_array($state['uid'], [0, posix_geteuid()], true)
            || (($state['mode'] & 0022) !== 0 && ($state['mode'] & 01000) === 0)) { throw new RuntimeException; }
        return $state;
    }

    private function fileState(array $metadata, bool $root, mixed $handle = null): array
    {
        clearstatcache(true, $metadata['path']);
        $state = @lstat($metadata['path']);
        if ($state === false || ($state['mode'] & 0170000) !== 0100000
            || ($state['mode'] & 07777) !== $metadata['mode'] || $state['uid'] !== $metadata['owner']
            || $state['dev'] !== $metadata['device'] || $state['ino'] !== $metadata['inode']
            || $state['nlink'] !== 1 || ($root ? $state['size'] !== 64 : ($state['size'] < 1 || $state['size'] > 1048576))
            || @realpath($metadata['path']) !== $metadata['path']) { throw new RuntimeException; }
        $keys = array_flip(['dev', 'ino', 'uid', 'mode', 'nlink', 'size', 'mtime', 'ctime']);
        $identity = array_intersect_key($state, $keys);
        if ($handle !== null) {
            $opened = @fstat($handle);
            if ($opened === false || array_intersect_key($opened, $keys) !== $identity) { throw new RuntimeException; }
        }
        return $identity;
    }

    public function __debugInfo(): array { return ['source' => 'pinned existing LDAP CA/settings root']; }
    public function jsonSerialize(): array { return $this->__debugInfo(); }
    public function __serialize(): array { throw new LogicException('LDAP sync file sources must not be serialized.'); }
}
