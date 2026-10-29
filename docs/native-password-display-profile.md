# #62 — keine native Passwortform für bereits verweigerte Ledger

Quellbasis Main `6ec57d4007cf6d6129d3d2acddc5daf06ee6881f`, Tree
`1c7f6e7a23e85d38d0f16fde3909dee0afc13dce`, nach PR #359.
Nur `settings.php`, der neue Definitionshelper und diese Notiz ändern sich.

## Behobene Bedienlücke

Die native Settingsansicht bot bisher jedem laut vorhandener Kontozeile
geeigneten lokalen Konto die Passwortfelder an. Die unveränderte tatsächliche
Passwortoperation akzeptiert jedoch ausschließlich die strikten bestehenden
Ledger `[1,2]` und `[1,2,3]`; andere Ledger führen dort zu 503 und Abmeldung.
Die Oberfläche lud damit zur Eingabe von Geheimnissen für eine bereits vom
bestehenden Befehl ausgeschlossene Operation ein.

Der zusätzliche reine Leser prüft vor der Darstellung genau diese vorhandenen
beiden Listen. Ein passendes lokales Konto erhält sein bisheriges Formular
bytegleich. Bei anderem, leerem, fehlendem oder nicht lesbarem Ledger erscheint
statt der Passwortfelder ausschließlich der bereits vorhandene lokalisierte
Hinweis `SETTINGS_OWN_PASSWORD_PROFILE_UNAVAILABLE`. LDAP-/ungeeignete oder
fehlende Konten behalten unverändert ihren bisherigen Kontohinweis und lösen
keine neue Ledgerabfrage aus. Keine neue Kopie oder Änderung der Sprachdateien.

## Reine Anzeige, ausdrücklich keine Autorität oder Profilzulassung

Die bestehenden Kontoanzeigechecks werden über denselben bereits vorhandenen
`native_own_password_available`-Helper wiederverwendet. Der neue Helper führt
höchstens ein `SELECT version ... ORDER BY version LIMIT 4` auf der bereits
vorhandenen Verbindung aus. Vier Zeilen reichen, um die beiden zugelassenen
strikten Listen von jedem längeren Ledger zu unterscheiden. Keine Verbindung,
Migration, Transaktion, Credentialprüfung, vollständige Schemaaufnahme oder
Schreiboperation kommt hinzu. Eine offene Callertransaktion wird weder gelesen
noch übernommen, committed oder zurückgerollt; nur die Passwortform bleibt in
diesem Fall nicht verfügbar. Technische Fehler verbergen ebenfalls nur diese
Form, ohne Abmeldung, Erfolgsanzeige, Rawfehler oder automatischen Wiederholungsaufruf.

Ein angezeigtes Formular bestätigt weder aktuelle Autorität noch die spätere
Zulässigkeit des Befehls. Der unveränderte Writer prüft Identität, Quelle, Rolle,
Fingerprint und Ledger weiterhin unter seinen eigenen Locks. Ein zwischenzeitlich
geänderter Stand oder ein bewusst/manuell gesendeter POST wird unverändert dort
behandelt. Keine neue serverseitige POSTsperre und keine V4-/V5-Zulassung.
Der vorhandene Native-Bootstrap läuft weiterhin vorher; diese Anzeige ist kein
Schema-, Eigentums-, Sicherheits-, Migrations- oder Cutovergate.

Passwort-/Quoten-/Auth-/CSRF-/Logout-/Session-/Audit-/Übertrags-/Sprach-/Theme-
und Sicherungshandler sowie der komplette Passworthelper bleiben bytegleich.
Keine neuen Felder, Eingabegrenzen, Werte, Listener, Endpoints, Dienste oder
Konfigurationen. Nur die bisherige lokale Darstellung erhält die zutreffende
bestehende Nichtverfügbarkeitsmeldung. Die übrige Settingsseite bleibt nutzbar.

## Grober Komponentenbeleg und offene Grenzen

PHP 8.5.12 / SQLite 3.46.1: ein zusammenhängender anonymer Memory-/Anzeigenjourney
in DE/EN/ZH mit tatsächlichem neuem Helper, unverändertem Kontohelper, echten
Sprachdateien und extrahiertem tatsächlichem alten/neuen Passwortbereich.
Die Basis zeigt für V4/V5 bereits das nicht nutzbare Formular; der Kandidat
zeigt den vorhandenen Profilhinweis ohne Passwort-/CSRF-/Aktionsfelder.
V2/V3 und die bisherigen ungeeigneten Kontoansichten rendern bytegleich;
alle drei bekannten lokalen Rollen bleiben angeboten. Leerer, V1-, unbekannter,
doppelter, textueller und fehlender Ledger sind nicht verfügbar. Der vierzeilige
Leser ist keine vollständige Bestands- oder Schemaprüfung.

Die Fixtures verwenden `PRAGMA query_only=ON`; Schema, Änderungszähler und
Transaktionszustand bleiben erhalten. In einer Callertransaktion erfolgt keine
Abfrage. Passwortfelder erhalten weiterhin keine `value`-Attribute; es wird
kein Passwort, Hash, Token, Konto oder Schlüssel erzeugt oder geprüft. Syntax,
Diffcheck und vollständige Quelleninverse belegen separat, dass alle bisherigen
Handler- und Formularbytes außerhalb der drei Anzeigezusätze erhalten sind.
Keine neue Repositorytestsuite oder manuell gestartete CI.

Keine tatsächliche native App-/Bootstrap-/Login-/Session-/CSRF-/Passwortwechsel-,
HTTP-/Browser-/Layout-/Konkurrenz-/V5-/Betriebsabnahme. Browserabnahme bleibt beim
Nutzer. Keine echten Credentials, Konten, Datenbankdatei, Daten oder Änderungen
an Runtime, Host oder Sicherheitseinstellungen; kein Deployment. **#62 bleibt
für weitergehende Einrichtung und Kontoverwaltung teilweise/offen.**
