<?php
require getenv('CHRONO_AUTOLOAD') ?: dirname(__DIR__).'/framework/vendor/autoload.php';
use App\View\ExistingUi\HumanTime;
$cases=[
 [HumanTime::clock('2026-10-30T15:57:38+01:00'),'15:57'],
 [HumanTime::clock('2026-10-30T14:57:38Z'),'15:57'],
 [HumanTime::clock('2026-07-30T13:57:38Z'),'15:57'],
 [HumanTime::clock('2026-10-31T02:00:00+01:00','2026-10-30T22:00:00+01:00'),'31.10.2026, 02:00'],
 [HumanTime::clock('Läuft'),'Aktiv'],
 [HumanTime::clock('2026-10-30 · Gutschrift'),'Ganztägig'],
 [HumanTime::clock('2026-02-30T09:00:00+01:00'),'2026-02-30T09:00:00+01:00'],
 [HumanTime::full('2026-10-30T15:57:38+01:00'),'30.10.2026, 15:57'],
 [HumanTime::full('Ungültig'),'Ungültig'],
 [HumanTime::clock('2026-03-29 02:30:00'),'2026-03-29 02:30:00'],
];
foreach($cases as [$actual,$expected]) if($actual!==$expected)throw new RuntimeException('Display formatting regression.');
echo "PASS readable Berlin times, UTC/DST, overnight dates and unchanged invalid-value labels.\n";
