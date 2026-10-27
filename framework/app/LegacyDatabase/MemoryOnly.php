<?php

namespace App\LegacyDatabase;

use PDO;
use RuntimeException;

final class MemoryOnly
{
    public static function configuration(array $config): void
    {
        if (($config['database'] ?? null) !== ':memory:'
            || isset($config['write']) || isset($config['url']) || isset($config['read'])) {
            throw new RuntimeException('This increment accepts anonymous memory fixtures only.');
        }
    }

    public static function connection(PDO $pdo): void
    {
        $databases = $pdo->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC);

        // Also reject ATTACHed databases and named/file-backed memory URIs.
        foreach ($databases as $database) {
            if (! in_array($database['name'], ['main', 'temp'], true) || $database['file'] !== '') {
                throw new RuntimeException('File or attached database ingress is not supported.');
            }
        }
    }
}
