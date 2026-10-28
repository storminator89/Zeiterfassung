<?php

namespace App\Ldap;

use Closure;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * Native-format encryption using an explicitly admitted EXISTING root source.
 * No loader/default, file, environment, DB, key creation or plaintext readback.
 * Root admission/provenance and local-admin/policy checks belong to the caller.
 */
final readonly class AdmittedLdapSettingsCipher
{
    /** @param Closure(): string $keySource Existing native 64-lowercase-hex root. */
    public function __construct(#[\SensitiveParameter] private Closure $keySource) {}

    public function encrypt(#[\SensitiveParameter] string $password): string
    {
        try {
            if ($password === '') {
                throw new RuntimeException;
            }
            $key = ($this->keySource)();
            if (! is_string($key) || ! preg_match('/^[a-f0-9]{64}$/D', $key)) {
                throw new RuntimeException;
            }
            // Exactly native admin.php v2: binary key, 12-byte IV, 16-byte GCM tag,
            // then raw ciphertext; base64 the concatenation without changing data.
            $iv = random_bytes(12);
            $ciphertext = openssl_encrypt($password, 'aes-256-gcm', hex2bin($key), OPENSSL_RAW_DATA,
                $iv, $tag, '', 16);
            if ($ciphertext === false || strlen($tag) !== 16) {
                throw new RuntimeException;
            }

            return 'v2:'.base64_encode($iv.$tag.$ciphertext);
        } catch (Throwable) {
            // Never forward a source error, path, key, password or previous error.
            throw new RuntimeException('Existing LDAP settings encryption admission refused.');
        }
    }

    public function __debugInfo(): array
    {
        return ['source' => 'explicit existing native LDAP settings root'];
    }

    public function __serialize(): array
    {
        throw new LogicException('LDAP settings key sources must not be serialized.');
    }
}
