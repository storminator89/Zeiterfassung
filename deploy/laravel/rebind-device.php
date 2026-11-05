<?php
require '/opt/chrono/framework/vendor/autoload.php';
// Operator-only, stopped standard local installation. Never run from a web route.
umask(0077);
try {
$d=$argv[1] ?? '';
if (posix_geteuid() === 0) throw new RuntimeException('Non-root operator required');
if (($argv[2]??'')!=='--offline'||!preg_match('~^/var/lib/chrono-laravel/chrono-fresh-[a-f0-9]{24}$~D',$d))throw new RuntimeException('Offline instance required');
if (file_exists($d.'/device-rebind.pending')) throw new RuntimeException('Pending recovery; retain backup and remain stopped');
if (realpath($d) !== $d || (lstat($d)['mode'] & 0777) !== 0700 || lstat($d)['uid'] !== posix_geteuid()) throw new RuntimeException('Private instance mismatch');
$lock=fopen($d.'/setup-operator.lock','r+');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Busy');
if(!is_file($d.'/setup-closed'))throw new RuntimeException('Closed setup required');
foreach(['-wal','-shm','-journal']as$s)if(file_exists($d.'/application.sqlite'.$s))throw new RuntimeException('Database sidecar');
$raw=file_get_contents($d.'/runtime-admission.json');$m=json_decode($raw,true,512,JSON_THROW_ON_ERROR);$old=App\LegacyDatabase\RuntimeAdmission::fromConfiguration($m);if ($old->path !== $d.'/application.sqlite') throw new RuntimeException('Unexpected database path');$st=lstat($d.'/application.sqlite');
if($st['ino']!==$old->inode||$st['uid']!==posix_geteuid()||($st['mode']&0777)!==0600||$st['nlink']!==1||is_link($d.'/application.sqlite'))throw new RuntimeException('Database identity changed');
if($st['dev']===$old->device) { echo "Device identity unchanged.\n"; exit(0); }
$m['device']=$st['dev'];$new=App\LegacyDatabase\RuntimeAdmission::fromConfiguration($m);
$oldHash=App\Api\ExistingApiKeyAdmission::databaseProfileDigest($old);$newHash=App\Api\ExistingApiKeyAdmission::databaseProfileDigest($new);
$updates=[$d.'/runtime-admission.json'=>json_encode($m,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n"];
function rebound(array $r,$old,$new,$oldHash,$newHash,$d):array {
 if(($r['device']??null)!==$old->device||($r['database_profile_sha256']??null)!==$oldHash||dirname($r['path'])!==$d)throw new RuntimeException('Unknown admission');
 $s=lstat($r['path']);$p=lstat($d);
 if(realpath($r['path'])!==$r['path']||$s['ino']!==$r['inode']||$s['uid']!==$r['owner']||($s['mode']&0777)!==$r['mode']||$s['nlink']!==1||$p['uid']!==posix_geteuid()||($p['mode']&0777)!==0700||$s['dev']!==$new->device||$p['ino']!==$r['parent_inode']||$r['parent_device']!==$old->device||!hash_equals($r['sha256'],hash_file('sha256',$r['path'])))throw new RuntimeException('Pinned file differs');
 $r['device']=$new->device;$r['parent_device']=$p['dev'];$r['database_profile_sha256']=$newHash;return $r;
}
$f=$d.'/framework-api-key.env';if(is_file($f)){
 $b=file_get_contents($f);if(!preg_match('/\AFRAMEWORK_FRESH_API_KEY_ADMISSION=([^\n]+)\n\z/',$b,$x))throw new RuntimeException('Unknown API env');
 $r=rebound(json_decode(base64_decode($x[1],true),true,32,JSON_THROW_ON_ERROR),$old,$new,$oldHash,$newHash,$d);
 App\Api\ExistingApiKeyAdmission::fromConfiguration($r,$new);
 $updates[$f]='FRAMEWORK_FRESH_API_KEY_ADMISSION='.base64_encode(json_encode($r,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR))."\n";
}
$f=$d.'/api-management-key.claim';if(is_file($f)){if(file_get_contents($f)!=='api-management-v1:'.$oldHash."\n")throw new RuntimeException('Unknown claim');$updates[$f]='api-management-v1:'.$newHash."\n";}
$f=dirname($d).'/terminal.env';$b=file_get_contents($f);if(!preg_match('/^FRAMEWORK_EXISTING_TERMINAL_KEY_ADMISSIONS=(.+)$/m',$b,$x))throw new RuntimeException('Terminal metadata missing');
$r=json_decode(base64_decode($x[1],true),true,32,JSON_THROW_ON_ERROR);foreach(['device','card']as$purpose)$r[$purpose]=rebound($r[$purpose],$old,$new,$oldHash,$newHash,$d);
$updates[$f]=str_replace($x[1],base64_encode(json_encode($r,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)),$b);
// Unsupported LDAP admissions must not be partly rebound.
foreach(['ldap-settings-root-admission.json','vacation-v7-upgrade.json']as$n)if(file_exists($d.'/'.$n))throw new RuntimeException('Separate recovery required');
// All expected metadata has been validated before backup or replacement.
$backup=dirname($d).'/device-rebind-backup-'.gmdate('YmdHis').'-'.bin2hex(random_bytes(4));
if(!mkdir($backup,0700)) throw new RuntimeException('Backup failed');
function copyPrivateTree(string $source,string $target):void {
    if (!mkdir($target,0700)) throw new RuntimeException('Backup directory failed');
    foreach (new DirectoryIterator($source) as $entry) {
        if ($entry->isDot()) continue;
        $path=$entry->getPathname(); $stat=lstat($path);
        if ($entry->isLink() || $stat['uid']!==posix_geteuid()) throw new RuntimeException('Unsupported backup entry');
        $dest=$target.'/'.$entry->getFilename();
        if ($entry->isDir()) { copyPrivateTree($path,$dest); continue; }
        if (!$entry->isFile() || $stat['nlink']!==1 || !copy($path,$dest)) throw new RuntimeException('Backup file failed');
        chmod($dest,0600);
        if (!hash_equals(hash_file('sha256',$path),hash_file('sha256',$dest))) throw new RuntimeException('Backup differs');
    }
}
copyPrivateTree($d,$backup.'/instance');
if(!copy(dirname($d).'/terminal.env',$backup.'/terminal.env')) throw new RuntimeException('Terminal backup failed');
chmod($backup.'/terminal.env',0600);
$dbhash=hash_file('sha256',$d.'/application.sqlite');
$fence=$d.'/device-rebind.pending';
$h=fopen($fence,'xb');
if(!$h || fwrite($h,json_encode(['backup'=>$backup,'database_sha256'=>$dbhash],JSON_THROW_ON_ERROR))===false || !fflush($h) || !fsync($h)) throw new RuntimeException('Recovery fence failed');
fclose($h);
// Publish runtime admission last; any interrupted replacement keeps the fence.
$manifest=$d.'/runtime-admission.json'; $manifestBytes=$updates[$manifest]; unset($updates[$manifest]); $updates[$manifest]=$manifestBytes;
foreach($updates as $path=>$bytes) {
    $stage=$path.'.device-rebind-stage'; $h=fopen($stage,'xb');
    if(!$h || fwrite($h,$bytes)!==strlen($bytes) || !fflush($h) || !fsync($h)) throw new RuntimeException('Staging failed; remain stopped');
    fclose($h); chmod($stage,0600);
    if(!rename($stage,$path)) throw new RuntimeException('Replacement failed; remain stopped');
}
if(!hash_equals($dbhash,hash_file('sha256',$d.'/application.sqlite'))) throw new RuntimeException('Database changed');
$runtime=new App\LegacyDatabase\RuntimeDatabase($new,new App\LegacyDatabase\OfflineFiles(['/opt/chrono/framework','/opt/chrono/framework/public','/opt/chrono']));
$connection=$runtime->readConnection($new->configuration(),'chrono');
$connection->select('SELECT 1'); $connection->disconnect();
if(!unlink($fence)) throw new RuntimeException('Recovery completion unconfirmed');
echo "Device metadata rebound; verified private backup retained; database bytes unchanged.\n";
} catch (Throwable $error) {
    fwrite(STDERR,"Device recovery refused: ".$error->getMessage().". Keep consumers stopped; inspect the private backup.\n");
    exit(1);
}
