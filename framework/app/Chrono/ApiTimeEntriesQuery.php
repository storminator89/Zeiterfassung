<?php

namespace App\Chrono;

/** Normalized existing API query; the HTTP adapter owns date-shape validation. */
final readonly class ApiTimeEntriesQuery
{
    public function __construct(
        public ?string $startDate = null,
        public ?string $endDate = null,
        public ?int $limit = null,
        public ?int $offset = null,
        public ?int $limitMetadata = null,
        public ?int $offsetMetadata = null,
    ) {}
}
