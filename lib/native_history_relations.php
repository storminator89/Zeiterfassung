<?php
require_once __DIR__.'/history_filters.php';
require_once __DIR__.'/history_correlation.php';
require_once __DIR__.'/history_export.php';

/** Native HTML/export opt-in: one owned read snapshot, fresh role and existing scopes. */
function chrono_native_history_bundle(PDO $db,array $filters,int $actorId): array {
    if ($db->inTransaction()) { throw new LogicException('History read requires an idle connection.'); }
    if (!$db->beginTransaction()) { throw new RuntimeException('History read unavailable.'); }
    try {
        $actorStatement=$db->prepare('SELECT id,role FROM users WHERE id=?');
        $actorStatement->execute([$actorId]);$actor=$actorStatement->fetch(PDO::FETCH_ASSOC);$actorStatement->closeCursor();
        if (!$actor || !in_array($actor['role'],['user','supervisor','admin'],true)) { throw new AttendanceError('Bitte erneut anmelden.',401); }
        $query=chrono_history_query($filters,$actorId,$actor['role']);
        $statement=$db->prepare($query['sql']);$statement->execute($query['params']);$anchorRows=$statement->fetchAll(PDO::FETCH_ASSOC);$statement->closeCursor();
        $result=['rows'=>$anchorRows,'correlation'=>chrono_history_correlations($db,$anchorRows,$actorId,$actor['role'])];
        if (!$db->commit()) { throw new RuntimeException('History read unavailable.'); }
        return $result;
    } catch (Throwable $readError) {
        if ($db->inTransaction()) { $db->rollBack(); }
        throw $readError;
    }
}

/** Validate/drop private ownership fields through the existing public export projection. */
function chrono_native_history_relations(array $bundle,array $values): array {
    $selected=chrono_history_correlated_export_page($bundle,$values);
    $relations=json_decode($selected['body'],true,512,JSON_THROW_ON_ERROR)['correlation'];
    foreach ($relations['events'] as &$event) {
        $event['received_label']=attendance_local($event['received_at']);
        $event['kind_label']=match($event['kind']) {
            'start'=>'Start','pause_start'=>'Pausenbeginn','pause_end'=>'Pausenende','end'=>'Ende',
        };
    }
    unset($event);
    return $relations;
}

/** Same existing filters/cursor; display opt-in alone is carried across page links. */
function chrono_native_history_relations_url(string $url,bool $enabled=true): string {
    return $enabled?$url.(str_contains($url,'?')?'&':'?').'relations=1':$url;
}
