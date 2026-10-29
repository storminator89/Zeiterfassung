<?php

namespace App\Api\Management;

use App\Accounts\AccountLifecycleAuthority;
use DomainException;
use LogicException;
use PDO;
use UnexpectedValueException;

/**
 * Reserved append-only policy events in the existing audit log, not new V5 DDL.
 * Caller owns an admitted V5 read snapshot or the sole IMMEDIATE writer; this
 * class never opens, begins, commits, retries, provisions or changes auth.
 */
final class ApiPolicyJournal
{
    public function read(PDO $db, bool $legacyDefaultEnabled): ApiPolicyState
    {
        $this->requireV5Transaction($db);
        $statement = $db->query("SELECT id,occurred_at,actor,operation,record_id,user_id,before_json,after_json FROM audit_log WHERE entity='api_service_policy' ORDER BY id DESC LIMIT 2");
        try { $rows = $statement->fetchAll(PDO::FETCH_ASSOC); }
        finally { $statement->closeCursor(); }
        if ($rows === []) { return new ApiPolicyState($legacyDefaultEnabled, $legacyDefaultEnabled, 0); }
        $states = [];
        foreach ($rows as $row) {
            if (! is_int($row['id']) || $row['id'] < 1 || $row['operation'] !== 'UPDATE'
                || $row['record_id'] !== 1 || $row['user_id'] !== 0
                || ! is_string($row['before_json']) || ! is_string($row['after_json'])) {
                throw new UnexpectedValueException('Invalid API policy journal.');
            }
            $before = ApiPolicyState::fromJson($row['before_json']);
            $after = ApiPolicyState::fromJson($row['after_json']);
            if ($before->revision === PHP_INT_MAX || $after->revision !== $before->revision + 1
                || $after->revision < 1 || $after->updatedAt !== $row['occurred_at']
                || $row['actor'] !== 'web:'.$after->updatedBy || ($before->keyPrepared && ! $after->keyPrepared)) {
                throw new UnexpectedValueException('Invalid API policy journal.');
            }
            $states[] = [$before, $after];
        }
        if (count($states) === 1 ? $states[0][0]->revision !== 0
            : ($rows[0]['id'] <= $rows[1]['id'] || $states[0][0]->values() !== $states[1][1]->values())) {
            throw new UnexpectedValueException('Discontinuous API policy journal.');
        }
        return $states[0][1];
    }

    /** Check CAS/authority BEFORE a caller may reserve files or generate entropy. */
    public function requireChangeLocked(PDO $db, AccountLifecycleAuthority $actor, ApiPolicyChange $change,
        bool $legacyDefaultEnabled): ApiPolicyState
    {
        $this->requireV5Transaction($db);
        $s = $db->prepare('SELECT id,username,password,role,auth_source,account_status,auth_generation FROM users WHERE id=?');
        try { $s->execute([$actor->id]); $row = $s->fetch(PDO::FETCH_ASSOC); }
        finally { $s->closeCursor(); }
        if ($row === false || ! $actor->matches($row) || ! AccountLifecycleAuthority::locallyLoginEligibleAdmin($row)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Nur aktuelle lokale Administration darf die API verwalten.');
        }
        $state = $this->read($db, $legacyDefaultEnabled);
        if ($state->revision !== $change->expectedRevision) { throw new DomainException('API-Stand wurde geändert. Neu laden und erneut bewusst wählen.'); }
        if ($state->revision === PHP_INT_MAX) { throw new DomainException('API-Richtlinienversion kann nicht erhöht werden.'); }
        return $state;
    }

    /** Existing key validation/provisioning must confirm before enabling. No key data enters the log. */
    public function appendLocked(PDO $db, AccountLifecycleAuthority $actor, ApiPolicyChange $change,
        bool $legacyDefaultEnabled, bool $keyConfirmed): ApiPolicyState
    {
        $before = $this->requireChangeLocked($db, $actor, $change, $legacyDefaultEnabled);
        if ($change->enabled && ! $keyConfirmed) { throw new LogicException('A confirmed admitted private API key is required.'); }
        $prepared = $before->keyPrepared || $keyConfirmed;
        // The initial source-derived default is not an explicit decision. Persist
        // even an initial Disable so later Env selection cannot reopen it and
        // another revision-0 tab cannot overwrite the administrator's decision.
        if ($before->revision > 0 && $before->enabled === $change->enabled && $before->keyPrepared === $prepared) { return $before; }
        $now = time();
        $after = new ApiPolicyState($change->enabled, $prepared, $before->revision + 1, $actor->id, $now);
        $s = $db->prepare("INSERT INTO audit_log(occurred_at,actor,reason,entity,record_id,user_id,operation,before_json,after_json) VALUES(?,audit_actor(),audit_reason(),'api_service_policy',1,0,'UPDATE',?,?)");
        try { $s->execute([$now, json_encode($before->values(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            json_encode($after->values(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]); }
        finally { $s->closeCursor(); }
        $saved = $this->read($db, $legacyDefaultEnabled);
        if ($saved->values() !== $after->values()) { throw new LogicException('API policy append not confirmed.'); }
        return $saved;
    }

    private function requireV5Transaction(PDO $db): void
    {
        if (! $db->inTransaction() || $db->query('SELECT version FROM app_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN) !== [1,2,3,4,5]) {
            throw new LogicException('The caller-owned admitted V5 transaction is required.');
        }
    }
}
