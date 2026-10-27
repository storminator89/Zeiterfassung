<?php

// One definition/credential-boundary proof, NOT a login or directory rehearsal.
// No Laravel bootstrap, container, guard, session, PDO, query or network call.
use App\Auth\ExistingLdapUserProvider;
use App\Auth\LdapCredentialVerifier;
use App\Auth\LegacyUser;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;

require dirname(__DIR__).'/vendor/autoload.php';

$ensure = static function (bool $condition): void {
    if (! $condition) {
        throw new RuntimeException('Dormant LDAP provider contract failed.');
    }
};
$previousResolver = Model::getConnectionResolver();
$resolver = new class implements ConnectionResolverInterface
{
    public int $calls = 0;

    public function connection($name = null) { $this->calls++; throw new LogicException('No database admission.'); }
    public function getDefaultConnection() { throw new LogicException('No database admission.'); }
    public function setDefaultConnection($name) { throw new LogicException('No database admission.'); }
};
$hasher = new class implements Hasher
{
    public function info($hashedValue) { throw new LogicException('No hashing admission.'); }
    public function make(#[\SensitiveParameter] $value, array $options = []) { throw new LogicException('No hashing admission.'); }
    public function check(#[\SensitiveParameter] $value, $hashedValue, array $options = []) { throw new LogicException('No local fallback admission.'); }
    public function needsRehash($hashedValue, array $options = []) { throw new LogicException('No hashing admission.'); }
};
$directory = new class implements LdapCredentialVerifier
{
    public int $calls = 0;
    public bool $available = true;
    public bool $accept = true;

    public function verify(string $username, #[\SensitiveParameter] string $password): bool
    {
        $this->calls++;
        if (! $this->available) { throw new LogicException('Synthetic directory unavailable.'); }

        return $this->accept && $username === 'directory-fixture' && $password === 'synthetic-only-input';
    }
};
$fixture = static function (array $changes = [], bool $exists = true): LegacyUser {
    $user = new LegacyUser;
    $user->setRawAttributes(array_replace([
        'id' => 101, 'username' => 'directory-fixture', 'auth_source' => 'ldap',
        'role' => 'user', 'password' => 'synthetic-version-marker-not-a-password-hash',
        'supervisor_id' => 102, 'department_id' => 7,
    ], $changes), true);
    $user->exists = $exists;

    return $user;
};

try {
    $ensure($argc === 1 && ! defined('CHRONO_SECURITY_LOADED') && session_status() === PHP_SESSION_NONE);
    Model::setConnectionResolver($resolver);
    $provider = new ExistingLdapUserProvider($hasher, $directory);
    $ensure($provider instanceof UserProvider && $provider instanceof EloquentUserProvider
        && $provider->getModel() === LegacyUser::class);
    $credentials = ['username' => ' directory-fixture ', 'password' => 'synthetic-only-input'];

    // Existing identity/role/team/version fields are just anonymous model values.
    foreach (['user', 'supervisor', 'admin'] as $role) {
        $user = $fixture(['role' => $role]);
        $before = $user->getAttributes();
        $ensure($provider->validateCredentials($user, $credentials));
        $provider->rehashPasswordIfRequired($user, $credentials, true);
        $ensure($user->getAttributes() === $before && ! $user->isDirty());
    }

    $beforeCalls = $directory->calls;
    foreach ([
        $fixture(['auth_source' => 'local']), $fixture(['auth_source' => 'unknown']),
        $fixture(['role' => 'unknown']), $fixture(['username' => 'other-fixture']),
        $fixture(['id' => null]), $fixture([], false),
        new GenericUser(['id' => 101, 'username' => 'directory-fixture', 'role' => 'admin', 'auth_source' => 'ldap']),
    ] as $refused) {
        $ensure(! $provider->validateCredentials($refused, $credentials));
    }
    foreach ([
        [], ['username' => 'directory-fixture'], ['username' => [], 'password' => 'synthetic-only-input'],
        ['username' => 'directory-fixture', 'password' => []], ['username' => ' ', 'password' => 'synthetic-only-input'],
        ['username' => '0', 'password' => 'synthetic-only-input'], ['username' => 'directory-fixture', 'password' => '0'],
        ['username' => 'directory-fixture', 'password' => ''], $credentials + ['auth_source' => 'local'],
        $credentials + ['role' => static fn () => throw new LogicException('Untrusted callback.')],
    ] as $invalid) {
        $ensure($provider->retrieveByCredentials($invalid) === null
            && ! $provider->validateCredentials($fixture(), $invalid));
    }
    $ensure($directory->calls === $beforeCalls);

    $user = $fixture();
    $before = $user->getAttributes();
    $directory->accept = false;
    $ensure(! $provider->validateCredentials($user, $credentials));
    $directory->available = false;
    $thrown = false;
    try { $provider->validateCredentials($user, $credentials); }
    catch (LogicException) { $thrown = true; }
    $ensure($thrown && $user->getAttributes() === $before && ! $user->isDirty());

    // No remember-me lookup or write, including calls bypassing a normal guard.
    $ensure($provider->retrieveByToken(101, 'synthetic-only-token') === null);
    $thrown = false;
    try { $provider->updateRememberToken($user, 'synthetic-only-token'); }
    catch (LogicException) { $thrown = true; }
    $ensure($thrown && $user->getAttributes() === $before && $resolver->calls === 0
        && ! defined('CHRONO_SECURITY_LOADED') && session_status() === PHP_SESSION_NONE);

    echo json_encode([
        'passed' => true,
        'scope' => 'unregistered Laravel provider definitions and anonymous fake-verifier credential boundary only',
        'laravel_provider_contract' => true,
        'existing_ldap_identity_roles_and_attributes_unchanged' => true,
        'local_unknown_mismatch_malformed_and_transient_refused_before_directory' => true,
        'directory_denial_and_exception_no_local_hash_fallback' => true,
        'no_rehash_or_remember_write' => true,
        'database_resolutions' => $resolver->calls,
        'valid_database_retrieval_executed' => false,
        'real_directory_or_login_session_or_activation' => false,
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $error) {
    // Never echo model values, synthetic inputs or connector diagnostics.
    fwrite(STDERR, 'FAIL dormant LDAP contract ('.$error::class.").\n");
    exit(1);
} finally {
    if ($previousResolver === null) {
        Model::unsetConnectionResolver();
    } else {
        Model::setConnectionResolver($previousResolver);
    }
}
