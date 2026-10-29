# #32: Terminal-Modul und Standorte im bestehenden Journal

Begrenzter Quellfortschritt auf Main
`2cb384f9cce33eb08da3d4d06b4d41f2c17a209f`, Baum
`454ce4db6338d5ab46de1e3c734e2b29111e844e`. Der bereits integrierte
Pausenregel-Nachweis bleibt unverändert. **#32 bleibt offen**, insbesondere für
LDAP, Geräte-/Zugangsänderungen und alte Native-V2-Userfelder.

## Ausschließlich zwei geheimnisfreie Konfigurations-Entities

Die bestehende Standortanlage schreibt eine `terminal_sites`-INSERT-Entity mit
`id` und `name`. Die Site-ID wird unmittelbar nach der Anlage und vor dem
Journalinsert erfasst; kein Audit-ID-/LastInsertId-Verwechseln. Ein vorhandener
Modulschalter wird als `terminal_settings`-UPDATE mit vollständigen alten/neuen
`id`-/`enabled`-Werten protokolliert. Auch ein erfolgreicher No-op-Speichervorgang
bleibt ein UPDATE mit gleichen Werten. Fehlt die feste Modulzeile, bleibt der
bisherige Zero-row-Pfad ohne erfundene Defaultzeile oder Audit-Entity erhalten.

Zeitpunkt und vorhandene vertrauenswürdige UDFs liefern Akteur/Aktionsgrund.
Laravel behält `web:<id>` und `terminals.php`, Native seinen bisherigen
serverseitigen UDF-Kontext. Dies ist eine Aktionsbezeichnung, keine neu verlangte
fachliche Benutzerbegründung. Owner `user_id=0` bleibt nur in der bestehenden
Adminsicht sichtbar, nicht im Eigen-/Direktteamscope. Vorhandene History- und
ausgewählte Exportdarstellung erkennen genau die zwei festen Feldpaare;
Escaping, Begrenzungen und Sichtrechte sind unverändert.

Keine Geräte-, Pairing-, Generation-, Kontakt-, Grant-, Personalcode-, Karten-,
PIN-/Hash-, Passwort-, Token- oder Schlüsselwerte werden für dieses Journal
gelesen oder erzeugt. Gerätekopplung, Sperren und persönliche Terminalzugänge
werden durch diesen Baustein weder geändert noch als bereits auditiert behauptet.

## Die vorhandenen Grenzen bleiben allein maßgeblich

Native und Laravel rufen denselben internen festen Core auf ihrer bereits
autorisierten Transaktion auf. Der Core öffnet keine Verbindung, Transaktion,
Datei, Route oder UDF und akzeptiert keinen Akteur, beliebigen SQL-/Entitynamen
oder Callback. Die vorhandenen lokalen Admin-/Hash-/Generationsprüfungen,
Namensvalidierung, Hostfreigabe und Offenbuchungsprüfungen bleiben an ihren
bisherigen Stellen, ebenso IMMEDIATE/Commit-/Rollback-/Unknown-outcome-Grenzen.
Ein später Journalfehler lässt die vorhandene Eigentümertransaktion insgesamt
scheitern, nicht nur den Journalteil.

Keine Schema-/Trigger-/Versions-/Ledger-/Admissionänderung oder neue
Sicherheitskonfiguration. Das bestehende append-only Journal schützt auch diese
Zeilen gegen Anwendungsänderungen. Es ist Anwendungsjournalisierung genau dieser
beiden bestehenden Speicherwege, kein Triggernachweis für beliebiges Betreiber-
SQL oder Migrationen/Bootstrapdefaults, keine rekonstruierte Vorgeschichte und
kein vollständiger forensischer Export. Das getrennte V6/#57-Paket bleibt
unangetastet. Keine Terminal-Aktivierung oder neue Gerätefreigabe folgt daraus.

## Tatsächlicher begrenzter Nachweis

Ein grober erfundener Ablauf mit tatsächlichem V4-/V5-Writer und gemeinsamem
Native-Core unter PHP **8.5.12**, SQLite **3.46.1** und vorhandenen gesperrten
Laravel-Abhängigkeiten: vollständige feste Entities/IDs, Schema-/Ledger- und
sonstiger Zustandserhalt, Admin-only History/ausgewählter Export, No-op-/Zero-row-
Semantik sowie unveränderter Zustand nach ungültigem Namen, Duplikat,
Nichtadmin-/Hostgate-Verweigerung und spätem Journalfehler. Native-Caller-Rollback
und Idle-Verweigerung sind enthalten. Nur notwendige PHP-Syntax/Diffprüfung,
keine neue feine Testsuite oder manuelle CI-Auslösung.

Private Wegwerfdateien und absichtlich unbrauchbare Passwortmarker, keine
nutzbaren Zugangsdaten, Keys, Codes, Tokens oder Serverinstanz. Eine ausschließlich
synthetische Modulzeile war zur Vorher-/Nachherprüfung gesetzt; **kein Hostflag**
wurde aktiviert oder geändert. Eigene Testdateien sind entfernt. Keine echte
Terminal-/Credential-/HTTP-/CSRF-/Provider-/Browser-/Parallelitäts-/Commit-Crash-
oder Betriebs-/Produktionsabnahme und kein Deployment. Quellhashes,
Kandidatbaum, Inverse und unabhängiges Review gehören zum privaten Paketnachweis.
