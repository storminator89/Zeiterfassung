<?php

namespace App\View\ExistingUi;

/**
 * Explicit, already-authorized display data only. No queries, clocks, accounting,
 * actor construction or HTML fragments belong in this model.
 *
 * $status is the existing ChronoReads::attendanceStatus() shape. The remaining
 * metrics must come from a reviewed shared report projection; absent is unknown,
 * never a fabricated zero. Navigation visibility is not an authorization gate.
 */
final readonly class AttendancePage
{
    public string $state;
    public bool $reportUnresolved;

    /** @param list<array{date: string, name: string}> $holidaysThisWeek Already formatted display dates. */
    public function __construct(
        public Copy $copy,
        public int $userId,
        public string $username,
        public string $role,
        array $status,
        public string $theme = 'light',
        public ?int $workingDaysThisMonth = null,
        public ?string $currentMonthName = null,
        public ?string $totalOverHoursFormatted = null,
        public ?bool $positiveOvertime = null,
        public ?string $dailyHoursFormatted = null,
        public array $holidaysThisWeek = [],
        public Interactions $interactions = new Interactions,
        public string $activePage = 'index.php',
        public int $reportInvalidCount = 0,
        bool $reportUnresolved = false,
    ) {
        if ($userId < 1 || ! in_array($role, ['user', 'supervisor', 'admin'], true)
            || ! in_array($theme, ['light', 'dark'], true)
            || ! in_array($activePage, ['index.php', 'attendance.php', 'adjustments.php', 'work_models.php', 'history.php', 'supervisor.php', 'dashboard.php'], true)
            || ($status['success'] ?? null) !== true
            || ! in_array($status['state'] ?? null, ['absent', 'working', 'paused'], true)) {
            throw new \InvalidArgumentException('Invalid existing attendance display data.');
        }

        $this->state = $status['state'];
        $this->reportUnresolved = $reportUnresolved || ($status['report_finality']['all'] ?? false) === true;
    }

    public function overtimeLabel(): string
    {
        // A newer status snapshot may discover a conflict after summary loading.
        return $this->reportUnresolved ? 'Klärung offen' : ($this->reportInvalidCount > 0 ? 'Unvollständig' : ($this->totalOverHoursFormatted ?? '—'));
    }

    public function overtimePresentation(): string
    {
        return $this->reportUnresolved ? 'unresolved' : ($this->reportInvalidCount > 0 ? 'incomplete' : ($this->totalOverHoursFormatted === null ? 'unavailable' : 'current'));
    }

    public function statusLabel(): string
    {
        // Preserve the existing labels, including their current German-only scope.
        return ['absent' => 'Nicht angemeldet', 'working' => 'Arbeitszeit läuft', 'paused' => 'Pause läuft'][$this->state];
    }
}
