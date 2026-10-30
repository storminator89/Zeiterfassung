<?php
namespace App\View\ExistingUi;

/** Fixed, own-only display values. Never exposes a raw row or claims contractual finality. */
final readonly class OwnPeriodComparison
{
    private array $labels;
    public ?array $result;

    public function __construct(public string $from,public string $through,string $language,?array $snapshot=null,
        public bool $requested=false,public bool $invalid=false,public bool $unavailable=false)
    {
        if (!in_array($language,['de','en','zh'],true)) { throw new \InvalidArgumentException('Invalid comparison language.'); }
        foreach ([$from,$through] as $day) {
            if ($day!=='' && preg_match('/\A(?!0000)[0-9]{4}-[0-9]{2}-[0-9]{2}\z/',$day)!==1) {
                throw new \InvalidArgumentException('Invalid comparison date.');
            }
        }
        $this->labels=require dirname(__DIR__,3).'/lang/'.$language.'/own-period-comparison.php';
        if ($snapshot===null) { $this->result=null;return; }
        if (($snapshot['from']??null)!==$from || ($snapshot['through']??null)!==$through
            || !is_string($snapshot['loaded_at']??null)
            || preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}[+-][0-9]{2}:[0-9]{2}\z/',$snapshot['loaded_at'])!==1
            || !is_array($snapshot['issues']??null) || !is_array($snapshot['days']??null) || count($snapshot['days'])>366
            || !array_key_exists('totals',$snapshot)) { throw new \UnexpectedValueException('Invalid comparison snapshot.'); }
        $issues=[];
        foreach (['unknown_start','open','invalid','conflict','model'] as $key) { $issues[$key]=self::number($snapshot['issues'][$key]??null); }
        $totals=null;$days=[];
        if ($snapshot['totals']!==null) {
            if (!is_array($snapshot['totals']) || array_sum($issues)!==0 || $snapshot['days']===[]) {
                throw new \UnexpectedValueException('Invalid comparison totals.');
            }
            $totals=[];
            foreach (['work_minutes','credit_minutes','target_minutes','unrecorded_target_days'] as $key) { $totals[$key]=self::number($snapshot['totals'][$key]??null); }
            $totals['difference_minutes']=self::number($snapshot['totals']['difference_minutes']??null,true);
            foreach ($snapshot['days'] as $day=>$values) {
                if (!is_string($day) || $day<$from || $day>$through || preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/',$day)!==1 || !is_array($values)) {
                    throw new \UnexpectedValueException('Invalid comparison day.');
                }
                $days[$day]=[];
                foreach (['work_minutes','credit_minutes','target_minutes','records'] as $key) { $days[$day][$key]=self::number($values[$key]??null); }
            }
        } elseif ($snapshot['days']!==[] || array_sum($issues)===0) { throw new \UnexpectedValueException('Invalid unavailable comparison.'); }
        $this->result=['loaded_at'=>$snapshot['loaded_at'],'issues'=>$issues,'totals'=>$totals,'days'=>$days];
    }

    private static function number(mixed $value,bool $signed=false): int
    {
        if (!is_int($value) || (!$signed && $value<0) || $value===PHP_INT_MIN) { throw new \UnexpectedValueException('Invalid comparison number.'); }
        return $value;
    }

    public function text(string $key): string
    {
        return $this->labels[$key]??throw new \InvalidArgumentException('Invalid comparison label.');
    }

    public function minutes(int $value): string
    {
        return ($value<0?'-':'').sprintf('%02d:%02d',intdiv(abs($value),60),abs($value)%60);
    }
}
