<?php

namespace App\LegacyDatabase;

use PDO;

final class SourceSnapshot
{
    // Read-only streaming digest: neither copies nor returns personal row values.
    public static function capture(PDO $pdo, array $mapping): array
    {
        $counts = [];
        $digest = hash_init('sha256');
        $exists = $pdo->prepare("SELECT 1 FROM sqlite_schema WHERE type='table' AND name=?");
        foreach ($mapping['tables'] as $table) {
            $name = $table['name'];
            $exists->execute([$name]);
            if ($exists->fetchColumn() === false) {
                continue;
            }
            hash_update($digest, json_encode($name, JSON_THROW_ON_ERROR)."\n");
            $statement = $pdo->query('SELECT * FROM "'.$name.'" ORDER BY '.implode(',', $table['primary_key']));
            $counts[$name] = 0;
            while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                hash_update($digest, json_encode($row, JSON_THROW_ON_ERROR)."\n");
                $counts[$name]++;
            }
            $statement->closeCursor();
        }
        $sequence = $pdo->query("SELECT name,seq FROM sqlite_sequence WHERE name<>'framework_migrations' ORDER BY name");
        hash_update($digest, "sqlite_sequence\n");
        while (($row = $sequence->fetch(PDO::FETCH_ASSOC)) !== false) {
            hash_update($digest, json_encode($row, JSON_THROW_ON_ERROR)."\n");
        }
        ksort($counts);

        return ['counts' => $counts, 'data_signature' => hash_final($digest)];
    }
}
