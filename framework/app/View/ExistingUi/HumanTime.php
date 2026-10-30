<?php
namespace App\View\ExistingUi;

use DateTimeImmutable;
use DateTimeZone;

/** Display only. Original values, accounting and diagnostic flags remain unchanged. */
final class HumanTime
{
    private static function parse(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(?::\d{2})?(?:Z|[+-]\d{2}:?\d{2})?$/D', $value)) return null;
        try {
            $date=new DateTimeImmutable($value,new DateTimeZone('Europe/Berlin'));
            $errors=DateTimeImmutable::getLastErrors();
            if (is_array($errors) && ($errors['warning_count'] || $errors['error_count'])) return null;
            if (!preg_match('/(?:Z|[+-]\d{2}:?\d{2})$/D', $value)) {
                $local=str_replace('T',' ',$value);
                if (strlen($local)===16) $local.=':00';
                if ($date->format('Y-m-d H:i:s')!==$local) return null;
            }
            return $date->setTimezone(new DateTimeZone('Europe/Berlin'));
        } catch (\Throwable) { return null; }
    }
    public static function full(mixed $value): string
    {
        return self::parse($value)?->format('d.m.Y, H:i') ?? (is_string($value) ? $value : '—');
    }
    public static function clock(mixed $value, mixed $start = null): string
    {
        if ($value === 'Läuft') return 'Aktiv';
        if ($value === 'Keine Stempelzeit') return '—';
        if (is_string($value) && str_ends_with($value,' · Gutschrift')) return 'Ganztägig';
        $date=self::parse($value);
        if ($date===null) return is_string($value) ? $value : '—';
        $reference=self::parse($start);
        return $reference!==null && $date->format('Y-m-d')!==$reference->format('Y-m-d')
            ? $date->format('d.m.Y, H:i') : $date->format('H:i');
    }
}
