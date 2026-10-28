<?php

namespace App\Chrono;

/** Transport-free read boundary for the existing pages and later API adapters. */
interface ChronoReads
{
    /** Existing attendance.php?format=status shape; always the authenticated user. */
    public function attendanceStatus(): array;

    /** Own raw rows; callers explicitly opt into native instant/id ordering before paging. */
    public function reportRows(bool $chronological = false): array;

    /** Native latest own work row: NULL-open first, then creation ID; UI columns only. */
    public function latestWorkRecord(): array;

    /** Native own Home statistics plus invalid/conflict diagnostics; unresolved totals are not numeric. */
    public function homeSummary(): array;

    /** Own native dashboard metrics/diagnostics and cached selected-week inputs, in one snapshot. */
    public function calendarWeek(mixed $calendarDate = null): array;

    /** Complete native admin/direct-team report; search/page never narrow diagnostic scope. */
    public function teamReport(string $userSearch = '', mixed $page = null): array;

    /** Existing API own-only identity, including the current wire field types. */
    public function apiIdentity(): array;

    /** Authoritative own attendance state/record_id; no reporting rows or selector. */
    public function apiAttendanceStatus(): array;

    /** Existing API UTC SQL filtering/paging; no subject selector or domain math. */
    public function apiTimeEntries(ApiTimeEntriesQuery $query): array;

    /** Completed start-day totals; subject is a selector, never an actor identity. */
    public function period(string $start, string $end, ?int $subject = null): array;

    /** Existing adjustments.php list, including final decisions and original snapshots. */
    public function corrections(): array;

    /** Native queue plus optional one visible own-pending Home preview, in one snapshot. */
    public function correctionsPage(string $status = 'pending', int $page = 1, ?int $previewRequestId = null): array;

    /** Existing history.php cursor/order/scope, not a journal mutation API. */
    public function history(int $before = PHP_INT_MAX): array;

    /** Native validated filters narrow the same history scope; invalid means no audit query. */
    public function historyQuery(HistoryQuery $query): array;

    /** Same anchor page plus bounded allowlisted structural references in one fresh snapshot. */
    public function historyCorrelations(HistoryQuery $query): array;

    /** Existing work_models.php list: own models, or all for current admins. */
    public function workModels(): array;

    /** Native paged versions, scoped selection and today's model for the same owner, in one snapshot. */
    public function workModelsPage(int $page = 1, ?int $selectedModelId = null): array;

    /** Existing Admin pause-rule rows; fresh local-admin authorization in the snapshot. */
    public function adminPauseSettings(): array;

    /** Complete native department list, only after a fresh current local-Admin check. */
    public function adminDepartments(): array;

    /** Read-only native device/site/contact display; fresh local Admin, no credential fields. */
    public function adminTerminalDevices(): array;

    /** Native name-ordered Site options for device enrollment; current local Admin only. */
    public function adminTerminalPairingSites(): array;

    /** Native effective module checkbox and server-gate presence, current local Admin only. */
    public function adminTerminalModuleSettings(): array;

    /** Existing configured terminal-access display only; fresh local Admin, never PIN/card hashes. */
    public function adminTerminalCredentials(): array;

    /** Separately enabled revocation form: current local Admin, display fields plus target user ID. */
    public function adminTerminalCredentialRevocationTargets(): array;

    /** Native search/order/ten-row paging, current local Admin, UI columns only. */
    public function adminUsersPage(string $search = '', int $page = 1): array;

    /** Complete native create-form supervisor options; current local Admin only. */
    public function adminSupervisorOptions(): array;

    /** Fresh original user-modal fields only; no credentials/tokens/source projection. */
    public function adminUserDetail(mixed $identifier): array;

    /** Native work-model target options, only after a fresh current-admin check. */
    public function workModelTargets(): array;
}
