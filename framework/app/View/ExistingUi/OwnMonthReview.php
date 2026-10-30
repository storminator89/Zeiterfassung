<?php
namespace App\View\ExistingUi;

/** Minimal own counts/IDs only; no account, original JSON, approval or closing authority. */
final readonly class OwnMonthReview
{
    private array $labels;
    public ?array $result;

    public function __construct(public string $month, string $language, ?array $snapshot=null,
        public bool $requested=false, public bool $invalid=false, public bool $unavailable=false,
        public bool $findingsAvailable=false)
    {
        if (!in_array($language,['de','en','zh'],true)
            || ($month!=='' && preg_match('/\A(?!0000)[0-9]{4}-(?:0[1-9]|1[0-2])\z/',$month)!==1)) {
            throw new \InvalidArgumentException('Invalid own month presentation.');
        }
        $this->labels=require dirname(__DIR__,3).'/lang/'.$language.'/own-month-review.php';
        if ($snapshot===null) { $this->result=null;return; }
        if (($snapshot['month']??null)!==$month || !is_string($snapshot['loaded_at']??null)
            || preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}[+-][0-9]{2}:[0-9]{2}\z/',$snapshot['loaded_at'])!==1
            || !is_array($snapshot['counts']??null) || !is_array($snapshot['samples']??null)
            || count($snapshot['samples'])>5 || !is_bool($snapshot['limited']??null)) {
            throw new \UnexpectedValueException('Invalid own month snapshot.');
        }
        $counts=[];
        foreach (['open_selected','open_earlier','open_unclear','pending_selected','pending_unclear'] as $key) {
            $value=$snapshot['counts'][$key]??null;
            if (!is_int($value) || $value<0) { throw new \UnexpectedValueException('Invalid own month count.'); }
            $counts[$key]=$value;
        }
        // Absent on older optional reader snapshots: do not invent a zero/clean check.
        $closedCounts=null;
        if (array_key_exists('closed_counts',$snapshot)) {
            if (!is_array($snapshot['closed_counts'])) { throw new \UnexpectedValueException('Invalid closed month counts.'); }
            $closedCounts=[];
            foreach (['selected','unclear'] as $key) {
                $value=$snapshot['closed_counts'][$key]??null;
                if (!is_int($value) || $value<0) { throw new \UnexpectedValueException('Invalid closed month count.'); }
                $closedCounts[$key]=$value;
            }
        }
        $samples=self::rows($snapshot['samples'],$closedCounts);
        $result=['month'=>$month,'loaded_at'=>$snapshot['loaded_at'],'counts'=>$counts,'closed_counts'=>$closedCounts,'samples'=>$samples,'limited'=>$snapshot['limited']];
        if (array_key_exists('findings',$snapshot)) {
            $finding=$snapshot['findings'];
            if (!$findingsAvailable || $closedCounts===null || !is_array($finding)
                || !is_int($finding['page']??null) || $finding['page']<1 || $finding['page']>intdiv(PHP_INT_MAX,20)
                || ($finding['per_page']??null)!==20 || !is_int($finding['total']??null) || $finding['total']<0
                || $finding['total']!==array_sum($counts)+array_sum($closedCounts)
                || ($finding['total_pages']??null)!==($finding['total']===0?1:intdiv($finding['total']-1,20)+1)
                || !is_array($finding['rows']??null)
                || count($finding['rows'])!==min(20,max(0,$finding['total']-($finding['page']-1)*20))) {
                throw new \UnexpectedValueException('Invalid own month finding page.');
            }
            $result['findings']=['page'=>$finding['page'],'per_page'=>20,'total'=>$finding['total'],
                'total_pages'=>$finding['total_pages'],'rows'=>self::rows($finding['rows'],$closedCounts)];
        }
        $this->result=$result;
    }

    private static function rows(array $rows,?array $closedCounts): array
    {
        $result=[];$seen=[];
        foreach ($rows as $row) {
            if (!is_array($row) || !in_array($row['kind']??null,['open','request','closed'],true)
                || !in_array($row['relation']??null,['selected','earlier','unclear'],true)
                || (in_array($row['kind'],['request','closed'],true) && $row['relation']==='earlier')
                || ($row['kind']==='closed' && $closedCounts===null)
                || !is_string($row['id']??null) || preg_match('/\A[1-9][0-9]*\z/',$row['id'])!==1
                || filter_var($row['id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])===false) {
                throw new \UnexpectedValueException('Invalid own month row.');
            }
            $key=$row['kind'].':'.$row['id'];
            if (isset($seen[$key])) { throw new \UnexpectedValueException('Duplicate own month finding.'); }
            $seen[$key]=true;$result[]=['kind'=>$row['kind'],'id'=>$row['id'],'relation'=>$row['relation']];
        }
        return $result;
    }

    public function findingUrl(int $page): string
    {
        if ($this->month==='' || $page<1 || $page>intdiv(PHP_INT_MAX,20)) {
            throw new \InvalidArgumentException('Invalid own month navigation.');
        }
        return 'attendance.php?month_review='.rawurlencode($this->month).'&month_review_page='.$page.'#own-month-review';
    }

    public function text(string $key): string
    {
        if (!isset($this->labels[$key])) { throw new \InvalidArgumentException('Invalid own month label.'); }
        return $this->labels[$key];
    }
}
