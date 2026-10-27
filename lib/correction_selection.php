<?php
/** Native correction-selector helpers; no session, database or writer access. */
function correction_selection_id(mixed $value, bool $allowNew = false): int {
    if ((!is_string($value) && !is_int($value)) || !preg_match('/^(0|[1-9][0-9]*)$/D', (string)$value)) {
        throw new InvalidArgumentException('Buchung nicht verfügbar. Bitte erneut aus den eigenen Buchungen auswählen.');
    }
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $allowNew ? 0 : 1]]);
    if ($id === false) {
        throw new InvalidArgumentException('Buchung nicht verfügbar. Bitte erneut aus den eigenen Buchungen auswählen.');
    }
    return $id;
}

/** The optional extra row must come from the caller's own-user-scoped lookup. */
function correction_selection_options(array $records, int $selectedId, ?array $ownRecord): array {
    if ($selectedId === 0) { return $records; }
    foreach ($records as $record) {
        if ((int)$record['id'] === $selectedId) { return $records; }
    }
    if ($ownRecord === null || (int)$ownRecord['id'] !== $selectedId) {
        throw new InvalidArgumentException('Buchung nicht verfügbar. Bitte erneut aus den eigenen Buchungen auswählen.');
    }
    $records[] = $ownRecord;
    return $records;
}
