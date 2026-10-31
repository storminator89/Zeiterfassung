<?php

namespace App\View\ExistingUi;

/** Prepared authorized native report only; not parallel chrome, SQL, accounting or policy. */
final readonly class SupervisorPage
{
    private array $labels;

    /** @param array $report Complete supervisor_report_projection() output, unescaped. */
    public function __construct(public array $report, public string $search, public string $language = 'de',
        public ?array $absenceWeek = null, public ?array $period = null,
        public bool $periodInvalid = false, public bool $periodUnavailable = false,
        /** Optional read-only complete-team diagnostic projection, never raw report rows. */
        public ?array $review = null)
    {
        if (! in_array($language, ['de', 'en', 'zh'], true)) {
            throw new \InvalidArgumentException('Unsupported existing UI language.');
        }
        $this->labels = require dirname(__DIR__, 3).'/lang/'.$language.'/supervisor.php';
    }

    public function text(string $key): string
    {
        return $this->labels[$key] ?? throw new \InvalidArgumentException('Unknown supervisor UI label.');
    }

    /** Presentation only: a second booking does not imply a second work location. */
    public function locationLabel(mixed $location): string
    {
        return match ($location) {
            'office', 'Büro' => match ($this->language) { 'en' => 'Office', 'zh' => '办公室', default => 'Büro' },
            'home_office', 'Home', 'Home Office', 'Homeoffice' => match ($this->language) { 'en' => 'Home office', 'zh' => '居家办公', default => 'Homeoffice' },
            'business_trip', 'Dienstreise' => match ($this->language) { 'en' => 'Business trip', 'zh' => '出差', default => 'Dienstreise' },
            null, '' => '—',
            default => is_string($location) ? $location : '—',
        };
    }

    public function groupLocation(array $group): string
    {
        $locations = array_unique(array_map(fn (array $entry): string => $this->locationLabel($entry['standort'] ?? null), $group['entries'] ?? []));
        return count($locations) > 1
            ? match ($this->language) { 'en' => 'Different locations', 'zh' => '多个地点', default => 'Verschiedene Orte' }
            : ($locations === [] ? '—' : reset($locations));
    }

    public function pageUrl(int $page): string
    {
        return '?'.http_build_query(['lang' => $this->language, 'user_search' => $this->search, 'page' => $page]
            + ($this->absenceWeek === null ? [] : ['team_date' => $this->absenceWeek['from'], 'team_page' => $this->absenceWeek['page']])
            + $this->periodQuery() + $this->reviewQuery());
    }
    /** Fixed same-page navigation only; source dates/page come from the scoped projection. */
    public function absenceWeekUrl(string $day, int $page = 1): string
    {
        return '?'.http_build_query(['lang' => $this->language, 'user_search' => $this->search,
            'page' => $this->report['page'], 'team_date' => $day, 'team_page' => $page] + $this->periodQuery() + $this->reviewQuery());
    }

    public function periodQuery(): array
    {
        require_once dirname(__DIR__, 4).'/lib/supervisor_table_period.php';

        return \supervisor_table_period_query($this->period, $this->periodInvalid);
    }

    public function periodResetUrl(): string
    {
        return '?'.http_build_query(['lang' => $this->language, 'user_search' => $this->search, 'page' => 1]
            + ($this->absenceWeek === null ? [] : ['team_date' => $this->absenceWeek['from'], 'team_page' => $this->absenceWeek['page']]) + $this->reviewQuery());
    }

    public function reviewQuery(): array
    {
        return $this->review !== null && $this->review['state'] !== 'invalid' ? ['team_review' => '1'] : [];
    }

    public function reviewUrl(bool $open): string
    {
        return '?'.http_build_query(['lang' => $this->language, 'user_search' => $this->search, 'page' => $this->report['page']]
            + ($this->absenceWeek === null ? [] : ['team_date' => $this->absenceWeek['from'], 'team_page' => $this->absenceWeek['page']])
            + $this->periodQuery() + ($open ? ['team_review' => '1'] : [])).'#team-time-review';
    }
}
