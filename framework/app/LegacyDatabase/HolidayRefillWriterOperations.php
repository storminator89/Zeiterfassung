<?php

namespace App\LegacyDatabase;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;

trait HolidayRefillWriterOperations
{
    /** Fixed local Admin operation using the unchanged writer authority/lock lifecycle. */
    public function holidayRefill(int $user, mixed $year, mixed $region, mixed $json,
        ?\App\Accounts\AccountLifecycleAuthority $authority = null, ?array $webContext = null): array
    {
        require_once dirname(__DIR__, 3).'/lib/holiday_refill.php';
        $stage = \chrono_holiday_refill_stage($year, $region, $json);
        $reason = 'Feiertage ergänzen BW '.$stage['year'].'; Antwort SHA256 '.$stage['source_sha256'];

        return $this->executeLocked(function () use ($user, $year, $region, $json, $authority, $webContext): array {
            $this->assertBusinessAdminAuthorityLocked($user, $authority, $webContext);
            $actor = att_one($this->pdo, 'SELECT role,auth_source FROM users WHERE id=?', [$user]);
            if ($actor === null || $actor['role'] !== 'admin' || $actor['auth_source'] !== 'local') {
                throw new AuthorizationException('Nur aktuelle lokale Verwaltung darf Feiertage ergänzen.');
            }
            return \chrono_holiday_refill_locked($this->pdo, $year, $region, $json);
        }, fn (Closure $transaction): mixed => $this->context->run('web:'.$user, $reason, $transaction),
            lifecycleReady: $this->hasGenerationBoundAccountAuthority() && $authority !== null);
    }
}
