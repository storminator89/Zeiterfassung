<?php

namespace App\Http;

use Symfony\Component\HttpFoundation\AcceptHeader;

/** Initial display preference only; no request/session mutation or auth policy. */
final class InitialWebLanguage
{
    public static function resolve(mixed $storedLanguage, mixed $acceptLanguage): string
    {
        require_once dirname(__DIR__, 3).'/lib/language.php';
        if ($storedLanguage !== null) { return \valid_language($storedLanguage); }
        if (! is_string($acceptLanguage) || strlen($acceptLanguage) > 4096) { return 'de'; }

        // Reuse the installed framework parser's quality order, not a new parser.
        // Only concrete supported base languages count; otherwise keep German.
        foreach (AcceptHeader::fromString($acceptLanguage)->all() as $item) {
            if ($item->getQuality() <= 0) { continue; }
            if (preg_match('/\A([a-z]{2})(?:-[a-z0-9]+)*\z/D', strtolower($item->getValue()), $match) !== 1) { continue; }
            $language = $match[1];
            if (\valid_language($language) === $language) { return $language; }
        }

        return 'de';
    }
}
