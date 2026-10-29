<?php

namespace App\Chrono;

/** Immutable native filters only; identity and read authority belong to the snapshot. */
final readonly class HistoryQuery
{
    public array $values;

    public array $errors;

    public array $entities;

    public array $operations;

    public bool $hasBefore;

    private function __construct(private array $filters)
    {
        $this->values = $filters['values'];
        $this->errors = $filters['errors'];
        $this->entities = \chrono_history_entities();
        $this->operations = \chrono_history_operations();
        $this->hasBefore = $filters['ids']['before'] !== null;
    }

    public static function fromQueryString(string $queryString): self
    {
        // Definitions only, not config.php, a connection or a native bootstrap.
        require_once dirname(__DIR__, 3).'/lib/history_filters.php';
        // Match native $_GET before Laravel's trim/empty-to-null transformations.
        parse_str($queryString, $query);

        return new self(\chrono_history_filters($query));
    }

    /** Only the reader supplies its freshly reloaded authenticated actor/role. */
    public function statementFor(int $user, string $role): array
    {
        // The canonical helper refuses invalid filters; no unfiltered fallback.
        return \chrono_history_query($this->filters, $user, $role);
    }

    public function url(?int $before = null): string
    {
        return \chrono_history_url($this->filters, $before);
    }
}
