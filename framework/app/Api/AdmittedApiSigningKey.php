<?php

namespace App\Api;

use Illuminate\Contracts\Container\Container;
use LogicException;

/** Resolve only the explicitly admitted existing secret; no loader or mutation. */
final class AdmittedApiSigningKey
{
    public function __construct(private Container $container) {}

    public function secret(): string
    {
        if (! $this->container->bound(LegacyApiTokenKey::class)) {
            throw new LogicException('An explicitly admitted legacy API signing secret is required.');
        }
        $secret = $this->container->make(LegacyApiTokenKey::class)->secret();
        if (! preg_match('/^[a-f0-9]{64}$/D', $secret)) {
            throw new LogicException('The existing legacy API signing secret is required.');
        }
        return $secret;
    }
}
