<?php
require __DIR__.'/../framework/app/Chrono/HolidayApiSource.php';
use App\Chrono\HolidayApiSource;
function assertRefused(callable $fn):void {try{$fn();}catch(Throwable){return;}throw new RuntimeException('Expected refusal');}
$valid='{"Neujahr":{"datum":"2026-01-01","hinweis":""},"Regional":{"datum":null,"hinweis":"Nur örtlich"}}';
if(HolidayApiSource::validatedResponse('2026',200,'application/json; charset=utf-8',$valid)!==$valid)throw new RuntimeException('Source changed');
foreach([['2026',302,'application/json',$valid],['2026',200,'text/html',$valid],['2026',200,'application/json',null],['2026',200,'application/json',str_repeat('x',131073)],['2025',200,'application/json',$valid],['2026',200,'application/json','{"A":{"datum":"2026-01-01","hinweis":""},"A":{"datum":"2026-01-02","hinweis":""}}'],['2026&nur_land=BY',200,'application/json',$valid],['0',200,'application/json',$valid]]as$args)assertRefused(fn()=>HolidayApiSource::validatedResponse(...$args));
assertRefused(fn()=>HolidayApiSource::year(['2026']));
echo "PASS fixed-source year validation, bounded JSON response, redirects/MIME rejection and existing strict year/duplicate/hint parser\n";
