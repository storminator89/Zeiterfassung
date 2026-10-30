<?php

namespace App\View\ExistingUi;

final readonly class VacationCopy
{
    private array $copy;
    public function __construct(string $language)
    {
        $language=in_array($language,['de','en','zh'],true)?$language:'de';
        $this->copy=require dirname(__DIR__,3).'/lang/'.$language.'/vacation.php';
    }
    public function text(string $key):string { return $this->copy[$key]??$key; }
    public function state(string $state):string { return $this->text('state_'.$state); }
    public function action(string $action):string { return $this->text('action_'.$action); }
}
