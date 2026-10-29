<?php

// Definitions only. The authenticated native caller owns one transaction;
// never bootstrap, connect, register UDFs, encrypt, query LDAP or nest a BEGIN.
require_once __DIR__.'/ldap_manager_mapping.php';
require_once __DIR__.'/native_ldap_session.php';
require_once __DIR__.'/ldap_settings_audit.php';

/** Legacy import plus reserved local Admin recovery; nonfatal manager conflicts stay. */
function chrono_ldap_import_locked(PDO $conn, array $entries, string $ldapHost, string $ldapPort, string $ldapUser, string $encryptedLdapPass, string $ldapBaseDN, bool $accountLifecycleV5 = false): array
{
    if (!$conn->inTransaction()) {
        throw new LogicException('LDAP import requires an owned transaction.');
    }
    if (!$accountLifecycleV5) {
        // Check the WHOLE materialized stage in the existing transaction,
        // before settings or any earlier identity can change. V5 keeps its
        // already stricter retained-identity preflight below unchanged.
        foreach ($entries as $entry) {
            if (!isset($entry['uid'][0], $entry['mail'][0])) { continue; }
            if (native_local_recovery_admin($conn, $entry['uid'][0])) {
                throw new DomainException('LDAP import refused for a local recovery identity.');
            }
        }
    }
    if ($accountLifecycleV5) {
        // Separate trusted Laravel V5 operation only. Preflight the WHOLE stage
        // before settings/departments; Native/default predecessors stay exact.
        if ($conn->query('SELECT version FROM app_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN) !== [1, 2, 3, 4, 5]) {
            throw new LogicException('The explicit V5 LDAP import profile is required.');
        }
        $check = $conn->prepare('SELECT auth_source,account_status,auth_generation FROM users WHERE username=?');
        foreach ($entries as $entry) {
            if (!isset($entry['uid'][0], $entry['mail'][0])) { continue; }
            $check->execute([$entry['uid'][0]]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);
            $check->closeCursor();
            if ($existing !== false && ($existing['auth_source'] !== 'ldap'
                || !in_array($existing['account_status'], ['active', 'inactive', 'departed'], true)
                || !is_int($existing['auth_generation']) || $existing['auth_generation'] < 1)) {
                throw new DomainException('LDAP import refused: retained identity or lifecycle conflicts.');
            }
        }
    }
    $settingsBefore = chrono_ldap_settings_audit_read($conn, $ldapBaseDN, $ldapUser);
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
    $settingsAfter = chrono_ldap_settings_audit_read($conn, $ldapBaseDN, $ldapUser);
    if ($settingsAfter === null) { throw new RuntimeException('LDAP settings journal unavailable.'); }
    chrono_ldap_settings_audit_append_locked($conn, $settingsBefore, $settingsAfter);
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
                $updateSql = $accountLifecycleV5
                    ? "UPDATE users SET email = :email, department_id = :department_id WHERE username = :username AND auth_source = 'ldap'"
                    : "UPDATE users SET auth_source = 'ldap', password = CASE WHEN auth_source = 'ldap' THEN password ELSE :placeholder END, email = :email, department_id = :department_id WHERE username = :username";
                // V5 retains the row, source, role, placeholder and lifecycle.
                // No presence/absence in this search activates or departs anyone.
                $stmt = $conn->prepare($updateSql);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':department_id', $departmentId);
                $stmt->bindParam(':username', $username);
                if (!$accountLifecycleV5) {
                    // Switching a native local account to LDAP is revocation;
                    // an ordinary repeat sync of an LDAP account is not.
                    // The preceding settings write already owns the transaction's
                    // write lock; this row cannot change underneath the selection.
                    $stmt->bindValue(':placeholder', $user->auth_source === 'ldap' ? null : native_ldap_placeholder());
                }
                $stmt->execute();
                $ldapUserId = (int)$user->id;
            } else {
                // Neuen Benutzer einfügen
                $insertSql = $accountLifecycleV5
                    ? "INSERT INTO users (username, password, email, role, department_id, auth_source, account_status, auth_generation) VALUES (:username, :password, :email, 'user', :department_id, 'ldap', 'active', 1)"
                    : "INSERT INTO users (username, password, email, role, department_id, auth_source) VALUES (:username, :password, :email, 'user', :department_id, 'ldap')";
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
