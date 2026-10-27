<?php

namespace App\Providers;

use App\Auth\LegacyUser;
use App\Console\Commands\RehearseLocalAuthentication;
use App\Policies\LegacyUserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Response;

final class LegacyAuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Registration is lazy: no user query, connection or session at boot.
        Gate::policy(LegacyUser::class, LegacyUserPolicy::class);
        Gate::define('chrono-admin', fn (LegacyUser $user): bool => $user->role === 'admin');

        RateLimiter::for('chrono-web-login', function (Request $request): array {
            $username = $request->input('username');
            $username = is_string($username) ? strtolower(trim($username)) : '';
            $response = fn (Request $request, array $headers) => new Response(
                'Too many login attempts. Try again later.', 429,
                array_merge($headers, ['Content-Type' => 'text/plain; charset=UTF-8']),
            );

            return [
                Limit::perMinutes(10, 60)->by('ip:'.hash('sha256', $request->ip() ?? 'unknown'))->response($response),
                Limit::perMinutes(10, 15)->by('user:'.hash('sha256', $username))->response($response),
            ];
        });

        if ($this->app->runningInConsole()) {
            $this->commands([RehearseLocalAuthentication::class]);
        }
    }
}
