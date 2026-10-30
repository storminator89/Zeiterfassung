<?php

namespace App\Api\Management;

use App\Api\ExistingApiKeyAdmission;
use App\Api\ExistingApiFileSigningKey;
use App\LegacyDatabase\RuntimeAdmission;
use App\Accounts\AccountLifecycleAuthority;
use Closure;
use PDO;
use RuntimeException;
use Throwable;

/** Private fixed-cohort files. No repair, rotation, retry or cleanup of uncertain output. */
final class ApiManagementFiles
{
    private bool $locked = false;
    private mixed $operatorHandle = null;
    private ?array $operatorIdentity = null;
    private string $instance;
    private array $baseline;
    public function __construct(private readonly RuntimeAdmission $database, private readonly ?Closure $trustedEntropy = null)
    {
        if (!$database->isGenerationBoundAccountProfile() || basename($database->path) !== 'application.sqlite') { throw new RuntimeException('API management requires local V5.'); }
        $this->instance = dirname($database->path);
        try { $this->baseline = $this->context(); }
        catch (Throwable) { throw new RuntimeException('Private API management cohort refused.'); }
    }
    public function __debugInfo(): array { return ['source' => 'private API management']; }
    public function __serialize(): array { throw new RuntimeException('Private API management cannot be serialized.'); }
    private function context(): array
    {
        clearstatcache(true,$this->instance.'/vacation-v7-upgrade.json');
        if (@lstat($this->instance.'/vacation-v7-upgrade.json') !== false) { throw new RuntimeException; }
        $state = self::instance($this->instance);
        $ancestors = [];
        $path = $this->instance;
        do { $ancestors[$path] = array_intersect_key(self::ancestor($path), array_flip(['dev','ino','uid','mode'])); $path = dirname($path); } while ($path !== '/');
        $ancestors['/'] = array_intersect_key(self::ancestor('/'), array_flip(['dev','ino','uid','mode']));
        [$manifest,$hash,$identity] = self::manifest($this->instance);
        if (ExistingApiKeyAdmission::databaseProfileDigest(RuntimeAdmission::fromConfiguration($manifest)) !== ExistingApiKeyAdmission::databaseProfileDigest($this->database)) { throw new RuntimeException; }
        [$closed,$closedIdentity] = $this->read('setup-closed', 7);
        if ($closed !== "closed\n") { throw new RuntimeException; }
        return [$state,$ancestors,$hash,$identity,$closedIdentity];
    }
    private function unchanged(): void { if ($this->context() !== $this->baseline || ($this->locked && self::file($this->instance.'/setup-operator.lock', $this->operatorHandle) !== $this->operatorIdentity)) { throw new RuntimeException; } }
    private function read(string $name, int $limit): array
    {
        $path = $this->instance.'/'.$name;
        $h = self::openPrivate($path);
        try {
            $id = self::file($path,$h); $before = fstat($h);
            if ($before['size'] > $limit) { throw new RuntimeException; }
            $bytes = stream_get_contents($h,$limit+1);
            if (!is_string($bytes) || strlen($bytes) !== $before['size'] || array_intersect_key(fstat($h), array_flip(['size','mtime','ctime'])) !== array_intersect_key($before, array_flip(['size','mtime','ctime'])) || self::file($path,$h) !== $id) { throw new RuntimeException; }
            return [$bytes,$id];
        } finally { fclose($h); }
    }
    private function exists(string $name): bool { clearstatcache(true,$this->instance.'/'.$name); return @lstat($this->instance.'/'.$name) !== false; }
    /** Trusted provider only; never put this object into responses/config dumps. Does not read key bytes. */
    public function metadata(): ?ExistingApiKeyAdmission
    {
        try {
            $this->unchanged();
            $key = $this->exists('jwt.key'); $env = $this->exists('framework-api-key.env'); $claim = $this->exists('api-management-key.claim');
            if ($claim) { [$bytes] = $this->read('api-management-key.claim',256); if ($bytes !== $this->claim()) { throw new RuntimeException; } }
            if (!$key && !$env && !$claim) { return null; }
            if (!$key || !$env) { throw new RuntimeException; }
            [$line] = $this->read('framework-api-key.env',8300);
            if (preg_match('/\AFRAMEWORK_FRESH_API_KEY_ADMISSION=([A-Za-z0-9+\/=]+)\n\z/D',$line,$m) !== 1) { throw new RuntimeException; }
            $json = base64_decode($m[1],true);
            if ($json === false || base64_encode($json) !== $m[1]) { throw new RuntimeException; }
            $value = json_decode($json,true,16,JSON_THROW_ON_ERROR);
            if (!is_array($value) || json_encode($value,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) !== $json) { throw new RuntimeException; }
            $a = ExistingApiKeyAdmission::fromConfiguration($value,$this->database);
            if ($a->path !== $this->instance.'/jwt.key') { throw new RuntimeException; }
            $h = self::openPrivate($a->path);
            try { $s = self::file($a->path,$h); if ($s['dev'] !== $a->device || $s['ino'] !== $a->inode || $s['uid'] !== $a->owner || ($s['mode'] & 07777) !== $a->mode || fstat($h)['size'] !== 64 || $this->baseline[0]['dev'] !== $a->parentDevice || $this->baseline[0]['ino'] !== $a->parentInode) { throw new RuntimeException; } }
            finally { fclose($h); }
            $this->unchanged(); return $a;
        } catch (Throwable) { throw new RuntimeException('Private API key files blocked; no automatic repair.'); }
    }
    public function inspect(): array
    {
        try { return ['status' => $this->metadata() === null ? 'missing' : 'prepared']; }
        catch (Throwable) { return ['status' => 'blocked']; }
    }
    private function claim(): string { return 'api-management-v1:'.ExistingApiKeyAdmission::databaseProfileDigest($this->database)."\n"; }
    /** Nonblocking exclusive operator lock: contention fails closed; caller may retry explicitly. */
    public function withLock(Closure $action): mixed
    {
        if ($this->locked) { throw new RuntimeException('Nested operator lock refused.'); }
        $this->unchanged(); $path = $this->instance.'/setup-operator.lock';
        $check = self::openPrivate($path); $id = self::file($path,$check); fclose($check);
        $h = @fopen($path,'r+b');
        try {
            if (self::file($path,$h) !== $id || fstat($h)['size'] !== 0 || !flock($h,LOCK_EX|LOCK_NB)) { throw new RuntimeException; }
            $this->unchanged(); $this->operatorHandle = $h; $this->operatorIdentity = $id; $this->locked = true;
            $result = $action();
            $this->unchanged();
            return $result;
        } finally { $this->locked = false; $this->operatorHandle = null; $this->operatorIdentity = null; if (is_resource($h)) { flock($h,LOCK_UN); fclose($h); } }
    }
    /** Caller-owned operator then admitted writer scope; never inspects signing-key files. */
    public function requireLockedWriter(PDO $db): void
    {
        if (!$this->locked || !$db->inTransaction()) { throw new RuntimeException('Operator then writer lock required.'); }
        $rows = $db->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC);
        $main = array_values(array_filter($rows,fn($r) => $r['name'] === 'main'));
        if ($this->database->schemaProfile==='vacation-requests-v7') {
            \App\LegacyDatabase\VacationWorkflowV7References::requireAdmitted($db,$this->database);
        } elseif (count($rows) !== 1 || count($main) !== 1 || $main[0]['file'] !== $this->database->path || $db->query('PRAGMA schema_version')->fetchColumn() !== $this->database->schemaVersion) { throw new RuntimeException('Admitted writer required.'); }
        $this->unchanged();
    }
    public function prepareLocked(PDO $db, AccountLifecycleAuthority $actor, ApiPolicyChange $change, bool $legacyDefaultEnabled): array
    {
        if (!$change->enabled) { throw new RuntimeException('Disable must not inspect or prepare signing keys.'); }
        $this->requireLockedWriter($db);
        $state = (new ApiPolicyJournal($this->database->schemaProfile==='vacation-requests-v7'?$this->database:null))->requireChangeLocked($db,$actor,$change,$legacyDefaultEnabled);
        $this->unchanged(); $metadata = $this->metadata();
        if ($metadata !== null) { $secret = (new ExistingApiFileSigningKey($metadata))->secret(); unset($secret); $this->unchanged(); return ['status'=>'prepared','key_confirmed'=>true]; }
        if ($state->keyPrepared || !$change->allowNewKey || !$change->enabled) { throw new RuntimeException('Explicit new-key authorization required.'); }
        $handles = []; $mask = umask(0077);
        try {
            foreach (['api-management-key.claim','jwt.key','framework-api-key.env'] as $name) {
                $h = @fopen($this->instance.'/'.$name,'x+b'); if ($h === false) { throw new RuntimeException; }
                $handles[$name] = [$h,self::file($this->instance.'/'.$name,$h)];
                if ($name === 'api-management-key.claim') { self::write($this->instance.'/'.$name,$h,$handles[$name][1],$this->claim()); }
            }
            $this->unchanged();
            $raw = $this->trustedEntropy === null ? random_bytes(32) : ($this->trustedEntropy)();
            try { if (!is_string($raw) || strlen($raw) !== 32) { throw new RuntimeException; } $key = bin2hex($raw); }
            finally { unset($raw); }
            [$h,$s] = $handles['jwt.key'];
            try { self::write($this->instance.'/jwt.key',$h,$s,$key); $digest = hash('sha256',$key); } finally { unset($key); }
            $v = ['version'=>1,'path'=>$this->instance.'/jwt.key','device'=>$s['dev'],'inode'=>$s['ino'],'owner'=>$s['uid'],'mode'=>0600,'parent_device'=>$this->baseline[0]['dev'],'parent_inode'=>$this->baseline[0]['ino'],'sha256'=>$digest,'database_profile_sha256'=>ExistingApiKeyAdmission::databaseProfileDigest($this->database)];
            ExistingApiKeyAdmission::fromConfiguration($v,$this->database);
            [$h,$s] = $handles['framework-api-key.env'];
            self::write($this->instance.'/framework-api-key.env',$h,$s,'FRAMEWORK_FRESH_API_KEY_ADMISSION='.base64_encode(json_encode($v,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR))."\n");
            $this->unchanged();
            foreach ($handles as $name => [$h,$s]) { if (self::file($this->instance.'/'.$name,$h) !== $s || !fclose($h)) { throw new RuntimeException; } }
            $handles = [];
            $secret = (new ExistingApiFileSigningKey($this->metadata()))->secret(); unset($secret);
            return ['status'=>'prepared','key_confirmed'=>true];
        } catch (Throwable) { throw new RuntimeException('API preparation not confirmed; retained files require private inspection, no automatic retry.'); }
        finally { foreach ($handles as [$h]) { if (is_resource($h)) { fclose($h); } } umask($mask); }
    }
    private static function instance(string $path): array
    {
        if ($path === '' || $path[0] !== '/' || str_contains($path, "\0")
            || array_intersect(explode('/', substr($path, 1)), ['', '.', '..']) !== []
            || preg_match('/^chrono-fresh-[a-f0-9]{24}$/D', basename($path)) !== 1) { throw new RuntimeException; }
        $current = '';
        self::ancestor('/');
        foreach (explode('/', substr($path, 1)) as $part) { $current .= '/'.$part; $stat = self::ancestor($current); }
        if (@realpath($path) !== $path || ($stat['mode'] & 07777) !== 0700 || $stat['uid'] !== posix_geteuid()) { throw new RuntimeException; }
        $root = dirname(__DIR__, 4);
        foreach ([$root, $root.'/framework/public'] as $forbidden) {
            $forbidden = @realpath($forbidden);
            if ($forbidden === false || $path === $forbidden || str_starts_with($path, $forbidden.'/')) { throw new RuntimeException; }
        }
        return array_intersect_key($stat, array_flip(['dev', 'ino', 'uid', 'mode']));
    }

    private static function ancestor(string $path): array
    {
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if ($stat === false || ($stat['mode'] & 0170000) !== 0040000 || ! in_array($stat['uid'], [0, posix_geteuid()], true)
            || (($stat['mode'] & 0022) !== 0 && ($stat['mode'] & 01000) === 0)) { throw new RuntimeException; }
        return $stat;
    }

    /** Excludes legitimate size/time changes during exclusive NEW writes. */
    private static function file(string $path, mixed $handle): array
    {
        clearstatcache(true, $path);
        $stat = @lstat($path);
        $opened = is_resource($handle) ? @fstat($handle) : false;
        $keys = array_flip(['dev', 'ino', 'uid', 'mode', 'nlink']);
        if ($stat === false || $opened === false || ($stat['mode'] & 0170000) !== 0100000
            || ($stat['mode'] & 07777) !== 0600 || $stat['uid'] !== posix_geteuid() || $stat['nlink'] !== 1
            || @realpath($path) !== $path || array_intersect_key($stat, $keys) !== array_intersect_key($opened, $keys)) { throw new RuntimeException; }
        return array_intersect_key($stat, $keys);
    }

    private static function openPrivate(string $path): mixed
    {
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if ($stat === false || ($stat['mode'] & 0170000) !== 0100000 || ($stat['mode'] & 07777) !== 0600
            || $stat['uid'] !== posix_geteuid() || $stat['nlink'] !== 1) { throw new RuntimeException; }
        $handle = @fopen($path, 'rb');
        if ($handle === false) { throw new RuntimeException; }
        try {
            $opened = self::file($path, $handle);
            if ($opened['dev'] !== $stat['dev'] || $opened['ino'] !== $stat['ino']) { throw new RuntimeException; }
            return $handle;
        } catch (Throwable $error) { @fclose($handle); throw $error; }
    }

    /** Fixed admission JSON only, never jwt.key or another key/token file. */
    private static function manifest(string $instance): array
    {
        $path = $instance.'/runtime-admission.json';
        $handle = self::openPrivate($path);
        try {
            $identity = self::file($path, $handle);
            $before = fstat($handle);
            if ($before === false || $before['size'] < 1 || $before['size'] > 4096) { throw new RuntimeException; }
            $bytes = stream_get_contents($handle, 4097);
            $after = fstat($handle);
            if ($after === false || ! is_string($bytes) || strlen($bytes) !== $before['size'] || $before['size'] !== $after['size']
                || $before['mtime'] !== $after['mtime'] || $before['ctime'] !== $after['ctime']
                || self::file($path, $handle) !== $identity) { throw new RuntimeException; }
            $decoded = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($decoded) || $bytes !== json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n") {
                throw new RuntimeException;
            }
            return [$decoded, hash('sha256', $bytes), $identity];
        } finally { if (! @fclose($handle)) { throw new RuntimeException; } }
    }

    private static function write(string $path, mixed $handle, array $identity, #[\SensitiveParameter] string $bytes): void
    {
        $offset = 0;
        while ($offset < strlen($bytes)) {
            $written = @fwrite($handle, substr($bytes, $offset));
            if ($written === false || $written === 0) { throw new RuntimeException; }
            $offset += $written;
        }
        if (! @fflush($handle) || ! @fsync($handle) || self::file($path, $handle) !== $identity) { throw new RuntimeException; }
        $written = @fstat($handle);
        if ($written === false || $written['size'] !== strlen($bytes)) { throw new RuntimeException; }
    }
}
