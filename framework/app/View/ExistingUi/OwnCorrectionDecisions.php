<?php
namespace App\View\ExistingUi;

/** Minimal own request metadata only; no subject selector, acknowledgment or approval authority. */
final readonly class OwnCorrectionDecisions
{
    private array $labels;
    public ?array $result;

    public function __construct(string $language,?array $snapshot=null,public bool $unavailable=false)
    {
        if (!in_array($language,['de','en','zh'],true)) { throw new \InvalidArgumentException('Unsupported own decision language.'); }
        $this->labels=require dirname(__DIR__,3).'/lang/'.$language.'/own-correction-decisions.php';
        if ($snapshot===null) { $this->result=null;return; }
        if (!is_string($snapshot['loaded_at']??null)
            || preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}[+-][0-9]{2}:[0-9]{2}\z/',$snapshot['loaded_at'])!==1) {
            throw new \UnexpectedValueException('Invalid own decision snapshot.');
        }
        $result=['loaded_at'=>$snapshot['loaded_at']];$seen=[];
        foreach (['recent','undated'] as $group) {
            if (!is_array($snapshot[$group]??null) || count($snapshot[$group])>5 || !is_bool($snapshot['more_'.$group]??null)) {
                throw new \UnexpectedValueException('Invalid own decision group.');
            }
            $result[$group]=[];$result['more_'.$group]=$snapshot['more_'.$group];
            foreach ($snapshot[$group] as $row) {
                if (!is_array($row) || !is_string($row['request_id']??null)
                    || preg_match('/\A[1-9][0-9]*\z/',$row['request_id'])!==1
                    || filter_var($row['request_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])===false
                    || isset($seen[$row['request_id']]) || !in_array($row['status']??null,['approved','rejected','withdrawn'],true)
                    || !array_key_exists('decision_epoch',$row)
                    || ($group==='recent' && !is_int($row['decision_epoch']))
                    || ($group==='undated' && $row['decision_epoch']!==null)) { throw new \UnexpectedValueException('Invalid own decision item.'); }
                $seen[$row['request_id']]=true;$date=$label=null;
                if ($group==='recent') {
                    $instant=new \DateTimeImmutable('@'.$row['decision_epoch']);
                    $local=$instant->setTimezone(new \DateTimeZone('Europe/Berlin'));
                    if (preg_match('/\A(?!0000)[0-9]{4}\z/',$instant->format('Y'))!==1
                        || preg_match('/\A(?!0000)[0-9]{4}\z/',$local->format('Y'))!==1) { throw new \UnexpectedValueException('Invalid own decision time.'); }
                    $date=$instant->format('Y-m-d\TH:i:s\Z');$label=$local->format('d.m.Y H:i:s T');
                }
                $result[$group][]=['request_id'=>$row['request_id'],'status'=>$row['status'],'datetime'=>$date,'time_label'=>$label,
                    'url'=>'adjustments.php?request_status=all&own_requests=1&request_id='.rawurlencode($row['request_id'])];
            }
        }
        $this->result=$result;
    }

    public function text(string $key): string
    {
        return $this->labels[$key]??throw new \InvalidArgumentException('Unknown own decision label.');
    }
}
