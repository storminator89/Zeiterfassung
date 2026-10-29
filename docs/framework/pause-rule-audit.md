# #32: Pausenregeln in derselben Anwendungstransaktion protokollieren

Begrenzter Quellfortschritt auf Main
`9cbe63d931388b1c5e3891a929f2e381be929828`, Baum
`67345d57a667907c4cb0dc2766f375eae3fb509f`. **#32 bleibt offen**: dieses
Paket ergänzt weder LDAP-/Terminal-Entities noch die fehlenden Userfelder im
alten nativen V2-Journal. Keine vollständige Admin-/Betriebsabnahme.

## Gespeicherte Entities und Sichtrechte

Der bisherige vollständige Listenersatz bleibt erhalten: jede alte Pausenregel
erhält vor dem Löschen eine `DELETE`-Zeile mit vollständigem Vorherwert; jede
neue Regel danach eine `INSERT`-Zeile mit vollständigem Nachherwert. Die festen
JSON-Schlüssel sind ausschließlich `id`, `hours_threshold`, `minimum_pause`.
Kein freies Kommando-JSON, Personenbezug, Passwort, PIN, Token, Schlüssel oder
Credential-Fingerprint wird gelesen oder protokolliert. Es wird kein künstliches
`UPDATE` zwischen unterschiedlichen Regel-IDs oder eine historische Regelwirkung
auf bereits gebuchte Pausen behauptet.

Das bestehende `audit_log` speichert den Datenbankzeitpunkt und den vorhandenen
vertrauenswürdigen UDF-Akteur/-Grund. Laravel setzt den bisherigen serverseitigen
Admin-Akteur `web:<id>` und den festen Aktionsgrund `Pausenregeln ersetzen`;
Native behält seine bisherigen serverseitigen UDFs und den Aktionsgrund
`admin.php`. Dies ist eine Aktionsbezeichnung, keine neue fachliche
Pflichtbegründung des Benutzers. `user_id=0` kennzeichnet installationsweite
Konfiguration. Der unveränderte Eigen-/Direktteamscope zeigt diese Zeilen nicht;
nur die bestehende Adminsicht sieht sie. Die vorhandene History-/Exportwhitelist
erkennt genau die drei Regelwerte; Größenbegrenzung, Escaping, Filter und die
ausgewählte, unvollständige Exportseite bleiben unverändert.

## Ein Writer, keine neue Schema- oder Sicherheitskonfiguration

Native `admin.php` und die vorhandene feste Laravel-Writeroperation verwenden
denselben internen Listenersatz. Dieser verlangt die bereits aktive
Eigentümertransaktion, validiert das kanonische vollständige Regelarray und
beginnt/committet selbst nichts. Die ursprüngliche Formularvalidierung bleibt
vor BEGIN, der bestehende Laravel-Admin-/Quellen-/Versions-/Generationscheck
unter demselben IMMEDIATE-Lock. Fehler beim Regel- oder Journalinsert brechen
die vorhandene Transaktion ab; kein schreibfreier Erfolg bei fehlendem Journal.

Keine neue Tabelle, Migration, Triggeränderung, Versions-/Ledger-/Signatur-
Ausnahme, Runtimezulassung, Verbindung, Route, Berechtigung, UI oder
Credentialquelle. Bestehende append-only Journaltrigger gelten auch für diese
neuen Zeilen. Es ist ausdrücklich **Anwendungsjournalisierung dieser beiden
Pausenregel-Speicherwege**, kein neuer Datenbanktrigger für beliebiges SQL.
Direkte Betreiberänderungen, bestehende Bootstrap-/Defaultdaten und frühere
Änderungen werden nicht rückwirkend protokolliert. Betreiber bleiben vertraut.
Das getrennte gehaltene V6-Paket wird hier weder integriert noch aktiviert.

## Tatsächlicher begrenzter Nachweis

PHP **8.5.12**, SQLite **3.46.1**, vorhandene gesperrte Laravel-Abhängigkeiten:
ein grober erfundener Komponentenablauf mit dem tatsächlichen Writer und dem
gemeinsamen Core auf privaten kurzlebigen V4-/V5-Dateien. Vollständige alte/neue
Regelwerte, Akteur/Grund/Zeit, unverändertes Schema/Ledger, Admin-only History,
ausgewählter JSON-Export, unveränderte Daten nach ungültiger bzw. Nichtadmin-
Anfrage und nach spätem Journalfehler sowie Native-Caller-Rollback/Idle-
Verweigerung bestanden. Sämtliche eigenen Testdateien sind gelöscht. Die
synthetischen Kontenzeilen enthalten ausschließlich einen absichtlich
unbrauchbaren Passwortmarker, keine erzeugten nutzbaren Zugangsdaten.

Nur dieser grobe Ablauf und notwendige PHP-Syntax-/Diffprüfung, keine neue
Testsuite, Serverinstanz, Login-/HTTP-/CSRF-/Provider-/Browser-/Parallelitäts-
oder Commit-/Crashabnahme, CI-Auslösung oder Veröffentlichung. Keine echten
Bestandsdaten, Konten, Schlüssel, Host-/Sicherheitseinstellungen oder
Produktionsaktivierung. Konkrete Quellhashes, Kandidatbaum und private
Komponentenbelege werden dem Veröffentlichungskoordinator übergeben.
