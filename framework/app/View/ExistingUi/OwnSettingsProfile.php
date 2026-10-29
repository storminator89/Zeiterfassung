<?php

namespace App\View\ExistingUi;

/** Minimal validated own display only: never an account model or approval authority. */
final readonly class OwnSettingsProfile
{
    private array $labels;
    public bool $available;
    public ?string $date;
    public array $department;
    public array $supervisor;
    public ?array $model;

    public function __construct(?array $snapshot, string $language)
    {
        if (!in_array($language, ['de', 'en', 'zh'], true)) {
            throw new \InvalidArgumentException('Unsupported settings profile language.');
        }
        $this->labels = require dirname(__DIR__, 3).'/lang/'.$language.'/settings-profile.php';
        $this->available = $snapshot !== null;
        if ($snapshot === null) {
            $this->date = null;
            $this->department = $this->supervisor = [];
            $this->model = null;
            return;
        }
        $this->date = self::date($snapshot['date'] ?? null);
        $this->department = self::reference($snapshot['department'] ?? null);
        $this->supervisor = self::reference($snapshot['supervisor'] ?? null);
        if (!array_key_exists('model', $snapshot)) {
            throw new \UnexpectedValueException('Invalid own settings profile.');
        }
        $model = $snapshot['model'];
        if ($model === null) {
            $this->model = null; // Missing stored model is not a zero target or a legacy fallback.
            return;
        }
        if (!is_array($model) || !is_array($model['weekday_minutes'] ?? null)
            || array_keys($model['weekday_minutes']) !== range(0, 6)) {
            throw new \UnexpectedValueException('Invalid own settings profile.');
        }
        $from = self::date($model['valid_from'] ?? null);
        if (strcmp($from, $this->date) > 0) {
            throw new \UnexpectedValueException('Invalid own settings profile.');
        }
        foreach ($model['weekday_minutes'] as $minutes) {
            if (!is_int($minutes) || $minutes < 0 || $minutes > 1440) {
                throw new \UnexpectedValueException('Invalid own settings profile.');
            }
        }
        // Drop internal model identity and every unknown field before the view.
        $this->model = ['valid_from' => $from, 'weekday_minutes' => $model['weekday_minutes']];
    }

    private static function date(mixed $value): string
    {
        if (!is_string($value) || preg_match('/\A([0-9]{4})-([0-9]{2})-([0-9]{2})\z/', $value, $parts) !== 1
            || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            throw new \UnexpectedValueException('Invalid own settings profile.');
        }
        return $value;
    }

    private static function reference(mixed $value): array
    {
        if (!is_array($value) || !in_array($value['status'] ?? null, ['unassigned', 'resolved', 'invalid', 'missing', 'unknown'], true)) {
            throw new \UnexpectedValueException('Invalid own settings profile.');
        }
        $name = $value['status'] === 'resolved' ? ($value['name'] ?? null) : null;
        if ($value['status'] === 'resolved' && (!is_string($name) || trim($name) === '' || strlen($name) > 1024 || preg_match('//u', $name) !== 1)) {
            throw new \UnexpectedValueException('Invalid own settings profile.');
        }
        return ['status' => $value['status'], 'name' => $name];
    }

    public function text(string $key): string
    {
        $label = $this->labels[$key] ?? null;
        return is_string($label) ? $label : throw new \InvalidArgumentException('Unknown settings profile label.');
    }

    public function referenceName(string $kind): string
    {
        $reference = match ($kind) {
            'department' => $this->department,
            'supervisor' => $this->supervisor,
            default => throw new \InvalidArgumentException('Unknown settings profile reference.'),
        };
        return $reference['status'] === 'resolved' ? $reference['name']
            : $this->text($reference['status'] === 'unassigned' ? 'unassigned' : 'unresolved');
    }

    public function weekday(int $index): string
    {
        return $this->labels['weekdays'][$index] ?? throw new \InvalidArgumentException('Invalid weekday.');
    }

    public function duration(int $minutes): string
    {
        return sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /** Stored seven-day rule only; no dated target, holiday or balance calculation. */
    public function weeklyDuration(): ?string
    {
        return $this->model === null ? null : $this->duration(array_sum($this->model['weekday_minutes']));
    }
}
