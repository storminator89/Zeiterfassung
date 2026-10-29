<?php
namespace App\View\ExistingUi;

/** Minimal own counts/IDs only; no account, original JSON, approval or closing authority. */
final readonly class OwnMonthReview
{
    private array $labels;
    public ?array $result;

    public function __construct(public string $month, string $language, ?array $snapshot=null,
        public bool $requested=false, public bool $invalid=false, public bool $unavailable=false)
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
        $samples=[];
        foreach ($snapshot['samples'] as $row) {
            if (!is_array($row) || !in_array($row['kind']??null,['open','request'],true)
                || !in_array($row['relation']??null,['selected','earlier','unclear'],true)
                || ($row['kind']==='request' && $row['relation']==='earlier')
                || !is_string($row['id']??null) || preg_match('/\A[1-9][0-9]*\z/',$row['id'])!==1
                || filter_var($row['id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])===false) {
                throw new \UnexpectedValueException('Invalid own month row.');
            }
            $samples[]=['kind'=>$row['kind'],'id'=>$row['id'],'relation'=>$row['relation']];
        }
        $this->result=['month'=>$month,'loaded_at'=>$snapshot['loaded_at'],'counts'=>$counts,'samples'=>$samples,'limited'=>$snapshot['limited']];
    }

    public function text(string $key): string
    {
        if (!isset($this->labels[$key])) { throw new \InvalidArgumentException('Invalid own month label.'); }
        return $this->labels[$key];
    }
}
