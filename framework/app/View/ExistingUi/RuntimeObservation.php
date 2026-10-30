<?php

namespace App\View\ExistingUi;

/** Bounded measurements only, not readiness, backup, support-ID or repair authority. */
final readonly class RuntimeObservation
{
    private array $labels;
    public ?array $measurements;
    public ?string $checkedAt;

    public function __construct(?array $snapshot, public string $language, public bool $requested = false)
    {
        if (!in_array($language, ['de', 'en', 'zh'], true)) { throw new \InvalidArgumentException; }
        $this->labels = require dirname(__DIR__, 3).'/lang/'.$language.'/runtime-observation.php';
        if ($snapshot === null) {
            $this->checkedAt = null;
            $this->measurements = null;
            return;
        }
        $now = $snapshot['checked_at_epoch'] ?? null;
        $storage = $snapshot['storage'] ?? null;
        $terminal = $snapshot['terminal_contact'] ?? null;
        if (!$requested || !is_int($now) || $now < 1 || !is_array($storage) || !is_array($terminal)
            || !array_key_exists('available_bytes', $storage) || ($storage['minimum_bytes'] ?? null) !== 134217728) {
            throw new \UnexpectedValueException('Invalid runtime observation.');
        }
        $free = $storage['available_bytes'];
        if ($free !== null && (!is_int($free) || $free < 0)) { throw new \UnexpectedValueException; }
        $storageState = $free === null ? 'unknown' : ($free < 134217728 ? 'low' : 'sufficient');
        if (($storage['state'] ?? null) !== $storageState) { throw new \UnexpectedValueException; }
        $contactState = $terminal['state'] ?? null;
        $contact = ['state' => $contactState];
        if ($contactState !== 'not_selected') {
            $counts = $terminal['counts'] ?? null;
            $scanned = $terminal['scanned'] ?? null;
            $truncated = $terminal['truncated'] ?? null;
            $ages = $terminal['observed_age_seconds'] ?? null;
            if (!is_int($scanned) || $scanned < 0 || $scanned > 1000 || !is_bool($truncated)
                || !is_array($counts) || array_keys($counts) !== ['recent', 'stale', 'missing', 'invalid']
                || ($terminal['maximum_contact_age_seconds'] ?? null) !== 90
                || !is_array($ages) || !array_key_exists('newest', $ages) || !array_key_exists('oldest', $ages)) {
                throw new \UnexpectedValueException;
            }
            foreach ($counts as $count) {
                if (!is_int($count) || $count < 0 || $count > 1000) { throw new \UnexpectedValueException; }
            }
            $expected = $truncated ? 'incomplete' : ($scanned === 0 ? 'no_active_devices'
                : ($counts['stale'] + $counts['missing'] + $counts['invalid'] > 0 ? 'degraded' : 'recent'));
            if (array_sum($counts) !== $scanned || ($truncated && $scanned !== 1000) || $contactState !== $expected) {
                throw new \UnexpectedValueException;
            }
            if ($counts['recent'] + $counts['stale'] === 0) {
                if ($ages['newest'] !== null || $ages['oldest'] !== null) { throw new \UnexpectedValueException; }
            } elseif (!is_int($ages['newest']) || !is_int($ages['oldest']) || $ages['newest'] < 0
                || $ages['oldest'] < $ages['newest'] || $ages['oldest'] >= $now) {
                throw new \UnexpectedValueException;
            }
            $contact += ['scanned' => $scanned, 'counts' => $counts, 'truncated' => $truncated,
                'observed_age_seconds' => ['newest' => $ages['newest'], 'oldest' => $ages['oldest']]];
        }
        // Unknown fields and all source identities are deliberately not passed to the view.
        $this->measurements = ['storage' => ['state' => $storageState, 'available_bytes' => $free, 'minimum_bytes' => 134217728],
            'terminal_contact' => $contact];
        $this->checkedAt = gmdate('Y-m-d\TH:i:s\Z', $now);
    }

    public function text(string $key): string
    {
        return $this->labels[$key] ?? throw new \InvalidArgumentException('Unknown runtime observation label.');
    }

    public function url(bool $terminal = false): string
    {
        return 'admin.php?'.http_build_query(['lang' => $this->language, 'runtime_observation' => '1']
            + ($terminal ? ['runtime_terminal' => '1'] : [])).'#runtime-observation';
    }

    public function number(int $value): string
    {
        return preg_replace('/\B(?=(\d{3})+(?!\d))/', ' ', (string)$value);
    }
}
