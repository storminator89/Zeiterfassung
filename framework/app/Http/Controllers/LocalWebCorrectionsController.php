<?php

namespace App\Http\Controllers;

use App\Chrono\ChronoReads;
use App\Chrono\LocalWebCorrections;
use App\Chrono\LocalWebCorrectionWithdrawals;
use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;
use App\View\ExistingUi\AttendancePage;
use App\View\ExistingUi\Copy;
use App\View\ExistingUi\CorrectionsPage;
use App\View\ExistingUi\Interactions;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/** Native correction forms only. All commands/permissions/SQL/audit stay in #78. */
final class LocalWebCorrectionsController extends Controller
{
    public function __construct(
        private ChronoReads $reads,
        private LocalWebCorrections $corrections,
        private Factory $auth,
        private Interactions $interactions,
        private ?LocalWebCorrectionWithdrawals $withdrawals = null,
        private ?Repository $config = null,
    ) {}

    public function index(Request $request): View
    {
        return $this->page($request);
    }

    // Only the POST route calls this method; GET/method override never dispatches a command.
    public function store(Request $request): View|RedirectResponse
    {
        $commandCompleted = false;
        try {
            require_once dirname(__DIR__, 4).'/lib/correction_selection.php';
            // Raw form bag only. No actor, role, source, audit, date or boolean normalization.
            $command = array_intersect_key($request->request->all(), array_flip([
                'action', 'record_id', 'cancel', 'start', 'end', 'pause', 'reason',
                'id', 'decision', 'review_reason',
                'location', 'note', 'metadata_confirm',
            ]));
            if (($command['action'] ?? null) === 'withdraw') {
                if (! $this->withdrawalsEnabled()) {
                    return $this->page($request, 'Rücknahme ist in diesem Profil nicht verbunden.');
                }
                $withdrawal = array_diff_key($request->request->all(), array_flip(['_token', 'csrf_token']));
                $this->withdrawals->withdraw($withdrawal);
                $commandCompleted = true;
                [$status, $requestedPage] = $this->queueFilters($request);

                return new RedirectResponse(CorrectionsPage::queueUrl($status, $requestedPage, $this->queueSelection($request, false, 0)).'&saved=1');
            }
            $selectedRecordId = 0;
            if (in_array($command['action'] ?? null, ['request', 'request_metadata_v1'], true)) {
                // Native request-only parser: explicit new entry 0 is valid only in POST.
                try {
                    $selectedRecordId = \correction_selection_id($command['record_id'] ?? null, ($command['action'] ?? null) === 'request');
                } catch (\InvalidArgumentException $selectionError) {
                    return $this->page($request, $selectionError->getMessage());
                }
            }
            $this->corrections->apply($command);
            $commandCompleted = true;

            [$status, $requestedPage] = $this->queueFilters($request);

            return new RedirectResponse(CorrectionsPage::queueUrl($status, $requestedPage, $this->queueSelection($request, false, $selectedRecordId)).'&saved=1');
        } catch (AuthenticationException $error) {
            throw $error; // Preserve the registrar's protected-route auth response.
        } catch (\AttendanceError|InvalidArgumentException $error) {
            // Only known domain rejections can retain a display-only form draft.
            // Never offer it after a returned command or an unknown write outcome.
            return $this->page($request, $error->getMessage(), ! $commandCompleted
                && $error instanceof \AttendanceError && in_array($error->status, [400, 409], true));
        } catch (Throwable) {
            return $this->page($request, ($command['action'] ?? null) === 'withdraw'
                ? 'Rücknahme konnte nicht bestätigt werden. Bitte neu laden und gespeicherten Stand prüfen; nicht automatisch wiederholen.'
                : (in_array($command['action'] ?? null, ['request', 'request_metadata_v1'], true)
                    ? 'Antrag konnte nicht bestätigt werden. Bitte neu laden und gespeicherten Stand prüfen; nicht automatisch wiederholen.'
                    : 'Entscheidung konnte nicht bestätigt werden. Bitte neu laden und gespeicherten Stand prüfen; nicht automatisch wiederholen.'));
        }
    }

    private function withdrawalsEnabled(): bool
    {
        return $this->withdrawals !== null
            && $this->config?->get('chrono_corrections.withdrawals_enabled', false) === true;
    }

    /** Same native fallback rules; query fields select a queue, never a command. */
    private function queueFilters(Request $request): array
    {
        $status = $request->query('request_status');
        $status = is_string($status) && isset(CorrectionsPage::STATUS_LABELS[$status]) ? $status : 'pending';
        $page = filter_var($request->query('request_page', 1), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;

        return [$status, $page];
    }

    private function queueSelection(Request $request, bool $invalid, int $selectedId): array
    {
        $query = $request->query->all();
        $selection = array_key_exists('record_id', $query)
            ? ['record_id' => is_string($query['record_id']) ? $query['record_id'] : 'invalid'] : [];
        $form = $request->request->all();
        if ($request->isMethod('POST') && in_array($form['action'] ?? null, ['request', 'request_metadata_v1'], true)) {
            $selection = $invalid ? ['record_id' => 'invalid'] : ($selectedId ? ['record_id' => $selectedId] : []);
        }

        return $selection;
    }

    private function page(Request $request, ?string $error = null, bool $retainRejectedDraft = false): View
    {
        // Definitions only: reuse the native pure display formatter, never its bootstrap.
        require_once dirname(__DIR__, 4).'/lib/attendance.php';
        require_once dirname(__DIR__, 4).'/lib/correction_presentation.php';
        require_once dirname(__DIR__, 4).'/lib/correction_selection.php';
        require_once dirname(__DIR__, 4).'/lib/correction_metadata.php';
        $actor = $this->auth->guard('web')->user();
        $language = $request->query('lang', $request->session()->get('lang', 'de'));
        $language = is_string($language) && in_array($language, ['de', 'en', 'zh'], true) ? $language : 'de';
        $request->session()->put('lang', $language);

        // Order own rows by instant/id before taking the newest 200 options.
        // Explicit older own selection may add one; never fall back to new entry 0.
        $allOwnRecords = $this->reads->reportRows(chronological: true);
        $recordOptions = array_slice($allOwnRecords, 0, 200);
        $selectedRecordId = 0;
        $selectionInvalid = false;
        $form = $request->request->all();
        $query = $request->query->all();
        try {
            if ($request->isMethod('POST') && in_array($form['action'] ?? null, ['request', 'request_metadata_v1'], true)) {
                $selectedRecordId = \correction_selection_id($form['record_id'] ?? null, ($form['action'] ?? null) === 'request');
            } elseif ($request->isMethod('GET') && array_key_exists('record_id', $query)) {
                $selectedRecordId = \correction_selection_id($query['record_id']);
            }
            if ($selectedRecordId !== 0) {
                $ownRecord = null;
                foreach ($allOwnRecords as $ownRow) {
                    if ((int) $ownRow['id'] === $selectedRecordId) {
                        $ownRecord = $ownRow;
                        break;
                    }
                }
                $recordOptions = \correction_selection_options($recordOptions, $selectedRecordId, $ownRecord);
            }
        } catch (\InvalidArgumentException $selectionError) {
            $selectionInvalid = true;
            $error = $selectionError->getMessage();
        }
        $records = array_map(static fn (array $row): array => [
            'id' => (int) $row['id'], 'start' => $row['startzeit'], 'end' => $row['endzeit'],
        ], $recordOptions);
        [$requestStatus, $requestedPage] = $this->queueFilters($request);
        $preview = $request->isMethod('GET') ? $request->query('balance_preview') : null;
        $previewRequestId = is_string($preview) && preg_match('/\A[1-9][0-9]*\z/D', $preview) === 1
            ? filter_var($preview, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        $previewRequestId = $previewRequestId === false ? null : $previewRequestId;
        $queue = $this->reads->correctionsPage($requestStatus, $requestedPage, $previewRequestId);
        $requests = array_map(static function (array $row): array {
            $preview = \chrono_correction_preview($row);
            return [
                'id' => (int) $row['id'], 'user_id' => (int) $row['user_id'],
                'username' => $row['username'], 'status' => $row['status'],
                'cancel' => (bool) $row['cancel'],
                // Native pure Berlin display formatting, not input parsing/accounting.
                'start' => $preview['proposed_usable'] ? \attendance_local((int) $row['proposed_start']) : '',
                'end' => $preview['proposed_usable'] ? \attendance_local((int) $row['proposed_end']) : '',
                'pause' => $preview['proposed_usable'] ? (int) $row['proposed_pause'] : null, 'reason' => $row['reason'],
                'review_reason' => $row['review_reason'],
                // Selected time fields only; no original snapshot is passed to Blade.
                'preview' => $preview,
                'metadata_preview' => \correction_metadata_v1_preview($row),
            ];
        }, $queue['records']);

        // Display only for the same still-visible review form after a fresh
        // scoped read. This never grants permission or retains the decision.
        $reviewDraft = $retainRejectedDraft && $request->isMethod('POST')
            ? CorrectionsPage::rejectedReviewDraft($form) : [];
        $reviewable = $this->interactions->corrections
            && in_array($actor->role, ['admin', 'supervisor'], true)
            && array_filter($requests, static fn (array $row): bool =>
                $row['id'] === ($reviewDraft['id'] ?? null)
                && $row['status'] === 'pending'
                && $row['user_id'] !== (int) $actor->getAuthIdentifier());
        if (! $reviewable) {
            $reviewDraft = [];
        }

        $withdrawalDraft = $retainRejectedDraft && $request->isMethod('POST')
            ? CorrectionsPage::rejectedWithdrawalDraft($form) : [];
        $withdrawable = $this->withdrawalsEnabled() && $this->interactions->corrections
            && $this->interactions->correctionWithdrawals
            && array_filter($queue['records'], static fn (array $row): bool =>
                (int) $row['id'] === ($withdrawalDraft['id'] ?? null)
                && (int) $row['user_id'] === (int) $actor->getAuthIdentifier()
                && $row['status'] === 'pending'
                && array_key_exists('reviewer_id', $row) && $row['reviewer_id'] === null
                && array_key_exists('reviewed_at', $row) && $row['reviewed_at'] === null
                && array_key_exists('review_reason', $row) && $row['review_reason'] === null);
        if (! $withdrawable) {
            $withdrawalDraft = [];
        }

        return view('chrono.corrections', [
            // Reuse the existing shared chrome contract; no legacy header/bootstrap include.
            'page' => new AttendancePage(
                new Copy($language), (int) $actor->getAuthIdentifier(), $actor->username,
                $actor->role, $this->reads->attendanceStatus(),
                interactions: $this->interactions, activePage: 'adjustments.php',
            ),
            'corrections' => new CorrectionsPage(
                $records, $requests, $selectedRecordId, $error,
                $error === null && $request->query->has('saved'), $this->interactions->corrections,
                selectionInvalid: $selectionInvalid,
                requestCounts: $queue['counts'], requestStatus: $queue['status'],
                requestTotal: $queue['total'], requestPage: $queue['page'], requestPages: $queue['pages'],
                queueSelection: $this->queueSelection($request, $selectionInvalid, $selectedRecordId),
                withdrawalsEnabled: $this->withdrawalsEnabled() && $this->interactions->corrections
                    && $this->interactions->correctionWithdrawals,
                balancePreviewRequestId: $previewRequestId,
                balancePreview: $queue['balancePreview'] ?? [],
                requestDraft: $retainRejectedDraft && ! $selectionInvalid && $request->isMethod('POST')
                    ? CorrectionsPage::rejectedRequestDraft($form) : [],
                reviewDraft: $reviewDraft,
                metadataDraft: $retainRejectedDraft && ! $selectionInvalid && $request->isMethod('POST')
                    ? \correction_metadata_v1_rejected_draft($form) : [],
                // Main PR318 admits the same scoped business actions for existing LDAP roles.
                metadataEnabled: $this->interactions->corrections,
                withdrawalDraft: $withdrawalDraft,
            ),
        ]);
    }
}
