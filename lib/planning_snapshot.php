<?php

// Definitions only: no bootstrap, connection, transaction lifecycle or writes.
require_once __DIR__.'/practice.php';

/**
 * Internal operation-local plan resolver, consumed synchronously in the caller's
 * already-authorized read snapshot. Never retain/export as an auth or request cache.
 * Only requested holiday dates and this owner's model history up to the final day.
 */
function planned_minutes_snapshot(PDO $db,int $user,string $firstDay,string $lastDay): Closure {
    if (!$db->inTransaction()) { throw new LogicException('An existing planning read snapshot is required.'); }
    practice_date($firstDay);practice_date($lastDay);
    if (strcmp($firstDay,$lastDay)>0) { throw new LogicException('Ordered planning bounds are required.'); }

    $statement=$db->prepare('SELECT datum FROM Feiertage WHERE datum>=? AND datum<=?');
    $statement->execute([$firstDay,$lastDay]);$holidays=[];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) { $holidays[$row['datum']]=true; }
    $statement->closeCursor();

    // ASC plus the last eligible row is exactly the existing DESC/id DESC LIMIT1
    // rule. Include older versions, not only models beginning inside the range.
    $statement=$db->prepare('SELECT id,valid_from,minutes_json FROM work_models WHERE user_id=? AND valid_from<=? ORDER BY valid_from ASC,id ASC');
    $statement->execute([$user,$lastDay]);$models=$statement->fetchAll(PDO::FETCH_ASSOC);$statement->closeCursor();
    $owner=att_one($db,'SELECT regelarbeitszeit FROM users WHERE id=?',[$user]);

    return static function (string $day) use ($firstDay,$lastDay,$holidays,$models,$owner): int {
        $date=practice_date($day);
        if (strcmp($day,$firstDay)<0 || strcmp($day,$lastDay)>0) {
            throw new LogicException('Planning day is outside this operation snapshot.');
        }
        if (isset($holidays[$day])) { return 0; }
        $low=0;$high=count($models)-1;$model=null;
        while ($low<=$high) {
            $middle=intdiv($low+$high,2);
            if (strcmp($models[$middle]['valid_from'],$day)<=0) {
                $model=$models[$middle];$low=$middle+1;
            } else { $high=$middle-1; }
        }
        return planned_minutes_value($date,$model,$owner);
    };
}
