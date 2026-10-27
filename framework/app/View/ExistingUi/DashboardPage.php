<?php

namespace App\View\ExistingUi;

/** Already-authorized native display values; no queries, clocks or accounting. */
final readonly class DashboardPage
{
    /**
     * @param array<string, string|int|bool|null> $statistics Reader-prepared native metrics.
     * @param array{start: \DateTimeImmutable, end: \DateTimeImmutable, previous: string, next: string, today: string} $range Canonical calendar_visible_week() output.
     * @param list<array> $events Canonical calendar_range_events() output, unescaped.
     * @param array{all: bool, week: bool, month: bool, year: bool} $finality Complete own-context conflict flags, never event-filtered.
     * @param array{all: bool, week: bool, month: bool, year: bool, unknown: bool} $incomplete Invalid-row scope, independent from conflicts.
     */
    public function __construct(
        public array $statistics,
        public array $range,
        public array $events,
        public array $finality,
        public array $incomplete,
    ) {}

    public function presentation(string $period): string
    {
        return ($this->finality[$period] ?? false) ? 'unresolved' : (($this->incomplete[$period] ?? false) ? 'incomplete' : 'current');
    }

    public function value(string $name, string $period): string|int
    {
        return match ($this->presentation($period)) {
            'unresolved' => 'Klärung offen',
            'incomplete' => 'Unvollständig',
            default => $this->statistics[$name],
        };
    }

    public function link(string $day): string
    {
        return 'dashboard.php?calendar_date='.rawurlencode($day).'#calendar';
    }

    public function calendarEvents(): array
    {
        return CalendarEvents::fromNative($this->events);
    }
}
