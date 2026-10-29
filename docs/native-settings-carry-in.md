# #61 — Native eigenen Admin-Übertrag ohne stilles floatval-Raten speichern

Quellbasis: Main `2ea273e6eddd294ff210a25b7bf65d535337aee5`, Baum
`35f86aaf85c32cc149cae6f2b017ff2ca7aad109`.

Der Native-Settingshandler verwendete bisher `floatval` direkt auf dem rohen
Formfeld. Dadurch konnten ungültige Texte still zu null/Präfixzahlen werden;
zusätzlich konnte die implizite Float-zu-String-Bindung gültige Dezimalstellen
verlieren. Diese getrennte Korrektur verwendet nun denselben bereits gemergten
`settings_carry_in_value`-Parser wie Laravel, ohne dessen Funktionskörper zu ändern.
Keine neue Rundungs-, Saldo-, Betrag-, Payroll- oder Kontofachregel.

Nur das bestehende eigene Feld für die bestehende Adminrolle wird verarbeitet.
Ziel-ID/Rolle/Akteur können weiterhin nicht aus dem Formular gewählt werden.
Nichtadmin-Felder und ausgelassene Werte behalten ihre native Ignoriersemantik;
`isset` bleibt gleich. Ungültige Strings/Arraywerte, nichtendliche, unterlaufende
oder zusätzlich repräsentationsverlierende Eingaben werden als400 vor UPDATE
abgewiesen. Keine Teilzahl, leise Null, Trimmung oder automatische Reparatur.
Der genaue Vertrag und seine REAL-/Auditpräzisionsgrenzen stehen in der
[gemeinsamen Dokumentation](framework/settings-carry-in.md).

Bei gültigem Wert wird dessen geprüfter **roher String** im bestehenden festen
UPDATE/execute-Array gebunden; keine zusätzliche Float-Serialisierung. Verbindung,
Autocommit, vorhandener Audittrigger und last-write-wins bleiben unverändert:
keine neue Transaktion, Lock-/Version-/OLD-Wert-409- oder Ledgerbehauptung.
Technische SQLfehler bleiben wie bisher ungefangen und versprechen weder sicheren
Rollback noch Speicherung. Kein Retry oder Compensation-Write.

Das vorhandene Eingabefeld verwendet `step=any` ohne künstliche Nulluntergrenze,
wie derselbe bisherige signierte Serververtrag in Laravel. Endliche gespeicherte
Floats werden mit dem vorhandenen Roundtripformatter und HTML-Escaping angezeigt;
ein erneutes Save darf nicht allein durch PHP-Anzeigepräzision Stellen verlieren.
Altwerte werden weder bereinigt noch auf neue Regeln umgeschrieben.

Die bestehende Sprache wird weiterhin vorher gewählt. Bei Validierungsfehler
bleibt der bisherige DBwert erhalten, späteres serverseitiges Theme-Save und das
Erfolgsmodal entfallen. Alte Importmeldungen dürfen den aktuellen Fehler nicht
zu Erfolg umdeuten; deren bisheriger Konsum bleibt erhalten. Session und SQLite
sind keine gemeinsame Transaktion; eine zuvor gewählte Sprache oder bestehende
Browser-Themevorschau wird nicht als zurückgerollt behauptet. Passwortformular,
CSRF-/Auth-/Quellen-/Revokationsgrenze und alle übrigen Settings bleiben unverändert.

Kein neuer Account, Credential, Schlüssel, Runtime-/Host-/Dienst-/Schemawechsel,
Betriebsdatenzugriff oder tatsächliches Speichern eines Saldo außerhalb der
anonymen Komponentenfixture. Keine vollständige #61-Ledger-/Wertstellungs- oder
Schlussstandsfreigabe; #61 bleibt offen.

Anders als der bestehende Laravelwriter bekommt die Native-Autocommitoperation
keinen neuen transaktionalen SQLite-Rücklese-/Rollbackcheck. Der angezeigte Wert
nach dem Execute ist der geprüfte Eingabewert, kein zusätzlicher Commit- oder
verlustfreier Dezimalbeleg. Allgemeine SQLite-/REAL-/Auditformatpräzision bleibt
begrenzt; dieses Paket verhindert die zusätzliche vorherige PHP-Formatkürzung,
nicht jede mögliche Abweichung einer beliebigen SQLite-Version/-Konfiguration.

Grober Komponentenbeleg: PHP8.5.12 (precision14) / SQLite3.46.1, tatsächlicher
extrahierter Normal-POST-Abschnitt und Inputtext, ursprüngliche Migratoren/
Audittrigger auf anonymem Memory. Baseline speichert ungültigen Text still als0
und kürzt einen gültigen17stelligen Wert bei impliziter Bindung. Kandidat weist
ungültig/Überlauf/Unterlauf/Repräsentationsverlust/Array vor SQL auch unter
query_only ab; alte Daten und Audit bleiben dabei unverändert. Gültige negative,
Null- und exakt darstellbare große Werte behalten das eigene Ziel, einen
Auditeintrag und den bisherigen Präferenzablauf. Raw-Bindung/Anzeige/Wiedersave
bleiben für die geprüften Werte gleich. Nichtadmin-, GET- und Passwortformular-
Klassifikationszweige bleiben im isolierten Abschnitt gleich; Sprachwahl ist
als der unveränderte frühere Schritt vorgegeben. Keine tatsächliche Native-
Bootstrap-/Auth-/CSRF-/Passwort-/HTTP-/Browser-/Konkurrenz-/Commitverlust- oder
Produktivabnahme. Kein neuer Repositorytest oder manueller CI-Start.
