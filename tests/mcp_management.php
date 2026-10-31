<?php
require __DIR__.'/../framework/app/Mcp/ManagedProfile.php';
use App\Mcp\ManagedProfile;
function check(bool $condition,string $message):void {if(!$condition)throw new RuntimeException($message);}
function refuses(callable $fn,string $class):void {try{$fn();}catch(Throwable $e){check($e instanceof $class,get_class($e));return;}throw new RuntimeException('Expected refusal');}
$dir=sys_get_temp_dir().'/chrono-mcp-management-'.bin2hex(random_bytes(8));mkdir($dir,0700);$dir=realpath($dir);
try{
 $profile=new ManagedProfile($dir,[]);check(!$profile->read()['enabled'],'default off');
 $form=['mcp_management'=>'1','mcp_action'=>'enable','mcp_revision'=>'0','apps_enabled'=>'1'];$profile->change($form);
 $fresh=new ManagedProfile($dir,[]);check($fresh->read()['enabled']&&$fresh->read()['apps_enabled'],'fresh policy enabled');
 check((fileperms($dir.'/mcp-http-policy.json')&0777)===0600,'private mode');
 refuses(fn()=>$profile->change($form),DomainException::class);
 $form['mcp_revision']='1';$form['account_id']='2';refuses(fn()=>$profile->change($form),InvalidArgumentException::class);unset($form['account_id']);
 $form['mcp_action']='disable';$profile->change($form);check(!$fresh->read()['enabled'],'immediate disable');
 check(array_keys($fresh->read())===['revision','enabled','apps_enabled'],'no account or token selectors');
 unlink($dir.'/mcp-http-policy.json');file_put_contents($dir.'/target','{}');symlink($dir.'/target',$dir.'/mcp-http-policy.json');check($profile->status()['error'],'symlink refused');
 check(!(new ManagedProfile(null,[]))->status()['available'],'unmanaged readonly');
 echo "MCP HTTP policy: default-off, persistence, CAS, enable/disable, no account selectors, private files and symlink refusal passed.\n";
}finally{foreach(glob($dir.'/*')as$file)unlink($file);rmdir($dir);}
