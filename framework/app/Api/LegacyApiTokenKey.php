<?php

namespace App\Api;

/**
 * Explicitly admitted existing legacy signing secret, as its original ASCII bytes.
 * No default binding/loader: boot must not read files, generate keys or use APP_KEY.
 */
interface LegacyApiTokenKey
{
    public function secret(): string;
}
