<?php

namespace App\Console\Commands;

use App\LegacyDatabase\AttendanceWriter;
use App\LegacyDatabase\OfflineFiles;
use App\LegacyDatabase\RuntimeDatabase;
use App\LegacyDatabase\VacationWorkflowCohortUpgrade;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

/** Explicit offline standard-cohort operation; no web call, server start or silent migration. */
final class UpgradeVacationWorkflow extends Command
{
    protected $signature='chrono:vacation-workflow-v7
        {--instance= : Existing canonical private chrono-fresh cohort directory}
        {--backup= : Distinct byte-identical current stopped V5 database backup (original backup on resume)}
        {--offline : Confirm all Web/API/LDAP/Terminal/CLI consumers are stopped and drained}
        {--resume : Explicit forward resume of the exact private pending upgrade after inspection}
        {--channel-metadata= : Optional private canonical JSON of currently configured api, terminal and ldap_sync metadata}';
    protected $description='Upgrade the existing closed standard V5 cohort to the vacation workflow, preserving accounts, keys and bookings.';

    public function handle():int
    {
        try {
            $instance=$this->option('instance');$backup=$this->option('backup');
            if ($this->option('offline')!==true || !is_string($instance) || $instance==='' || !is_string($backup) || $backup===''
                || $this->laravel->make('config')->get('chrono_runtime.read_enabled',false)!==false
                || $this->laravel->make('config')->get('chrono_runtime.write_enabled',false)!==false
                || $this->laravel->bound(RuntimeDatabase::class) || $this->laravel->bound(AttendanceWriter::class)
                || $this->laravel->make('db')->getConnections()!==[]) { throw new RuntimeException; }
            $roots=[dirname(base_path()),public_path()];$files=new OfflineFiles($roots);
            $channels=['api'=>null,'terminal'=>null,'ldap_sync'=>null];$channelPath=$this->option('channel-metadata');
            if ($channelPath!==null) {
                if (!is_string($channelPath) || $channelPath==='') { throw new RuntimeException; }
                if (in_array(basename($channelPath),['jwt.key','encryption.key','terminal-devices.key',
                    'terminal-cards.key','framework-app-key.env','operator-setup-token.txt'],true)) {
                    throw new RuntimeException; // Known credentials are never a channel-metadata input.
                }
                $path=$files->validate($channelPath);$handle=@fopen($path,'rb');
                if ($handle===false) { throw new RuntimeException; }
                try {
                    $before=fstat($handle);$bytes=stream_get_contents($handle,32769);$after=fstat($handle);
                    clearstatcache(true,$path);$current=@lstat($path);
                    // Reading may legitimately advance atime. Identity, modes,
                    // content size and modification/change times must not move.
                    $keys=array_flip(['dev','ino','uid','mode','nlink','size','mtime','ctime']);
                    if ($before===false || $after===false || $current===false
                        || array_intersect_key($before,$keys)!==array_intersect_key($after,$keys)
                        || array_intersect_key($before,$keys)!==array_intersect_key($current,$keys)
                        || !is_string($bytes) || strlen($bytes)>32768 || strlen($bytes)!==$before['size']) { throw new RuntimeException; }
                    $channels=json_decode($bytes,true,32,JSON_THROW_ON_ERROR);
                    if (!is_array($channels) || $bytes!==\App\LegacyDatabase\VacationWorkflowUpgradeFiles::encode($channels)) { throw new RuntimeException; }
                } finally { fclose($handle); }
            }
            $mapping=json_decode(file_get_contents(dirname(base_path()).'/docs/framework/schema-map.json'),true,512,JSON_THROW_ON_ERROR);
            $result=(new VacationWorkflowCohortUpgrade($files,$mapping,$roots))->run($instance,$backup,true,$this->option('resume')===true,$channels);
            $this->line(json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Vacation upgrade not confirmed. Keep every consumer stopped; retain the private database, original backup and upgrade record. Inspect before an explicit forward resume; no automatic retry, restore or key reset.');
            return self::FAILURE;
        }
    }
}
