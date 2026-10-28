<?php

namespace App\View\ExistingUi;

/** Already-authorized, presentation-only form options and request cards. */
final readonly class CorrectionsPage
{
    public const STATUS_LABELS = ['pending' => 'Offen', 'all' => 'Alle Status', 'approved' => 'Freigegeben', 'rejected' => 'Abgelehnt', 'withdrawn' => 'Zurückgenommen'];

    /** $requests contain selected preview {before:string, after:string, difference:?string, proposed_usable:bool}, never raw snapshots. */
    public function __construct(
        public array $recordOptions,
        public array $requests,
        public int $selectedRecordId = 0,
        public ?string $error = null,
        public bool $saved = false,
        public bool $enabled = false,
        public bool $selectionInvalid = false,
        public array $requestCounts = ['pending' => 0, 'all' => 0, 'approved' => 0, 'rejected' => 0, 'withdrawn' => 0],
        public string $requestStatus = 'pending',
        public int $requestTotal = 0,
        public int $requestPage = 1,
        public int $requestPages = 1,
        public array $queueSelection = [],
        public bool $withdrawalsEnabled = false,
        public array $requestDraft = [],
        public ?int $balancePreviewRequestId = null,
        public array $balancePreview = [],
    ) {}

    /** Display only after a known request rejection; never a command or stored/flash input. */
    public static function rejectedRequestDraft(array $form): array
    {
        if (($form['action'] ?? null) !== 'request') {
            return [];
        }
        $draft = [];
        foreach (['start' => 128, 'end' => 128, 'pause' => 128, 'reason' => 4096] as $field => $limit) {
            // Keep exact strings, especially explicit offsets. Do not coerce,
            // trim or silently truncate malformed/oversized submitted values.
            if (is_string($form[$field] ?? null) && strlen($form[$field]) <= $limit) {
                $draft[$field] = $form[$field];
            }
        }
        $draft['cancel'] = ($form['cancel'] ?? null) === '1';

        return $draft;
    }

    public static function queueUrl(string $status, int $page, array $selection = []): string
    {
        return 'adjustments.php?'.http_build_query(array_merge(['request_status' => $status, 'request_page' => $page], $selection));
    }

    public function balancePreviewUrl(int $requestId): string
    {
        return self::queueUrl($this->requestStatus, $this->requestPage, $this->queueSelection).'&balance_preview='.$requestId.'#balance-preview-'.$requestId;
    }

    public function pageUrl(int $page): string
    {
        return self::queueUrl($this->requestStatus, $page, $this->queueSelection);
    }
}
