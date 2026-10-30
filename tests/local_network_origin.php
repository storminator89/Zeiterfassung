<?php
require getenv('CHRONO_AUTOLOAD') ?: dirname(__DIR__).'/framework/vendor/autoload.php';
use App\FreshWeb\FreshWebConfiguration;
use App\FreshWeb\LocalNetworkOrigin;
use App\Setup\FirstAdminSetupFiles;
$origin=new ReflectionMethod(FreshWebConfiguration::class,'origin');
$setup=new ReflectionMethod(FirstAdminSetupFiles::class,'localOrigin');
foreach(['localhost','127.0.0.1','10.2.3.4','172.16.1.2','172.31.255.254','192.168.0.4'] as $host) {
    if (!LocalNetworkOrigin::allowsHttp($host)) throw new RuntimeException('Local host rejected.');
    if ($origin->invoke(null,"http://$host:8080")!=="http://$host:8080" || !$setup->invoke(null,"http://$host:8080")) throw new RuntimeException('Local origin rejected.');
}
foreach(['example.com','8.8.8.8','172.15.0.1','172.32.0.1','192.169.0.1','0.0.0.0'] as $host) {
    if(LocalNetworkOrigin::allowsHttp($host) || $setup->invoke(null,"http://$host:8080")) throw new RuntimeException('Public HTTP admitted.');
    try { $origin->invoke(null,"http://$host:8080"); throw new RuntimeException('Public HTTP admitted.'); } catch (LogicException) {}
}
foreach(['http://user@192.168.1.2','http://192.168.1.2/path','http://192.168.1.2?x=1','http://192.168.1.2:99999'] as $url) {
    try {$origin->invoke(null,$url);throw new RuntimeException('Noncanonical origin admitted.');}catch(LogicException){}
}
if($origin->invoke(null,'https://example.com')!=='https://example.com')throw new RuntimeException('HTTPS regression.');
echo "PASS local/private HTTP, public/alias rejection and HTTPS compatibility.\n";
