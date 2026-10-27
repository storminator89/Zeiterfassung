<?php

namespace App\View\ExistingUi;

/**
 * Explicit, already-authorized display data only. No queries, clocks, accounting,
 * actor construction, HTML fragments or activation flags belong in this model.
 *
 * $status is the existing ChronoReads::attendanceStatus() shape. The remaining
 * metrics must come from a reviewed shared report projection; absent is unknown,
 * never a fabricated zero. Navigation visibility is not an authorization gate.
 */
final readonly class AttendancePage
{
    public string $state;

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
    ) {
        if ($userId < 1 || ! in_array($role, ['user', 'supervisor', 'admin'], true)
            || ! in_array($theme, ['light', 'dark'], true)
            || ($status['success'] ?? null) !== true
            || ! in_array($status['state'] ?? null, ['absent', 'working', 'paused'], true)) {
            throw new \InvalidArgumentException('Invalid existing attendance display data.');
        }

        $this->state = $status['state'];
    }

    public function statusLabel(): string
    {
        // Preserve the existing labels, including their current German-only scope.
        return ['absent' => 'Nicht angemeldet', 'working' => 'Arbeitszeit läuft', 'paused' => 'Pause läuft'][$this->state];
    }
}
