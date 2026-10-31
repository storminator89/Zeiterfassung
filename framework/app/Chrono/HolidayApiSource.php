<?php
namespace App\Chrono;

use InvalidArgumentException;
use RuntimeException;

/** Fixed public BW source only. No request URL, credentials, redirects or database work. */
final class HolidayApiSource
{
    public static function year(mixed $year): string
    {
        if (!is_string($year) || !preg_match('/\A[1-9][0-9]{0,3}\z/D',$year)) {
            throw new InvalidArgumentException('Bitte ein gültiges Jahr wählen.');
        }
        return $year;
    }

    public function load(mixed $year): string
    {
        $year=self::year($year);
        $handle=curl_init('https://feiertage-api.de/api/?jahr='.$year.'&nur_land=BW');
        if ($handle===false) { throw new RuntimeException('Feiertagsquelle nicht erreichbar.'); }
        $body='';
        try {
            curl_setopt_array($handle,[
                CURLOPT_HTTPHEADER=>['Accept: application/json'], CURLOPT_FOLLOWLOCATION=>false,
                CURLOPT_MAXREDIRS=>0, CURLOPT_PROTOCOLS_STR=>'https', CURLOPT_REDIR_PROTOCOLS_STR=>'https',
                CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
                CURLOPT_CONNECTTIMEOUT_MS=>2500, CURLOPT_TIMEOUT_MS=>8000,
                CURLOPT_WRITEFUNCTION=>static function($curl,string $chunk) use (&$body):int {
                    if (strlen($body)+strlen($chunk)>131072) { return 0; }
                    $body.=$chunk;return strlen($chunk);
                },
            ]);
            $ok=curl_exec($handle);
            $status=curl_getinfo($handle,CURLINFO_RESPONSE_CODE);
            $type=curl_getinfo($handle,CURLINFO_CONTENT_TYPE);
            return self::validatedResponse($year,$status,$type,$ok===false?null:$body);
        } finally { unset($handle); }
    }

    public static function validatedResponse(mixed $year,int $status,mixed $type,?string $body): string
    {
        $year=self::year($year);
        if ($status!==200 || !is_string($type) || !preg_match('~\Aapplication/json(?:\s*;|\z)~i',$type)
            || $body===null || $body==='' || strlen($body)>131072) {
            throw new RuntimeException('Feiertage konnten nicht geladen werden. Bitte später erneut versuchen.');
        }
        require_once dirname(__DIR__,3).'/lib/holiday_refill.php';
        // The same bounded schema/year/region/date/duplicate/hint checks as the writer.
        \chrono_holiday_refill_stage($year,'BW',$body);
        return $body;
    }
}
