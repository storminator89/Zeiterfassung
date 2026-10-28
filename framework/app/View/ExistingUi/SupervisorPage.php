<?php

namespace App\View\ExistingUi;

/** Prepared authorized native report only; not parallel chrome, SQL, accounting or policy. */
final readonly class SupervisorPage
{
    private array $labels;

    /** @param array $report Complete supervisor_report_projection() output, unescaped. */
    public function __construct(public array $report, public string $search, public string $language = 'de',
        public ?array $absenceWeek = null)
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

    public function pageUrl(int $page): string
    {
        return '?'.http_build_query(['lang' => $this->language, 'user_search' => $this->search, 'page' => $page]
            + ($this->absenceWeek === null ? [] : ['team_date' => $this->absenceWeek['from'], 'team_page' => $this->absenceWeek['page']]));
    }
    /** Fixed same-page navigation only; source dates/page come from the scoped projection. */
    public function absenceWeekUrl(string $day, int $page = 1): string
    {
        return '?'.http_build_query(['lang' => $this->language, 'user_search' => $this->search,
            'page' => $this->report['page'], 'team_date' => $day, 'team_page' => $page]);
    }
}
