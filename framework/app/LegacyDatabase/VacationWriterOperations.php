<?php

namespace App\LegacyDatabase;

use App\Accounts\AccountLifecycleAuthority;
use App\Vacation\VacationCommand;
use App\Vacation\VacationWorkflow;
use Closure;
use LogicException;

/** One operation-scoped IMMEDIATE writer/audit/commit lifecycle shared with every existing channel. */
trait VacationWriterOperations
{
    public function vacationWorkflow(AccountLifecycleAuthority $actor,array $input):array
    {
        if ($this->vacationAdmission===null) { throw new LogicException('Vacation requests require their complete explicit profile.'); }
        $command=VacationCommand::fromArray($input);
        return $this->executeLocked(function () use($actor,$command):array {
            $this->requireVacationWriterProfile();
            $this->assertLifecycleWebPrincipalLocked($actor);
            $result=VacationWorkflow::applyLocked($this->pdo,$actor,$command,
                fn(string $reason,Closure $operation):mixed=>$this->context->run('web:'.$actor->id,$reason,$operation));
            // Final consistency is checked in the same transaction, never after a partial domain commit.
            $this->requireVacationWriterProfile();
            return $result;
        },lifecycleReady:true);
    }
}
