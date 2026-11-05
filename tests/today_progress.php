<?php
require __DIR__.'/../framework/app/Chrono/TodayProgress.php';
use App\Chrono\TodayProgress;
function check($condition):void { if(!$condition)throw new RuntimeException('Today progress mismatch'); }
$day='2026-11-05';$now=strtotime('2026-11-05T12:00:00+01:00');
$row=['id'=>1,'user_id'=>1,'startzeit'=>'2026-11-05T08:00:00+01:00','endzeit'=>'2026-11-05T09:00:00+01:00','pause'=>10,'absence_kind'=>null];
$p=TodayProgress::fromRecords([$row],['state'=>'absent'],null,$day,480,$now);
check($p['work_seconds']===3000 && $p['credit_seconds']===0 && $p['target_seconds']===28800 && !$p['advancing']);
$live=['started_at_epoch'=>strtotime('2026-11-05T11:00:00+01:00'),'elapsed_work_seconds'=>2400];
$p=TodayProgress::fromRecords([$row],['state'=>'working'],$live,$day,480,$now);check($p['work_seconds']===5400 && $p['advancing']);
$p=TodayProgress::fromRecords([$row],['state'=>'paused'],$live,$day,480,$now);check($p['work_seconds']===5400 && !$p['advancing']);
$credit=$row;$credit['id']=2;$credit['absence_kind']='Urlaub';$credit['startzeit']='2026-11-05T00:00:00+01:00';$credit['endzeit']='2026-11-05T08:00:00+01:00';$credit['pause']=0;
$p=TodayProgress::fromRecords([$credit],['state'=>'absent'],null,$day,480,$now);check($p['credit_seconds']===28800 && $p['work_seconds']===0);
check(TodayProgress::fromRecords([$row,$credit],['state'=>'absent'],null,$day,480,$now)===null);
check(TodayProgress::fromRecords([$credit],['state'=>'working'],$live,$day,480,$now)===null);
$overnight=$row;$overnight['startzeit']='2026-11-04T23:00:00+01:00';check(TodayProgress::fromRecords([$overnight],['state'=>'absent'],null,$day,480,$now)===null);
$bad=$row;$bad['startzeit']='broken';check(TodayProgress::fromRecords([$bad],['state'=>'absent'],null,$day,480,$now)===null);
check(TodayProgress::fromRecords([],['state'=>'working'],null,$day,480,$now)===null);
check(TodayProgress::fromRecords([],['state'=>'absent'],null,$day,0,$now)['target_seconds']===0);
echo "PASS today completed work, exact paused/running display, credits, conflicts, overnight/invalid refusal and free day\n";
