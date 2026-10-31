<?php

namespace App\Mcp;

use DomainException;
use InvalidArgumentException;
use RuntimeException;

/** Installation-wide HTTP operating policy. No account, token, URL or process selector. */
final class ManagedProfile
{
    public const FIELDS = ['mcp_management', 'mcp_action', 'mcp_revision', 'current_password', 'apps_enabled'];
    public function __construct(private ?string $directory, private array $defaults) {}

    public function read(): array
    {
        $state = ['revision'=>0, 'enabled'=>($this->defaults['http_enabled'] ?? false) === true,
            'apps_enabled'=>($this->defaults['apps_enabled'] ?? false) === true];
        if ($this->directory === null) { return $state; }
        $this->assertDirectory();
        $path = $this->directory.'/mcp-http-policy.json';
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if ($stat === false) { return $state; }
        $handle = $this->openExisting($path, $stat);
        try { $contents = stream_get_contents($handle, 4097); } finally { fclose($handle); }
        $stored = json_decode($contents, true, 8, JSON_THROW_ON_ERROR);
        if (! is_array($stored) || array_keys($stored) !== ['revision','enabled','apps_enabled']
            || ! is_int($stored['revision']) || $stored['revision'] < 1
            || ! is_bool($stored['enabled']) || ! is_bool($stored['apps_enabled'])) {
            throw new RuntimeException('Private MCP-Einstellungen nicht lesbar.');
        }
        return $stored;
    }

    public function status(): array
    {
        try { return [...$this->read(), 'available'=>$this->directory !== null, 'error'=>false]; }
        catch (\Throwable) { return ['revision'=>0,'enabled'=>false,'apps_enabled'=>false,'available'=>false,'error'=>true]; }
    }

    public function change(array $form): void
    {
        if ($this->directory === null) { throw new RuntimeException('Private lokale Installation erforderlich.'); }
        $action = $form['mcp_action'] ?? null;
        if (($form['mcp_management'] ?? null) !== '1' || ! in_array($action, ['save','enable','disable'], true)
            || ! is_string($form['mcp_revision'] ?? null) || ! preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/D', $form['mcp_revision'])
            || (isset($form['apps_enabled']) && $form['apps_enabled'] !== '1')
            || array_diff(array_keys($form), [...self::FIELDS,'_token','csrf_token']) !== []) {
            throw new InvalidArgumentException('MCP-Einstellungen prüfen.');
        }
        $this->assertDirectory();
        $lock = $this->createOrOpenLock($this->directory.'/mcp-http-policy.lock');
        try {
            if (! flock($lock, LOCK_EX | LOCK_NB)) { throw new DomainException('MCP-Einstellungen werden gerade geändert. Bitte neu laden.'); }
            $old = $this->read();
            if ((string) $old['revision'] !== $form['mcp_revision']) { throw new DomainException('MCP-Einstellungen wurden geändert. Bitte neu laden.'); }
            $state = ['revision'=>$old['revision']+1, 'enabled'=>$action === 'enable' || ($action === 'save' && $old['enabled']),
                'apps_enabled'=>$action === 'disable' ? $old['apps_enabled'] : (($form['apps_enabled'] ?? null) === '1')];
            $temporary = $this->directory.'/mcp-http-policy-'.bin2hex(random_bytes(12)).'.tmp';
            $previous = umask(0077);
            try { $handle = fopen($temporary, 'x+b'); } finally { umask($previous); }
            if ($handle === false) { throw new RuntimeException('MCP-Einstellungen nicht gespeichert.'); }
            try {
                $json = json_encode($state, JSON_THROW_ON_ERROR)."\n";
                if (fwrite($handle,$json) !== strlen($json) || ! fflush($handle) || ! fsync($handle)) { throw new RuntimeException('MCP-Einstellungen nicht gespeichert.'); }
                fclose($handle); $handle=null; $this->assertDirectory();
                if (! rename($temporary,$this->directory.'/mcp-http-policy.json')) { throw new RuntimeException('MCP-Einstellungen nicht gespeichert.'); }
            } finally { if (is_resource($handle)) { fclose($handle); } if (is_file($temporary)) { unlink($temporary); } }
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    private function assertDirectory(): void
    {
        $stat=@lstat($this->directory ?? '');
        if ($stat===false || ($stat['mode'] & 0170000)!==0040000 || ($stat['mode'] & 0077)!==0
            || $stat['uid']!==posix_geteuid() || realpath($this->directory)!==$this->directory) { throw new RuntimeException('Private MCP-Ablage nicht verfügbar.'); }
    }
    private function openExisting(string $path,array $before)
    {
        if (($before['mode'] & 0170000)!==0100000 || ($before['mode'] & 0077)!==0 || $before['uid']!==posix_geteuid() || $before['size']>4096) { throw new RuntimeException('Private MCP-Datei nicht verfügbar.'); }
        $handle=@fopen($path,'rb');
        if ($handle===false) { throw new RuntimeException('Private MCP-Datei nicht verfügbar.'); }
        $after=fstat($handle);
        if ($after['dev']!==$before['dev'] || $after['ino']!==$before['ino']) { fclose($handle); throw new RuntimeException('Private MCP-Datei wurde geändert.'); }
        return $handle;
    }
    private function createOrOpenLock(string $path)
    {
        clearstatcache(true,$path);$before=@lstat($path);
        if ($before!==false) { return $this->openExisting($path,$before); }
        $previous=umask(0077);
        try { $handle=@fopen($path,'x+b'); } finally { umask($previous); }
        if ($handle===false) { throw new DomainException('MCP-Einstellungen neu laden.'); }
        return $handle;
    }
}
