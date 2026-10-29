<?php

namespace App\Terminal;

use JsonSerializable;
use LogicException;
use RuntimeException;
use Throwable;

/** Unbound read-only existing root; no key generation, cache, rotation or fallback. */
final readonly class ExistingTerminalFileRoot implements JsonSerializable
{
    /** Pure construction. The trusted composition must supply the matching DB admission. */
    public function __construct(#[\SensitiveParameter] private ExistingTerminalRootAdmission $admission, private array $forbiddenRoots) {}

    public function secret(): string
    {
        $handle = null;
        try {
            if (! function_exists('posix_geteuid') || $this->admission->owner !== posix_geteuid()) { throw new RuntimeException; }
            $this->requireParent();
            $before = $this->fileState();
            $handle = @fopen($this->admission->path, 'rb'); // Never c/x/a/w or CREATE.
            if ($handle === false) { $handle = null; throw new RuntimeException; }
            if ($this->fileState($handle) !== $before) { throw new RuntimeException; }
            $bytes = @stream_get_contents($handle, 65);
            if (! is_string($bytes) || strlen($bytes) !== 64 || preg_match('/^[a-f0-9]{64}$/D', $bytes) !== 1
                || ! hash_equals($this->admission->sha256, hash('sha256', $bytes))
                || $this->fileState($handle) !== $before) { throw new RuntimeException; }
            $this->requireParent();
            if ($this->fileState($handle) !== $before) { throw new RuntimeException; }
            if (! @fclose($handle)) { throw new RuntimeException; }
            $handle = null;
            return $bytes; // Original ASCII bytes; no trim, decoding or copy to config.
        } catch (Throwable) {
            // No native diagnostic/previous exception/path/fingerprint/key escapes.
            throw new RuntimeException('Existing terminal root admission refused.');
        } finally {
            if (is_resource($handle)) { @fclose($handle); }
        }
    }

    private function requireParent(): void
    {
        $parent = dirname($this->admission->path);
        $current = '';
        $this->ancestor('/');
        foreach (explode('/', substr($parent, 1)) as $part) { $current .= '/'.$part; $state = $this->ancestor($current); }
        if (@realpath($parent) !== $parent || ($state['mode'] & 07777) !== 0700
            || $state['uid'] !== $this->admission->owner || $state['dev'] !== $this->admission->parentDevice
            || $state['ino'] !== $this->admission->parentInode) { throw new RuntimeException; }
        foreach ($this->forbiddenRoots as $forbidden) {
            $forbidden = @realpath($forbidden);
            if ($forbidden === false || $this->admission->path === $forbidden
                || str_starts_with($this->admission->path, $forbidden.'/')) { throw new RuntimeException; }
        }
    }

    private function ancestor(string $path): array
    {
        clearstatcache(true, $path);
        $state = @lstat($path);
        if ($state === false || ($state['mode'] & 0170000) !== 0040000
            || ! in_array($state['uid'], [0, $this->admission->owner], true)
            || (($state['mode'] & 0022) !== 0 && ($state['mode'] & 01000) === 0)) { throw new RuntimeException; }
        return $state;
    }

    private function fileState(mixed $handle = null): array
    {
        clearstatcache(true, $this->admission->path);
        $state = @lstat($this->admission->path);
        if ($state === false || ($state['mode'] & 0170000) !== 0100000
            || ($state['mode'] & 07777) !== $this->admission->mode || $state['uid'] !== $this->admission->owner
            || $state['dev'] !== $this->admission->device || $state['ino'] !== $this->admission->inode
            || $state['nlink'] !== 1 || $state['size'] !== 64 || @realpath($this->admission->path) !== $this->admission->path) {
            throw new RuntimeException;
        }
        $keys = array_flip(['dev', 'ino', 'uid', 'mode', 'nlink', 'size', 'mtime', 'ctime']);
        $identity = array_intersect_key($state, $keys);
        if ($handle !== null) {
            $opened = @fstat($handle);
            if ($opened === false || array_intersect_key($opened, $keys) !== $identity) { throw new RuntimeException; }
        }
        return $identity;
    }

    public function __debugInfo(): array
    {
        return ['source' => 'unbound read-only existing terminal root'];
    }

    public function jsonSerialize(): array
    {
        return $this->__debugInfo();
    }

    public function __serialize(): array
    {
        throw new LogicException('terminal root sources must not be serialized.');
    }
}
