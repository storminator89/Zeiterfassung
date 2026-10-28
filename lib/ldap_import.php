<?php

// Definitions only. The authenticated native caller owns one transaction;
// never bootstrap, connect, register UDFs, encrypt, query LDAP or nest a BEGIN.
require_once __DIR__.'/ldap_manager_mapping.php';

/** Exact legacy import policy, including local-name conversion and nonfatal manager conflicts. */
function chrono_ldap_import_locked(PDO $conn, array $entries, string $ldapHost, string $ldapPort, string $ldapUser, string $encryptedLdapPass, string $ldapBaseDN): array
{
    if (!$conn->inTransaction()) {
        throw new LogicException('LDAP import requires an owned transaction.');
    }
    $stmt = $conn->prepare("SELECT COUNT(*) FROM ldap_settings");
    $stmt->execute();
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        $stmt = $conn->prepare("UPDATE ldap_settings SET ldap_host = ?, ldap_port = ?, ldap_user = ?, ldap_pass = ?, ldap_base_dn = ? WHERE id = (SELECT id FROM ldap_settings ORDER BY id DESC LIMIT 1)");
        $stmt->execute([$ldapHost, $ldapPort, $ldapUser, $encryptedLdapPass, $ldapBaseDN]);
    } else {
        $stmt = $conn->prepare("INSERT INTO ldap_settings (ldap_host, ldap_port, ldap_user, ldap_pass, ldap_base_dn) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$ldapHost, $ldapPort, $ldapUser, $encryptedLdapPass, $ldapBaseDN]);
    }
    $ldapIdentities = [];
    // Pass 1: Identitäten importieren, bestehende Beziehungen nicht anfassen.
    foreach ($entries as $entry) {
        if (isset($entry["uid"][0]) && isset($entry["mail"][0])) {
            $username = $entry["uid"][0];
            $email = $entry["mail"][0];
            $cn = isset($entry["cn"][0]) ? $entry["cn"][0] : '';
            $sn = isset($entry["sn"][0]) ? $entry["sn"][0] : '';
            $department = isset($entry["ou"][0]) ? $entry["ou"][0] : '';
            $managerDn = isset($entry["manager"][0]) ? $entry["manager"][0] : '';

            // Prüfen, ob der Benutzer bereits in der SQLite-Datenbank existiert
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_OBJ);

            // Abteilungs-ID aus der Datenbank abrufen oder erstellen
            $stmt = $conn->prepare("SELECT id FROM departments WHERE name = :name");
            $stmt->bindParam(':name', $department);
            $stmt->execute();
            $dept = $stmt->fetch(PDO::FETCH_OBJ);
            $departmentId = $dept ? $dept->id : null;

            if (!$departmentId && $department) {
                // Abteilung einfügen, falls nicht vorhanden
                $stmt = $conn->prepare("INSERT INTO departments (name) VALUES (:name)");
                $stmt->bindParam(':name', $department);
                $stmt->execute();
                $departmentId = $conn->lastInsertId();
            }

            if ($user) {
                // Benutzerinformationen aktualisieren
                $updateSql = "UPDATE users SET auth_source = 'ldap', email = :email, department_id = :department_id WHERE username = :username";
                $stmt = $conn->prepare($updateSql);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':department_id', $departmentId);
                $stmt->bindParam(':username', $username);
                $stmt->execute();
                $ldapUserId = (int)$user->id;
            } else {
                // Neuen Benutzer einfügen
                $insertSql = "INSERT INTO users (username, password, email, role, department_id, auth_source) VALUES (:username, :password, :email, 'user', :department_id, 'ldap')";
                $stmt = $conn->prepare($insertSql);
                $passwordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':password', $passwordHash);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':department_id', $departmentId);
                $stmt->execute();
                $ldapUserId = (int)$conn->lastInsertId();
            }
            $ldapIdentities[] = ['id' => $ldapUserId, 'dn' => $entry['dn'] ?? '', 'manager_dn' => $managerDn];
        }
    }
    $currentSupervisors = [];
    foreach ($conn->query("SELECT id, supervisor_id FROM users")->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $currentSupervisors[(int)$row['id']] = $row['supervisor_id'] === null ? null : (int)$row['supervisor_id'];
    }
    $managerPlan = chrono_ldap_manager_plan($ldapIdentities, $currentSupervisors);
    $stmt = $conn->prepare("UPDATE users SET supervisor_id = ? WHERE id = ?");
    foreach ($managerPlan['assignments'] as $ldapUserId => $supervisorId) {
        if ($currentSupervisors[$ldapUserId] !== $supervisorId) {
            $stmt->execute([$supervisorId, $ldapUserId]);
        }
    }

    return $managerPlan;
}
