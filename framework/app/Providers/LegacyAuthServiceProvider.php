<?php

namespace App\Providers;

use App\Auth\ExistingAccountUserProvider;
use App\Auth\AccountLifecycleWebIdentity;
use App\LegacyDatabase\RuntimeDatabase;
use App\Auth\ExistingLdapUserProvider;
use App\Auth\LegacyUser;
use App\Auth\PhpLdapCredentialVerifier;
use App\Console\Commands\RehearseLocalAuthentication;
use App\Policies\LegacyUserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

final class LegacyAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(\App\Auth\OwnPasswordChange::class, fn ($app) => new \App\Auth\OwnPasswordChange(
            $app->make('auth'), $app->make('hash'), $app->make('config'), $app->make('cache')->store('file'),
            function () use ($app): \App\LegacyDatabase\AttendanceWriter {
                if (! $app->bound(\App\LegacyDatabase\AttendanceWriter::class)) { throw new LogicException('No admitted own-password writer.'); }
                return $app->make(\App\LegacyDatabase\AttendanceWriter::class);
            },
            fn (): bool => $app->bound(RuntimeDatabase::class) && $app->make(RuntimeDatabase::class)->hasGenerationBoundAccountAuthority(),
            $app->make(AccountLifecycleWebIdentity::class),
        ));
        // Lazy immutable profile metadata only: resolving this service never
        // opens a source or makes the still-closed V5 runtime operational.
        $this->app->scoped(AccountLifecycleWebIdentity::class, fn ($app) =>
            AccountLifecycleWebIdentity::fromRuntime($app->bound(RuntimeDatabase::class)
                ? $app->make(RuntimeDatabase::class) : null));
    }

    public function boot(): void
    {
        $this->app->make('auth')->provider('chrono-existing', function ($app, array $config): ExistingAccountUserProvider {
            $settings = $app->make('config')->get('chrono_ldap');
            if (($config['model'] ?? null) !== LegacyUser::class || ! is_array($settings)
                || ($settings['enabled'] ?? null) !== true) {
                throw new LogicException('Explicit existing-account LDAP profile required.');
            }
            foreach (['uri', 'base_dn', 'ca_file'] as $key) {
                if (! is_string($settings[$key] ?? null) || trim($settings[$key]) === '') {
                    throw new LogicException('Complete trusted LDAP configuration required.');
                }
            }

            // Pure construction: no extension, CA-path, connection or directory liveness check.
            // Local break-glass does not depend on LDAP availability in this opt-in profile.
            $hasher = $app->make('hash');
            $verifier = new PhpLdapCredentialVerifier(true, $settings['uri'], $settings['base_dn'], $settings['ca_file']);

            $lifecycle = $app->make(AccountLifecycleWebIdentity::class);

            return new ExistingAccountUserProvider($hasher, new ExistingLdapUserProvider($hasher, $verifier, $lifecycle), $lifecycle);
        });

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

        RateLimiter::for('chrono-web-register', function (Request $request): array {
            $response = fn (Request $request, array $headers) => new Response(
                'Too many registration attempts. Try again later.', 429,
                array_merge($headers, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']),
            );
            return [
                Limit::perMinutes(60, 10)->by('registration-ip:'.hash('sha256', $request->ip() ?? 'unknown'))->response($response),
                Limit::perMinutes(60, 100)->by('registration-total')->response($response),
            ];
        });

        RateLimiter::for('chrono-web-setup', function (Request $request): array {
            $response = fn (Request $request, array $headers) => new Response(
                'Too many setup attempts. Try again later.', 429,
                array_merge($headers, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']),
            );
            return [
                Limit::perMinutes(15, 5)->by('setup-ip:'.hash('sha256', $request->ip() ?? 'unknown'))->response($response),
                Limit::perMinutes(60, 20)->by('setup-total')->response($response),
            ];
        });

        if ($this->app->runningInConsole()) {
            $this->commands([RehearseLocalAuthentication::class]);
        }
    }
}
