<?php
require '/opt/chrono/framework/vendor/autoload.php';
umask(0077);
$dir=$argv[1] ?? '';
if (!preg_match('~^/var/lib/chrono-laravel/chrono-fresh-[a-f0-9]{24}$~D',$dir)) throw new RuntimeException('Explicit instance required.');

$admission=App\LegacyDatabase\RuntimeAdmission::fromConfiguration(json_decode(file_get_contents($dir.'/runtime-admission.json'),true,512,JSON_THROW_ON_ERROR));
$digest=App\Api\ExistingApiKeyAdmission::databaseProfileDigest($admission);
foreach(['terminal-devices.key','terminal-cards.key'] as $name) if (file_exists($dir.'/'.$name)) throw new RuntimeException('Existing key; no overwrite.');
if(file_exists('/var/lib/chrono-laravel/terminal.env')) throw new RuntimeException('Existing configuration; no overwrite.');
$metadata=[];
foreach(['device'=>'terminal-devices.key','card'=>'terminal-cards.key'] as $purpose=>$name) {
 $path=$dir.'/'.$name; $handle=fopen($path,'xb'); if(!$handle) throw new RuntimeException('Key creation refused.');
 $bytes=bin2hex(random_bytes(32)); if(fwrite($handle,$bytes)!==64 || !fflush($handle) || !fsync($handle)) throw new RuntimeException('Key write unconfirmed.'); fclose($handle);
 $stat=stat($path); $parent=stat($dir);
 $metadata[$purpose]=['version'=>1,'path'=>$path,'device'=>$stat['dev'],'inode'=>$stat['ino'],'owner'=>$stat['uid'],'mode'=>0600,'parent_device'=>$parent['dev'],'parent_inode'=>$parent['ino'],'sha256'=>hash('sha256',$bytes),'database_profile_sha256'=>$digest]; unset($bytes);
}
$env="FRAMEWORK_CHRONO_TERMINAL_HTTP_ENABLED=true\nFRAMEWORK_EXISTING_TERMINAL_KEYS_ENABLED=true\nFRAMEWORK_EXISTING_TERMINAL_KEY_ADMISSIONS=".base64_encode(json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR))."\n";
$handle=fopen('/var/lib/chrono-laravel/terminal.env','xb'); if(!$handle || fwrite($handle,$env)!==strlen($env) || !fflush($handle) || !fsync($handle)) throw new RuntimeException('Configuration write unconfirmed.'); fclose($handle);
echo "Private terminal roots and admission prepared.\n";
