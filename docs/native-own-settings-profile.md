# #62/#79 — eigene gespeicherte Kontohilfe in nativen Settings

Basis Main `e8f642ca675d6c48fec1db55a7050104ef303ba1`, Baum
`e2ac408f593fabb0d33cb7d28a0521dba895b395`, 29.10.2026, nach PR #346.

Die bestehende native Settingsseite ergänzt nach dem unveränderten
Präferenzformular einen standardmäßig **geschlossenen** Detailsbereich. Nur wer
ihn öffnet, sieht die eigene gespeicherte Abteilung, vorgesetzte Person und das
für den aktuellen Berliner Tag gespeicherte Wochenmodell mit Gültigkeit und
sieben HH:MM-Dauern. Vorhandene Details-/Rahmen-/Tabellenklassen, keine neue Seite,
Assets, Listener, Formfelder oder automatische Speicheraktion. Passwortbereich,
Übertrag, Sprache, Design und Sicherung bleiben an ihren bisherigen Stellen.

## Wiederverwendung und Lesegrenze

Nach dem bisherigen Security-/Configbootstrap, Anmeldeguard und **allen** alten
Form-/Importzweigen verwendet der neue Read ausschließlich die bereits gebundene
native `$user_id`. Keine Auswahl aus GET/POST, keine fremde oder Teamansicht.
Die bestehende native Session-/Passwortversions-/Rollenprüfung bleibt die
Authentifizierungsgrenze; der Zusatz führt weder eine zweite Anmeldung noch eine
zusätzliche Autoritäts-/Quellenpolicy ein. Eine zwischenzeitlich nicht mehr
vorhandene eigene Zeile führt nur zur nicht verfügbaren Profilansicht.

Der bereits mit #346 veröffentlichte `own_settings_profile_snapshot` liest
Zuordnungen und aktuelle gespeicherte Modellversion in einem eigenen PDO-
Lesesnapshot. Eine bereits offene Callertransaktion wird nicht übernommen oder
beendet; Fehler verwerfen nur den neuen Profilbereich. Es gibt keine SQLmutation,
Schema-/FK-/Audit-/Runtimeänderung oder neue Verbindung. Dieser eigene Snapshot
macht die vorherigen Settings-/Auth-/Chrome-Reads und eventuelle Formularwrites
**nicht** zur atomaren Gesamtseite.

Die unveränderte definitions-only Klasse
`framework/app/View/ExistingUi/OwnSettingsProfile.php` samt DE/EN/ZH-Labeldateien
wird mit festem Serverpfad direkt eingebunden: kein Composer oder Laravel-
Bootstrap. Shared Helper, DTO, Labels, Referenzklassifikation und strikte
Modellminutenprojektion bleiben bytegleich; keine parallelen Fachregeln.
Die native PHPansicht escaped alle dynamischen Texte und Dauern mit dem
vorhandenen `h()` und nutzt ausschließlich die minimale DTO. Keine IDs, E-Mails,
Auth-/Credentialfelder oder Modellgründe werden neu angezeigt.

Unzugeordnet, nicht auflösbar, fehlendes gespeichertes Modell und technische/
malformed Nichtverfügbarkeit bleiben verschieden. Kein erfundenes Nullsoll,
Legacy-Fallback, Feiertags-/Gutschrift-/Saldoalgorithmus oder Zusage aktueller
Freigaberechte. Nur der vorhandene `work_models.php`-Link führt weiter; es wird
kein neuer Freigabeweg und keine Selbstfreigabe geschaffen.

## Grober Beleg und ausdrückliche Grenzen

PHP 8.5.12 / SQLite 3.46.1: ein grober anonymer query-only Memoryjourney mit
ursprünglichen Migratoren und Modell-/Audittriggern, tatsächlich extrahiertem
neuen Settings-Block, unverändertem Shared Helper/DTO/Labels und nativer Partial.
DE/EN/ZH, eigene User-/Admin-/LDAP-Supervisorbereiche, exakte große Nachbar-
referenzen und PHP_INT_MAX als eigene ID, Modellgleichstand/Zukunft/initiale
Integral-Floats, Escaping und Dauern, frische NULL-/fehlende/ungültige Zuordnungen,
malformed Modell als nicht verfügbar, Callertransaktion und fehlender eigener
Actor. Ein reiner DTOzweig prüft fehlendes Modell, ohne unveränderliche historische
Modelle in der Fixture zu löschen. Schema/Zeilen/Audit/Änderungszähler bleiben
während Reads gleich; eigene Transaktion geschlossen, Callertransaktion erhalten.

Im Harness ist die Identität bewusst **vorgegeben**. Keine echte native
Security-/Config-/Auth-/CSRF-/Session-/HTTP-/Browser-/Layout-/Konkurrenz-/
Performance-/Betriebsabnahme und keine Aussage über wirkliche Browseröffnung des
Detailsbereichs. Alte Passwort-/Übertrags-/Präferenzhandler werden nicht im
Harness ausgeführt; vollständige mechanische Inverse bestätigt ihre unveränderten
Bytes. Ein anfänglicher Fixtureversuch, unveränderliche Modelle zu löschen,
wurde vom echten Append-only-Trigger abgelehnt; der Test wurde auf den reinen
DTOfehlzweig umgestellt, ohne Quellcode oder Trigger zu schwächen.

Keine echten Konten, Zugangsdaten, Schlüssel, Dateidatenbank oder Server; kein
Deployment, neuer Dienst, neuer Testbestand oder manueller CIstart. Die echte
Browserabnahme bleibt beim Nutzer. **#62 und #79 bleiben offen/teilweise:** keine
vollständige Einrichtung, Region-/Regelentscheidung, Einladung/Recovery oder
Geräte-/Sessionverwaltung und kein Cutover. Held PR #197/#239 sowie verweigerte
#32/#34/#57-Arbeit werden nicht transportiert.
