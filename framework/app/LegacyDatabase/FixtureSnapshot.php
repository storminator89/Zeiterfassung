<?php

namespace App\LegacyDatabase;

use PDO;

final class FixtureSnapshot
{
    public static function capture(PDO $pdo, array $mapping): array
    {
        MemoryOnly::connection($pdo);
        $counts = [];
        $data = [];
        $exists = $pdo->prepare("SELECT 1 FROM sqlite_schema WHERE type='table' AND name=?");
        foreach ($mapping['tables'] as $table) {
            $name = $table['name'];
            $exists->execute([$name]);
            if ($exists->fetchColumn() === false) {
                continue;
            }
            // Inventory is committed source, not user-supplied identifiers.
            $rows = $pdo->query('SELECT * FROM "'.$name.'" ORDER BY '.implode(',', $table['primary_key']))->fetchAll(PDO::FETCH_ASSOC);
            $counts[$name] = count($rows);
            $data[$name] = $rows;
        }
        $data['sqlite_sequence'] = $pdo->query("SELECT name,seq FROM sqlite_sequence WHERE name<>'framework_migrations' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        ksort($counts);
        ksort($data);

        return ['counts' => $counts, 'data_signature' => hash('sha256', json_encode($data, JSON_THROW_ON_ERROR))];
    }
}
