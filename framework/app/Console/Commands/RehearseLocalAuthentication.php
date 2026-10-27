<?php

namespace App\Console\Commands;

use App\Auth\LegacyUser;
use App\Auth\LocalSession;
use App\Http\Middleware\LegacyFormCsrf;
use App\Http\Middleware\RefreshLocalAccount;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\Repository;
use Illuminate\Console\Command;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** One coarse synthetic lifecycle, not an HTTP/UI/production acceptance suite. */
final class RehearseLocalAuthentication extends Command
{
    protected $signature = 'chrono:rehearse-local-auth';

    protected $description = 'Rehearse inactive local auth on anonymous memory-only users/session/cache; no file ingress or HTTP.';

    private const PASSWORD = 'Synthetic-only-login-passphrase';

    private ThrottleRequests $throttle;

    public function handle(): int
    {
        $config = $this->laravel->make('config');
        $database = $this->laravel->make('db');
        $phase = 'isolation';
        // This command must never replace an activated runtime connection or use
        // an already-open handle. No path, key, credential or fixture arguments.
        if ($config->has('database.connections.chrono') || $database->getConnections() !== []
            || $this->laravel->runningUnitTests()) {
            $this->error('Refused: requires unconfigured chrono, no resolved connections, and real CSRF middleware.');
            return self::FAILURE;
        }

        $previous = [
            'app.key' => $config->get('app.key'),
            'session.driver' => $config->get('session.driver'),
            'database.connections' => $config->get('database.connections'),
        ];

        try {
            try {
                LegacyUser::query()->first();
                throw new RuntimeException('Unconfigured auth connection was accepted.');
            } catch (InvalidArgumentException) {
                $this->ensure($database->getConnections() === [], 'Unconfigured auth opened a fallback connection.');
            }

            // Synthetic, process-local encryption key only; never output/save it.
            $config->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
            $config->set('session.driver', 'array');
            $config->set('database.connections.chrono', [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
                'foreign_key_constraints' => false, 'busy_timeout' => 5000,
                'journal_mode' => null, 'synchronous' => null,
            ]);
            $this->laravel->make('session')->forgetDrivers();
            $this->laravel->forgetInstance('session.store');
            $this->laravel->make('auth')->forgetGuards();

            // Reuse the registered production limit definition, but never its
            // file cache: this rehearsal's counters live only in an ArrayStore.
            $limiter = new RateLimiter(new Repository(new ArrayStore));
            $limiter->for('chrono-web-login', $this->laravel->make(RateLimiter::class)->limiter('chrono-web-login'));
            $this->throttle = new ThrottleRequests($limiter);
            $connection = $database->connection('chrono');
            $this->ensure($connection->getDatabaseName() === ':memory:', 'Fixture is not memory-only.');
            $mapping = json_decode(file_get_contents(dirname(base_path()).'/docs/framework/schema-map.json'), true, 512, JSON_THROW_ON_ERROR);
            $users = array_column($mapping['tables'], null, 'name')['users'];
            $connection->statement($users['create_sql']);
            foreach ($users['later_columns'] as $column) {
                $connection->statement('ALTER TABLE users ADD COLUMN '.$column['name'].' '.$column['declaration']);
            }
            $hash = password_hash(self::PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]);
            foreach ([
                [101, 'member', 'user', 102, 'local'],
                [102, 'supervisor', 'supervisor', null, 'local'],
                [103, 'direct', 'user', 102, 'local'],
                [104, 'outside', 'user', 103, 'local'],
                [105, 'admin', 'admin', null, 'local'],
                [106, 'directory', 'user', 102, 'ldap'],
            ] as [$id, $name, $role, $supervisor, $source]) {
                $connection->table('users')->insert([
                    'id' => $id, 'username' => $name, 'password' => $hash,
                    'email' => $name.'@example.invalid', 'role' => $role,
                    'supervisor_id' => $supervisor, 'department_id' => 7, 'auth_source' => $source,
                ]);
            }

            $phase = 'local login and preserved identity/hash';
            $session = $this->laravel->make('session.store');
            $session->start();
            $oldId = $session->getId();
            $oldCsrf = $session->token();
            $this->ensure($this->login('member', 'wrong')->getStatusCode() === 401, 'Wrong password accepted.');
            $this->ensure($this->login('directory')->getStatusCode() === 401, 'LDAP account received local fallback.');
            $this->ensure($this->login('member')->getStatusCode() === 200, 'Existing hash login failed.');
            $this->ensure((int) $this->laravel->make('auth')->guard('web')->id() === 101, 'Identity changed.');
            $this->ensure($session->getId() !== $oldId && $session->token() !== $oldCsrf, 'Login did not rotate ID/token.');
            $this->ensure($connection->table('users')->where('id', 101)->value('password') === $hash, 'Login rehashed the legacy password.');
            $session->save();
            $session->start();
            $this->laravel->make('auth')->forgetGuards();
            $this->ensure($this->authenticated(fn (Request $r) => (int) $r->user()->getKey() === 101), 'Session roundtrip lost identity.');
            $this->info('PASS local login, wrong password/LDAP refusal, unchanged ID/hash, isolated session rotation');

            $phase = 'role/team boundaries and current-account revocation';
            $this->ensure($this->authenticated(fn (Request $r) =>
                Gate::forUser($r->user())->allows('view', LegacyUser::findOrFail(101))
                && Gate::forUser($r->user())->denies('view', LegacyUser::findOrFail(104))
                && LegacyUser::visibleTo($r->user())->pluck('id')->all() === [101]
            ), 'Member scope escaped self.');
            $this->ensure($this->login('supervisor')->getStatusCode() === 200, 'Supervisor login failed.');
            $this->ensure($this->authenticated(fn (Request $r) =>
                Gate::forUser($r->user())->allows('reviewCorrection', LegacyUser::findOrFail(103))
                && Gate::forUser($r->user())->denies('view', LegacyUser::findOrFail(104))
                && Gate::forUser($r->user())->denies('reviewCorrection', $r->user())
                && LegacyUser::visibleTo($r->user())->orderBy('id')->pluck('id')->all() === [101, 102, 103, 106]
            ), 'Supervisor scope escaped direct team.');
            $this->ensure($this->login('admin')->getStatusCode() === 200, 'Admin login failed.');
            $this->ensure($this->authenticated(fn (Request $r) =>
                Gate::forUser($r->user())->allows('chrono-admin')
                && Gate::forUser($r->user())->allows('reviewCorrection', LegacyUser::findOrFail(104))
                && Gate::forUser($r->user())->denies('reviewCorrection', $r->user())
                && LegacyUser::visibleTo($r->user())->count() === 6
            ), 'Admin boundary/self-approval failed.');
            $connection->table('users')->where('id', 105)->update(['role' => 'user']);
            $this->ensure($this->authenticated(fn (Request $r) => Gate::forUser($r->user())->denies('chrono-admin')), 'Role change left stale admin rights.');
            $connection->table('users')->where('id', 105)->update(['password' => password_hash('Synthetic replacement', PASSWORD_BCRYPT, ['cost' => 4])]);
            $this->expectUnauthenticated(fn () => $this->authenticated(fn () => true));
            $this->ensure(! $session->has('password_hash_web'), 'Standard password revocation left login marker.');
            // A new anonymous web request starts a fresh CSRF-bearing session.
            $session->save();
            $session->start();
            $this->laravel->make('auth')->forgetGuards();
            $this->ensure($this->login('direct')->getStatusCode() === 200, 'Deletion fixture login failed.');
            $connection->table('users')->where('id', 103)->delete();
            $this->expectUnauthenticated(fn () => $this->authenticated(fn () => true));
            $this->info('PASS self/direct-team/admin, no self-approval, fresh role, password/deletion revocation');

            $phase = 'CSRF and cookie-independent login limiting';
            $csrf = $this->laravel->make(LegacyFormCsrf::class);
            $missing = $this->request('POST', [], ['HTTP_SEC_FETCH_SITE' => 'same-origin']);
            $this->ensure($csrf->handle($missing, fn () => new Response)->getStatusCode() === 403, 'Same-origin request bypassed required token.');
            $stale = $this->request('POST', ['csrf_token' => $oldCsrf]);
            $this->ensure($csrf->handle($stale, fn () => new Response)->getStatusCode() === 403, 'Old pre-login CSRF token accepted.');
            $header = $this->request('POST', [], ['HTTP_X_CSRF_TOKEN' => $session->token()]);
            $this->ensure($csrf->handle($header, fn () => new Response)->getStatusCode() === 200, 'Legacy CSRF header rejected.');
            for ($attempt = 1; $attempt <= 16; $attempt++) {
                $session->regenerate(true); // Changing cookies cannot reset account/IP budget.
                $response = $this->login('throttled', 'wrong');
                $this->ensure($response->getStatusCode() === ($attempt <= 15 ? 401 : 429), 'Named login limiter boundary failed.');
            }
            $this->ensure((int) $response->headers->get('Retry-After') > 0, 'Throttle retry information missing.');
            $this->info('PASS legacy CSRF field/header, stale/missing-token refusal, cookie-independent throttle');

            $phase = 'logout';
            $this->ensure($this->login('member')->getStatusCode() === 200, 'Logout fixture login failed.');
            $beforeLogout = [$session->getId(), $session->token()];
            $this->laravel->make(LocalSession::class)->logout($this->request('POST', ['csrf_token' => $session->token()]));
            $this->ensure($beforeLogout[0] !== $session->getId() && $beforeLogout[1] !== $session->token(), 'Logout did not invalidate ID/token.');
            $this->expectUnauthenticated(fn () => $this->authenticated(fn () => true));
            $this->ensure(array_keys($database->getConnections()) === ['chrono'], 'Rehearsal resolved another connection.');
            $this->info('PASS logout and anonymous access refusal; only memory users/session/cache used');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            // Never output inputs, keys, SQL, hashes, bindings or exception text.
            $this->error('FAIL '.$phase.' ('.$exception::class.').');
            return self::FAILURE;
        } finally {
            $this->laravel->make('auth')->forgetGuards();
            $database->purge('chrono');
            foreach ($previous as $key => $value) {
                $config->set($key, $value);
            }
            $this->laravel->make('session')->forgetDrivers();
            $this->laravel->forgetInstance('session.store');
            $this->laravel->forgetInstance('encrypter');
        }
    }

    private function request(string $method, array $input = [], array $server = []): Request
    {
        $request = Request::create('/synthetic-auth-rehearsal', $method, $input, [], [], array_merge(['REMOTE_ADDR' => '192.0.2.1'], $server));
        $request->setLaravelSession($this->laravel->make('session.store'));
        $request->setUserResolver(fn ($guard = null) => $this->laravel->make('auth')->guard($guard ?? 'web')->user());
        $this->laravel->instance('request', $request);
        return $request;
    }

    private function login(string $username, string $password = self::PASSWORD): Response
    {
        $request = $this->request('POST', [
            'username' => $username, 'password' => $password,
            'csrf_token' => $this->laravel->make('session.store')->token(),
        ]);
        try {
            return $this->laravel->make(LegacyFormCsrf::class)->handle($request, fn (Request $r) =>
                $this->throttle->handle($r, fn (Request $r) => new Response(
                    '', $this->laravel->make(LocalSession::class)->attempt($r) ? 200 : 401,
                ), 'chrono-web-login')
            );
        } catch (HttpResponseException $exception) {
            return $exception->getResponse();
        }
    }

    private function authenticated(callable $check): bool
    {
        $response = $this->laravel->make(RefreshLocalAccount::class)->handle($this->request('GET'), fn (Request $r) =>
            $this->laravel->make(Authenticate::class)->handle($r, fn (Request $r) =>
                $this->laravel->make(AuthenticateSession::class)->handle($r,
                    fn (Request $r) => new Response($check($r) ? 'allowed' : 'denied')
                ), 'web')
        );
        return $response->getContent() === 'allowed';
    }

    private function expectUnauthenticated(callable $operation): void
    {
        try {
            $operation();
        } catch (AuthenticationException) {
            return;
        }
        throw new RuntimeException('Invalidated session remained authenticated.');
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }
}
