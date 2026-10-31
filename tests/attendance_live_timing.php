<?php
require dirname(__DIR__).'/framework/app/Chrono/LiveAttendanceTiming.php';
use App\Chrono\LiveAttendanceTiming;
function check(bool $value, string $label): void { if (!$value) { throw new RuntimeException($label); } }
$session=['id'=>3,'record_id'=>7,'started_at'=>1000,'ended_at'=>null];
$working=['state'=>'working','record_id'=>7];
$paused=['state'=>'paused','record_id'=>7];
$closed=[['session_id'=>3,'started_at'=>1100,'ended_at'=>1131],['session_id'=>3,'started_at'=>1200,'ended_at'=>1230]];
$value=LiveAttendanceTiming::fromSnapshot($working,$session,$closed,5000);
check($value['elapsed_work_seconds']===3939 && $value['closed_pause_seconds']===61,'Exact seconds, not rounded stored pause minutes.');
check($value['record_id']==='7' && $value['as_of_epoch']===5000,'Stable record and server-time binding.');
$open=[...$closed,['session_id'=>3,'started_at'=>4900,'ended_at'=>null]];
check(LiveAttendanceTiming::fromSnapshot($paused,$session,$open,5000)['elapsed_work_seconds']===3839,'Freeze at current break start.');
check(LiveAttendanceTiming::fromSnapshot($paused,$session,$open,5060)['elapsed_work_seconds']===3839,'Paused duration remains unchanged across server reads.');
$completed=[...$closed,['session_id'=>3,'started_at'=>4900,'ended_at'=>5000]];
check(LiveAttendanceTiming::fromSnapshot($working,$session,$completed,5060)['elapsed_work_seconds']===3899,'Resume counts new work and excludes exact completed pause.');
check(LiveAttendanceTiming::fromSnapshot($working,$session,[],1000)['elapsed_work_seconds']===0,'Start boundary.');
foreach ([
 [$working,null,[],5000], [['state'=>'absent','record_id'=>null],$session,[],5000],
 [$working,$session,$open,5000], [$paused,$session,$closed,5000],
 [$working,$session,$closed,999], [$working,$session,[['session_id'=>99,'started_at'=>1100,'ended_at'=>1131]],5000],
 [$working,$session,[['session_id'=>3,'started_at'=>1100,'ended_at'=>5100]],5000],
 [$working,$session,[['session_id'=>3,'started_at'=>1100,'ended_at'=>1099]],5000],
 [$working,$session,[...$closed,['session_id'=>3,'started_at'=>1220,'ended_at'=>1250]],5000],
 [$working,$session,[['session_id'=>3,'started_at'=>1100]],5000],
 [$working,[...$session,'ended_at'=>5000],[],5000],
] as $case) check(LiveAttendanceTiming::fromSnapshot(...$case)===null,'Unknown/inconsistent timeline must not invent work time.');
echo "PASS exact own-session net timing, open/closed pause boundaries and invalid/legacy absence guards.\n";
