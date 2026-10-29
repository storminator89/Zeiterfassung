<?php

namespace App\FreshWeb;

use App\Ldap\AdmittedLdapSettingsCipher;
use App\Ldap\LdapSyncFileSources;
use App\Ldap\LdapSyncTarget;
use App\Ldap\LdapSyncTransport;
use App\Ldap\PhpLdapSyncTransport;
use App\Ldap\PinnedLdapSyncTransport;
use App\LegacyDatabase\RuntimeAdmission;
use JsonSerializable;
use LogicException;
use Throwable;

/** Pure trusted Sync metadata, separate from existing-account LDAP login settings. */
final readonly class FreshWebLdapSyncProfile implements JsonSerializable
{
    private function __construct(private array $settings, private LdapSyncFileSources $files) {}

    /** No file/CA/root, extension, connection, DB contents or key generation. */
    public static function fromConfiguration(mixed $settings, RuntimeAdmission $database, array $forbiddenRoots): ?self
    {
        try {
            if (! is_array($settings) || ! is_bool($settings['enabled'] ?? null)) { throw new LogicException; }
            if (! $settings['enabled']) { return null; } // Ignore unused metadata, preserve local break-glass.
            $keys = ['enabled', 'version', 'uri', 'base_dn', 'max_entries', 'ca', 'settings_root', 'database_profile_sha256'];
            if (count($settings) !== count($keys) || array_diff($keys, array_keys($settings)) !== []
                || $settings['version'] !== 1 || ! in_array($database->schemaProfile, ['intake-policy-v4', 'account-lifecycle-v5'], true)
                || ! is_string($settings['uri']) || ! is_string($settings['base_dn'])
                || ! is_int($settings['max_entries']) || $settings['max_entries'] < 1 || $settings['max_entries'] > 10000
                || ! is_array($settings['ca']) || ! is_array($settings['settings_root'])
                || ! is_string($settings['database_profile_sha256'])
                || ! hash_equals(LdapSyncFileSources::databaseProfileDigest($database), $settings['database_profile_sha256'])) {
                throw new LogicException;
            }
            LdapSyncTarget::fromTrustedUri($settings['uri'], $settings['base_dn']);
            $files = LdapSyncFileSources::fromConfiguration($settings['ca'], $settings['settings_root'], $forbiddenRoots);
            if ($settings['ca']['path'] === $settings['settings_root']['path']) { throw new LogicException; }
            return new self($settings, $files);
        } catch (Throwable) { throw new LogicException('Fresh LDAP sync metadata is not admitted.'); }
    }

    /** Exact non-secret metadata only, captured for the existing per-request FreshWeb guard. */
    public function configuration(): array { return $this->settings; }

    public function transport(): LdapSyncTransport
    {
        return new PinnedLdapSyncTransport(new PhpLdapSyncTransport(true, $this->settings['uri'],
            $this->settings['base_dn'], $this->settings['ca']['path'], $this->settings['max_entries']), $this->files);
    }

    public function cipher(): AdmittedLdapSettingsCipher
    {
        return new AdmittedLdapSettingsCipher(fn (): string => $this->files->settingsRoot());
    }

    public function __debugInfo(): array { return ['profile' => 'explicit FreshWeb LDAP sync metadata']; }
    public function jsonSerialize(): array { return $this->__debugInfo(); }
    public function __serialize(): array { throw new LogicException('Fresh LDAP sync profiles must not be serialized.'); }
}
