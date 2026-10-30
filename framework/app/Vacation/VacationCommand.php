<?php

namespace App\Vacation;

use InvalidArgumentException;

/** Closed, actor-free command. Identity, source, clocks and audit context are never form fields. */
final readonly class VacationCommand
{
    private function __construct(
        public string $action,
        public string $commandId,
        public string $reason,
        public ?int $requestId,
        public ?int $revision,
        public ?string $startDate,
        public ?string $endDate,
        public ?string $planHash,
    ) {}

    public static function fromArray(array $input): self
    {
        $action = $input['action'] ?? null;
        if (!is_string($action) || !in_array($action, ['request', 'approve', 'reject', 'withdraw',
            'request_cancel', 'approve_cancel', 'reject_cancel'], true)) {
            throw new InvalidArgumentException('Ungültige Urlaubsaktion.');
        }
        $keys = ['action', 'command_id', 'reason'];
        $keys = [...$keys, ...($action === 'request' ? ['start_date', 'end_date'] : ['request_id', 'revision'])];
        if ($action === 'approve') { $keys[] = 'plan_hash'; }
        if (count($input) !== count($keys) || array_diff($keys, array_keys($input)) !== []) {
            throw new InvalidArgumentException('Nur die vollständigen Felder dieser Urlaubsaktion senden.');
        }
        $id = $input['command_id'];
        $reason = $input['reason'];
        if (!is_string($id) || preg_match('/\A[0-9a-f]{32}\z/', $id) !== 1
            || !is_string($reason) || $reason === '' || trim($reason) !== $reason || strlen($reason) > 2000
            || preg_match('//u', $reason) !== 1 || preg_match('/[\x00-\x1f\x7f]/', $reason) !== 0) {
            throw new InvalidArgumentException('Ereigniskennung und eine kurze gültige Begründung sind erforderlich.');
        }
        $start = $end = null;
        $request = $revision = null;
        $plan = null;
        if ($action === 'request') {
            if (!is_string($input['start_date']) || !is_string($input['end_date'])) {
                throw new InvalidArgumentException('Urlaubszeitraum prüfen.');
            }
            self::range($input['start_date'], $input['end_date']);
            $start = $input['start_date'];
            $end = $input['end_date'];
        } else {
            $request = self::positiveInteger($input['request_id']);
            $revision = self::positiveInteger($input['revision']);
        }
        if ($action === 'approve') {
            $plan = $input['plan_hash'];
            if (!is_string($plan) || preg_match('/\A[0-9a-f]{64}\z/', $plan) !== 1) {
                throw new InvalidArgumentException('Die aktuelle Gutschriftvorschau muss bestätigt werden.');
            }
        }
        return new self($action, $id, $reason, $request, $revision, $start, $end, $plan);
    }

    public static function positiveInteger(mixed $value): int
    {
        if (is_int($value) && $value > 0) { return $value; }
        if (!is_string($value) || preg_match('/\A[1-9][0-9]*\z/', $value) !== 1
            || strlen($value) > strlen((string)PHP_INT_MAX)
            || (strlen($value) === strlen((string)PHP_INT_MAX) && strcmp($value, (string)PHP_INT_MAX) > 0)) {
            throw new InvalidArgumentException('Exakte positive Antragskennung und Revision erforderlich.');
        }
        return (int)$value;
    }

    /** Existing Berlin absence range, additionally bounded to 366 inclusive calendar days. */
    public static function range(string $start, string $end): array
    {
        require_once dirname(__DIR__, 3).'/lib/attendance.php';
        require_once dirname(__DIR__, 3).'/lib/practice.php';
        foreach ([$start, $end] as $day) {
            if (preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $day) !== 1 || str_starts_with($day, '0000-')) {
                throw new InvalidArgumentException('Gültige Berliner Kalendertage im Format JJJJ-MM-TT erforderlich.');
            }
        }
        [$a, $b] = \practice_absence_range($start, $end, 'Urlaub');
        if ($a->diff($b)->days >= 366) {
            throw new InvalidArgumentException('Ein Urlaubsantrag umfasst höchstens 366 Kalendertage einschließlich Anfang und Ende.');
        }
        return [$a, $b];
    }

    /** Canonical semantics, not raw form order; the actor is bound by the separate replay key. */
    public function payloadHash(): string
    {
        return hash('sha256', json_encode([
            'version' => 1, 'action' => $this->action, 'reason' => $this->reason,
            'request_id' => $this->requestId === null ? null : (string)$this->requestId,
            'revision' => $this->revision, 'start_date' => $this->startDate, 'end_date' => $this->endDate,
            'plan_hash' => $this->planHash,
        ], JSON_THROW_ON_ERROR));
    }
}
