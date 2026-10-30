<?php

namespace App\Vacation;

use App\Accounts\AccountLifecycleAuthority;
use App\Auth\AccountLifecycleWebIdentity;
use App\Auth\LegacyUser;
use App\LegacyDatabase\AttendanceWriter;
use App\LegacyDatabase\RuntimeDatabase;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\SQLiteConnection;
use InvalidArgumentException;
use LogicException;
use PDO;

/** Generation-bound existing Web transport. No request actor/role/path, raw handle or public callback. */
final readonly class VacationWeb
{
    public function __construct(private Factory $auth,private DatabaseManager $database,
        private RuntimeDatabase $runtime,private AccountLifecycleWebIdentity $identity,private Closure $writer) {}

    public function enabled():bool { return $this->runtime->isVacationWorkflowV7() && $this->identity->requiresGeneration(); }

    public function apply(array $input):array
    {
        $actor=$this->actor();$authority=$this->identity->capture($actor);
        if ($authority===null) { throw new AuthenticationException; }
        $writer=($this->writer)();
        if (!$writer instanceof AttendanceWriter) { throw new LogicException('An admitted vacation writer is required.'); }
        return $writer->vacationWorkflow($authority,$input);
    }

    /** One fresh admitted snapshot; selected rows are already authorized, never auth-bearing DTOs. */
    public function page(string $scope,string $state,int $page,?int $preview=null):array
    {
        if (!in_array($scope,['own','review'],true) || !in_array($state,['all','pending','approved','rejected','withdrawn','cancel_pending','cancelled'],true)
            || $page<1 || $page>1000000 || ($preview!==null && $preview<1)) { throw new InvalidArgumentException('Ungültige Antragsauswahl.'); }
        $authenticated=$this->actor();$authority=$this->identity->capture($authenticated);
        if ($authority===null) { throw new AuthenticationException; }
        $connection=$this->database->connection('chrono');
        if (!$connection instanceof SQLiteConnection || $connection->transactionLevel()!==0) { throw new LogicException; }
        $pdo=$connection->getPdo();if ($pdo->inTransaction()) { throw new LogicException; }
        return $connection->transaction(function () use($connection,$pdo,$authority,$scope,$state,$page,$preview):array {
            if ($connection->getRawPdo()!==$pdo) { throw new LogicException; }
            $s=$pdo->prepare('SELECT id,username,password,auth_source,role,account_status,auth_generation FROM users WHERE id=?');
            try {$s->execute([$authority->id]);$actor=$s->fetch(PDO::FETCH_ASSOC);}finally{$s->closeCursor();}
            if ($actor===false || !$authority->matches($actor)) { throw new AuthenticationException; }
            if ($scope==='review' && !in_array($actor['role'],['admin','supervisor'],true)) { throw new AuthorizationException('Nur aktuelle Vorgesetzte dürfen fremde Urlaubsanträge prüfen.'); }
            $arguments=[$authority->id];
            $where=$scope==='own'?'r.user_id=?':'r.user_id<>?';
            if ($scope==='review' && $actor['role']!=='admin') {$where.=' AND u.supervisor_id=?';$arguments[]=$authority->id;}
            if ($state!=='all') {$where.=' AND r.state=?';$arguments[]=$state;}
            $s=$pdo->prepare('SELECT COUNT(*) FROM vacation_requests r JOIN users u ON u.id=r.user_id WHERE '.$where);
            try {$s->execute($arguments);$total=$s->fetchColumn();}finally{$s->closeCursor();}
            if (!is_int($total) || $total<0) { throw new LogicException; }
            $pages=max(1,(int)ceil($total/25));$selected=min($page,$pages);
            $s=$pdo->prepare('SELECT r.id,r.user_id,r.start_date,r.end_date,r.state,r.revision,r.created_at,r.updated_at,u.username FROM vacation_requests r JOIN users u ON u.id=r.user_id WHERE '.$where.' ORDER BY r.id DESC LIMIT 25 OFFSET '.(($selected-1)*25));
            try {$s->execute($arguments);$rows=$s->fetchAll(PDO::FETCH_ASSOC);}finally{$s->closeCursor();}
            foreach ($rows as &$row) {
                $s=$pdo->prepare('SELECT id,revision,actor_id,action,from_state,to_state,reason,occurred_at FROM vacation_transitions WHERE request_id=? ORDER BY revision DESC LIMIT 11');
                try {$s->execute([$row['id']]);$history=$s->fetchAll(PDO::FETCH_ASSOC);}finally{$s->closeCursor();}
                $row['history_limited']=count($history)>10;$row['history']=array_slice($history,0,10);
                $row['credit_count']=(int)$pdo->query('SELECT COUNT(*) FROM vacation_credits WHERE request_id='.(int)$row['id'])->fetchColumn();
            }unset($row);
            $approval=null;
            if ($preview!==null) {
                if ($scope!=='review') { throw new InvalidArgumentException('Die Gutschriftvorschau gehört zur Fremdprüfung.'); }
                $approval=VacationWorkflow::approvalPreviewLocked($pdo,$authority,$preview);
            }
            if ($connection->getRawPdo()!==$pdo) { throw new LogicException; }
            return ['scope'=>$scope,'state'=>$state,'page'=>$selected,'pages'=>$pages,'total'=>$total,'rows'=>$rows,
                'can_review'=>in_array($actor['role'],['admin','supervisor'],true),'approval'=>$approval,
                'approval_request_id'=>$preview,'loaded_at'=>time()];
        },1);
    }

    private function actor():LegacyUser
    {
        if (!$this->enabled()) { throw new LogicException('Vacation workflow requires its complete admitted standard profile.'); }
        $actor=$this->auth->guard('web')->user();
        if (!$actor instanceof LegacyUser || (int)$actor->getKey()<1
            || !in_array($actor->role,['user','supervisor','admin'],true)) { throw new AuthenticationException; }
        return $actor;
    }
}
