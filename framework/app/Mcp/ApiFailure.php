<?php

namespace App\Mcp;

/** Safe classifications only; never retain upstream messages or HTTP exceptions. */
final class ApiFailure extends \RuntimeException
{
    public function __construct(public readonly string $reason, public readonly ?int $status = null, public readonly ?int $retryAfter = null)
    {
        parent::__construct('Chrono API request was not confirmed.');
    }
}
