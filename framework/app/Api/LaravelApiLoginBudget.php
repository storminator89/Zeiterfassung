<?php

namespace App\Api;

use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Exceptions\HttpResponseException;

/** Stock counter mechanics, existing budgets; storage admission remains external. */
final class LaravelApiLoginBudget implements LegacyApiLoginBudget
{
    public function __construct(private RateLimiter $limiter) {}

    public function consume(string $username, string $remoteAddress): void
    {
        foreach (['ip:'.$remoteAddress => 60, 'user:'.strtolower($username) => 15] as $name => $limit) {
            // Consume successful and failed attempts. Never clear on login and
            // never invent a fresh per-request/cookie store or budget namespace.
            if ($this->limiter->hit(hash('sha256', $name), 600) > $limit) {
                $response = LegacyApiResponse::text('Too many login attempts. Try again later.', 429);
                $response->headers->set('Retry-After', '600');
                throw new HttpResponseException($response);
            }
        }
    }
}
