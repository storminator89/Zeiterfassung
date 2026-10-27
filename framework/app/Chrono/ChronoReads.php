<?php

namespace App\Chrono;

/** Transport-free read boundary for the existing pages and later API adapters. */
interface ChronoReads
{
    /** Existing attendance.php?format=status shape; always the authenticated user. */
    public function attendanceStatus(): array;

    /** Unmodified own reporting/export rows, newest start first, including open rows. */
    public function reportRows(): array;

    /** Native home statistics and stored weekly holidays; authenticated own user only. */
    public function homeSummary(): array;

    /** Existing API own-only identity, including the current wire field types. */
    public function apiIdentity(): array;

    /** Existing API UTC SQL filtering/paging; no subject selector or domain math. */
    public function apiTimeEntries(ApiTimeEntriesQuery $query): array;

    /** Completed start-day totals; subject is a selector, never an actor identity. */
    public function period(string $start, string $end, ?int $subject = null): array;

    /** Existing adjustments.php list, including final decisions and original snapshots. */
    public function corrections(): array;

    /** Existing history.php cursor/order/scope, not a journal mutation API. */
    public function history(int $before = PHP_INT_MAX): array;

    /** Existing work_models.php list: own models, or all for current admins. */
    public function workModels(): array;

    /** Native work-model target options, only after a fresh current-admin check. */
    public function workModelTargets(): array;
}
