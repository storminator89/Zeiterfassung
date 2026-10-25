<?php
require __DIR__.'/../lib/attendance.php';
function expect($name,$value) { if (!$value) { throw new RuntimeException($name); } echo "PASS $name\n"; }
$fall=strtotime('2026-10-25T00:15:00Z');
expect('Fall DST offset before transition', attendance_local($fall)==='2026-10-25T02:15:00+02:00');
expect('Fall DST offset after transition', attendance_local($fall+5400)==='2026-10-25T02:45:00+01:00');
$spring=strtotime('2026-03-29T00:30:00Z');
expect('Spring DST skips nonexistent hour', attendance_local($spring+3600)==='2026-03-29T03:30:00+02:00');
$db=new PDO('sqlite::memory:');
$s=$db->prepare("SELECT (strftime('%s',?) - strftime('%s',?))/60 - ?");
$s->execute([attendance_local($fall+5400),attendance_local($fall),30]);
expect('SQL reporting respects repeated hour and actual break',(int)$s->fetchColumn()===60);
$s->execute([attendance_local($spring+90000),attendance_local($spring),60]);
expect('SQL reporting supports shifts longer than 24h',(int)$s->fetchColumn()===1440);
expect('Local calendar day retained',substr(attendance_local(strtotime('2026-08-01T22:30:00Z')),0,10)==='2026-08-02');
