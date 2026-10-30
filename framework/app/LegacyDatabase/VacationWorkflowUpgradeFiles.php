<?php

namespace App\LegacyDatabase;

use Closure;
use RuntimeException;
use Throwable;

/** Fixed stopped-cohort metadata handoff; never a key producer, path repair or Runtime activation. */
final class VacationWorkflowUpgradeFiles
{
    public const STATE = 'vacation-v7-upgrade.json';
    public const RECEIPT = 'vacation-v7-upgrade-complete.json';
    private array $directoryIdentity;
    private mixed $operator = null;
    private ?array $operatorIdentity = null;

    public function __construct(public readonly string $directory, private readonly OfflineFiles $files)
    {
        if (PHP_SAPI !== 'cli' || !function_exists('posix_geteuid') || !function_exists('fsync')
            || preg_match('/\Achrono-fresh-[a-f0-9]{24}\z/',basename($directory))!==1) {
            throw new RuntimeException('A stopped canonical private cohort is required.');
        }
        $this->directoryIdentity=$this->requireDirectory();
        // Existing file admission also enforces both application-webroot boundaries.
        $this->files->validate($directory.'/application.sqlite');
    }

    /** One existing fixed operator lock, never a new/guessed lock path. */
    public function locked(Closure $operation): mixed
    {
        if ($this->operator!==null) { throw new RuntimeException('Nested upgrade lock refused.'); }
        $this->unchanged();
        $path=$this->path('setup-operator.lock');
        $handle=@fopen($path,'r+b');
        if ($handle===false) { throw new RuntimeException('The existing operator lock is required.'); }
        try {
            $identity=$this->identity($path,$handle);
            $state=fstat($handle);
            if ($state===false || $state['size']!==0 || !flock($handle,LOCK_EX|LOCK_NB)) {
                throw new RuntimeException('Stop/drain all cohort users before upgrading.');
            }
            $this->operator=$handle;$this->operatorIdentity=$identity;
            $this->unchanged();
            return $operation();
        } finally {
            $this->operator=null;$this->operatorIdentity=null;
            @flock($handle,LOCK_UN);@fclose($handle);
        }
    }

    public function exists(string $name): bool
    {
        $this->unchanged();$path=$this->path($name);clearstatcache(true,$path);
        return @lstat($path)!==false;
    }

    /** Bounded private bytes; callers never return/log a metadata document or its values. */
    public function read(string $name,int $limit=131072): string
    {
        $this->unchanged();$path=$this->path($name);$handle=@fopen($path,'rb');
        if ($handle===false) { throw new RuntimeException('Required upgrade metadata is unavailable.'); }
        try {
            $identity=$this->identity($path,$handle);$before=fstat($handle);
            if ($before===false || $limit<1 || $before['size']<0 || $before['size']>$limit) { throw new RuntimeException('Upgrade metadata is not bounded.'); }
            $bytes=stream_get_contents($handle,$limit+1);$after=fstat($handle);
            if (!is_string($bytes) || $after===false || strlen($bytes)!==$before['size']
                || array_intersect_key($before,array_flip(['size','mtime','ctime']))!==array_intersect_key($after,array_flip(['size','mtime','ctime']))
                || $this->identity($path,$handle)!==$identity) { throw new RuntimeException('Upgrade metadata changed while reading.'); }
            $this->unchanged();return $bytes;
        } finally { @fclose($handle); }
    }

    public function json(string $name): array
    {
        $bytes=$this->read($name);
        try { $decoded=json_decode($bytes,true,32,JSON_THROW_ON_ERROR); }
        catch (Throwable) { throw new RuntimeException('Upgrade metadata is not canonical.'); }
        if (!is_array($decoded) || $bytes!==self::encode($decoded)) { throw new RuntimeException('Upgrade metadata is not canonical.'); }
        return $decoded;
    }

    public static function encode(array $value): string
    {
        return json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    }

    /** Exclusively new regular file. Retain any uncertain output for explicit later inspection. */
    public function reserve(string $name,string $bytes): void
    {
        if (!in_array($name,[self::STATE,self::RECEIPT,'vacation-v7-channel-admissions.json',
            'vacation-v7-new-runtime-admission.json','vacation-v7-new-framework-api-key.env',
            'vacation-v7-new-api-management-key.claim','vacation-v7-new-ldap-settings-root-admission.json'],true)) {
            throw new RuntimeException('Only fixed new upgrade metadata may be reserved.');
        }
        $this->requireLocked();$path=$this->path($name);$mask=umask(0077);$handle=null;
        try {
            $handle=@fopen($path,'x+b');
            if ($handle===false) { throw new RuntimeException('Upgrade output already exists or cannot be reserved.'); }
            $identity=$this->identity($path,$handle);$this->write($path,$handle,$identity,$bytes);
        } finally { if (is_resource($handle)) { @fclose($handle); } umask($mask); }
        $this->unchanged();
    }

    /** Only a plan-recorded old value or already-complete new value is accepted. */
    public function replaceKnown(string $name,string $old,string $new,string $staging): void
    {
        if (!in_array($name,['runtime-admission.json','framework-api-key.env','api-management-key.claim',
            'ldap-settings-root-admission.json'],true) || $staging!=='vacation-v7-new-'.$name) {
            throw new RuntimeException('Only exact planned admission metadata may be replaced.');
        }
        $this->requireLocked();$current=$this->read($name);
        if (hash_equals($current,$new)) { return; }
        if (!hash_equals($current,$old)) { throw new RuntimeException('Unexpected metadata must not be overwritten.'); }
        if ($this->exists($staging)) {
            if (!hash_equals($this->read($staging),$new)) { throw new RuntimeException('Uncertain staged metadata needs private inspection.'); }
        } else { $this->reserve($staging,$new); }
        // Both paths are fixed to this trusted same-UID private directory and all
        // cooperative admin/upgrade writers hold the same operator lock. This
        // is not a defense against a malicious owner racing filesystem renames.
        $oldIdentity=$this->identity($this->path($name));
        $stageIdentity=$this->identity($this->path($staging));
        if (!hash_equals($this->read($name),$old) || !hash_equals($this->read($staging),$new)
            || $this->identity($this->path($name))!==$oldIdentity
            || $this->identity($this->path($staging))!==$stageIdentity
            || !@rename($this->path($staging),$this->path($name))) {
            throw new RuntimeException('Metadata handoff not confirmed; do not restart the cohort.');
        }
        if ($this->identity($this->path($name))!==$stageIdentity || !hash_equals($this->read($name),$new)) {
            throw new RuntimeException('Metadata handoff not confirmed; keep the cohort stopped.');
        }
    }

    /** Remove only the exact recorded pending marker after the complete receipt exists. */
    public function finish(string $expectedPending,string $expectedReceipt): void
    {
        $this->requireLocked();
        if (!$this->exists(self::RECEIPT) || !hash_equals($this->read(self::RECEIPT),$expectedReceipt)
            || !hash_equals($this->read(self::STATE),$expectedPending)) {
            throw new RuntimeException('A confirmed complete upgrade receipt is required.');
        }
        $path=$this->path(self::STATE);$identity=$this->identity($path);
        if ($this->identity($path)!==$identity || !@unlink($path)) { throw new RuntimeException('Upgrade fence removal not confirmed.'); }
        $this->unchanged();
    }

    public function identityOf(string $name): array { return $this->identity($this->path($name)); }

    /** Existence/identity only; key bytes never enter this metadata handoff. */
    public function keyExists(string $name): bool
    {
        if (!in_array($name,['jwt.key','encryption.key','framework-app-key.env'],true)) {
            throw new RuntimeException('Only fixed existing key identities are supported.');
        }
        $this->unchanged();$path=$this->directory.'/'.$name;clearstatcache(true,$path);
        if (@lstat($path)===false) { return false; }
        $this->identity($path,null,[0400,0600]);return true;
    }

    private function requireLocked(): void
    {
        if (!is_resource($this->operator) || $this->operatorIdentity===null) { throw new RuntimeException('The operator lock is required.'); }
        $this->unchanged();
    }

    private function unchanged(): void
    {
        if ($this->requireDirectory()!==$this->directoryIdentity
            || ($this->operator!==null && $this->identity($this->path('setup-operator.lock'),$this->operator)!==$this->operatorIdentity)) {
            throw new RuntimeException('The stopped upgrade cohort changed.');
        }
    }

    private function path(string $name): string
    {
        // Closed metadata inventory: never permit a key file (including APP_KEY)
        // or arbitrary environment file to be read, archived or overwritten.
        $fixed=['setup-operator.lock','setup-closed','runtime-admission.json',
            'framework-api-key.env','api-management-key.claim','ldap-settings-root-admission.json',
            self::STATE,self::RECEIPT,'vacation-v7-channel-admissions.json'];
        $metadata=['runtime-admission.json','framework-api-key.env','api-management-key.claim',
            'ldap-settings-root-admission.json'];
        $stages=array_map(static fn(string $file):string=>'vacation-v7-new-'.$file,$metadata);
        if (!in_array($name,[...$fixed,...$stages],true)) {
            throw new RuntimeException('Only a fixed cohort filename is supported.');
        }
        return $this->directory.'/'.$name;
    }

    private function identity(string $path,mixed $handle=null,array $modes=[0600]): array
    {
        clearstatcache(true,$path);$state=@lstat($path);$keys=array_flip(['dev','ino','uid','mode','nlink']);
        if ($state===false || ($state['mode']&0170000)!==0100000 || !in_array($state['mode']&07777,$modes,true)
            || $state['uid']!==posix_geteuid() || $state['nlink']!==1 || realpath($path)!==$path
            || ($handle!==null && array_intersect_key((array)@fstat($handle),$keys)!==array_intersect_key($state,$keys))) {
            throw new RuntimeException('Private regular single-link cohort files are required.');
        }
        return array_intersect_key($state,$keys);
    }

    private function requireDirectory(): array
    {
        $path=$this->directory;
        if ($path==='' || $path[0]!=='/' || str_contains($path,"\0") || array_intersect(explode('/',substr($path,1)),['','.','..'])!==[]) {
            throw new RuntimeException('A canonical cohort directory is required.');
        }
        $current='';
        foreach (['/',...array_filter(explode('/',$path),static fn(string $part):bool=>$part!=='')] as $part) {
            $current=$part==='/'?'/':rtrim($current,'/').'/'.$part;clearstatcache(true,$current);$state=@lstat($current);
            if ($state===false || ($state['mode']&0170000)!==0040000 || !in_array($state['uid'],[0,posix_geteuid()],true)
                || (($state['mode']&0022)!==0 && ($state['mode']&01000)===0)) { throw new RuntimeException('Unsafe upgrade directory ancestry.'); }
        }
        if (realpath($path)!==$path || ($state['mode']&07777)!==0700 || $state['uid']!==posix_geteuid()) {
            throw new RuntimeException('The cohort must remain private to its existing owner.');
        }
        return array_intersect_key($state,array_flip(['dev','ino','uid','mode']));
    }

    private function write(string $path,mixed $handle,array $identity,string $bytes): void
    {
        if (strlen($bytes)>131072) { throw new RuntimeException('Upgrade metadata is too large.'); }
        for ($offset=0;$offset<strlen($bytes);) {
            $written=@fwrite($handle,substr($bytes,$offset));
            if ($written===false || $written===0) { throw new RuntimeException('Upgrade output not confirmed.'); }
            $offset+=$written;
        }
        if (!@fflush($handle) || !@fsync($handle)) { throw new RuntimeException('Upgrade output not confirmed.'); }
        $state=fstat($handle);
        if ($state===false || $state['size']!==strlen($bytes) || $this->identity($path,$handle)!==$identity) {
            throw new RuntimeException('Upgrade output not confirmed.');
        }
    }
}
