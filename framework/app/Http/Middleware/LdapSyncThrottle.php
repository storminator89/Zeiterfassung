<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\FileStore;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/** Stock Laravel counters, bound to the CURRENT explicit file store, not a stale singleton. */
final class LdapSyncThrottle extends ThrottleRequests
{
    public function __construct(Factory $cache)
    {
        $repository = $cache->store('file');
        if (! $repository->getStore() instanceof FileStore) {
            throw new LogicException('LDAP sync requires the existing persistent file cache.');
        }
        $limiter = new RateLimiter($repository);
        $limiter->for('chrono-web-ldap-sync', static function (Request $request): array {
            $actor = $request->user('web');
            $id = $actor?->getAuthIdentifier();
            $response = static fn (Request $request, array $headers) => new Response(
                'Too many LDAP synchronization attempts. Try again later.', 429,
                [...$headers, 'Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store'],
            );
            return [
                Limit::perMinutes(10, 5)->by('ldap-admin:'.hash('sha256', (string) ($id ?? 'unknown')))->response($response),
                Limit::perMinutes(60, 20)->by('ldap-ip:'.hash('sha256', $request->ip() ?? 'unknown'))->response($response),
            ];
        });
        parent::__construct($limiter);
    }

    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1, $prefix = '')
    {
        if (! $request->isMethod('POST') || ! array_key_exists('sync_ldap', $request->request->all())) {
            return $next($request);
        }

        return parent::handle($request, $next, 'chrono-web-ldap-sync');
    }
}
