<?php

namespace App\Api\Management;

use InvalidArgumentException;

/** Safe UI projection, never raw key metadata, secret bytes, path, hash or password. */
final readonly class ApiManagementStatus
{
    private array $labels;
    public function __construct(public ApiPolicyState $policy, public string $keyStatus,
        public bool $legacySource, public ?string $notice = null, public string $language = 'de')
    {
        if (! in_array($keyStatus, ['missing','ready','partial','blocked','operator'], true)
            || ($notice !== null && ! in_array($notice, ['enabled','disabled','unchanged'], true))
            || ! in_array($language, ['de','en','zh'], true)) {
            throw new InvalidArgumentException('Invalid API management display state.');
        }
        $this->labels = require dirname(__DIR__, 3).'/lang/'.$language.'/api-management.php';
    }
    public function text(string $key): string { return $this->labels[$key] ?? throw new InvalidArgumentException('Unknown API display label.'); }
}
