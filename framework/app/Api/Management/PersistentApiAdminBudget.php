<?php

namespace App\Api\Management;

use Illuminate\Cache\FileStore;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\Repository;
use Illuminate\Http\Exceptions\HttpResponseException;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/** Confirmed stock file accounting before costly password verification; no quota reset. */
final readonly class PersistentApiAdminBudget implements ApiAdminBudget
{
    public function __construct(private Repository $attempts) {}
    public function consume(int $user, string $remoteAddress): void
    {
        if ($user < 1) { throw new LogicException('Current API administrator required.'); }
        $store = $this->attempts->getStore();
        if (! $store instanceof FileStore) { throw new LogicException('Persistent API administration accounting required.'); }
        $limiter = new RateLimiter($this->attempts);
        $retry = $store->lock('chrono-api-admin-accounting', 10)->block(2, function () use ($limiter, $user, $remoteAddress): int {
            $retry = 0;
            foreach ([['user:'.hash('sha256', (string) $user), 5], ['ip:'.hash('sha256', $remoteAddress), 20]] as [$name, $max]) {
                $key = 'chrono-api-admin:'.$name;
                $hits = $limiter->hit($key, 600);
                $remaining = $limiter->availableIn($key);
                if (! is_int($hits) || $hits < 1 || $limiter->attempts($key) !== $hits || $remaining < 1) {
                    throw new LogicException('API administration accounting not confirmed.');
                }
                if ($hits > $max) { $retry = max($retry, $remaining); }
            }
            return $retry;
        });
        if ($retry > 0) {
            throw new HttpResponseException(new Response('Zu viele Passwortversuche. Bitte später erneut versuchen.', 429,
                ['Content-Type'=>'text/plain; charset=UTF-8','Cache-Control'=>'no-store','Retry-After'=>(string) $retry]));
        }
    }
}
