# Abwesenheitsgutschriften vor Modellwechsel prüfen — Teilumfang #61

Lokaler Kandidat auf tatsächlichem Remote-Main
`c9483797ff8470998fd018042d2e988e3b1f015d`, Baum
`9acde7d4819aee8428e7aa286780964f522537f9`. Additiv reankert auf Main
`c7abc17837f7f86b28ce3e2b3245633d21aa06ce`, Baum
`f26b6bab589b727bb3ef8f2fe1cb1fef74316ae7`; beide Funktionsdateien unverändert.
Eigenständiger Slice, nicht von
PR #239 oder anderen offenen Paketen abhängig. Kein Release/Deployment.

## Bestehende Bedienlücke, keine neue Saldenregel

Die native Seite erklärt bereits, dass neue Modelle Sollauswertungen, nicht
gespeicherte Abwesenheitsgutschriften ändern. Die Verwaltung kann diese
Gutschriften nun vor dem Speichern anhand ihrer eigenen Formulareingaben
ansehen. Der neue Button heißt ausdrücklich „ohne Speichern“; er ist nicht
der Speicherbutton. Datum, Zielperson, sieben Wochentagswerte und Begründung
bleiben in der Maske, ein lokaler Fragmentlink führt ohne Reload zurück.
Die Historienlinks sind ausdrücklich als neue Tabs mit `noopener noreferrer`
markiert, damit der Entwurf nicht verlassen wird. Eine alte `saved=1`-Meldung
bleibt auf GET begrenzt und bestätigt keinen nachfolgenden Preview-POST.
Eine Person muss ausdrücklich gewählt werden; ungültige oder verschwundene
Ziele fallen nicht auf die erste Person der Liste zurück.

Die Tabelle zeigt höchstens 100 gespeicherte Abwesenheiten des ausgewählten
Kontos. Der Abschnitt reicht ab dem eingegebenen Gültigkeitsdatum bis vor das
nächste vorhandene, strikt spätere Modell; bei gleichem Datum würde die neu
gespeicherte Version wie bisher gewinnen. Ohne späteres Modell ist das Ende
offen. Nicht zuordenbare Zeitwerte des Kontos werden gesondert zuerst gezeigt,
weil ihre Zugehörigkeit zum Abschnitt unbekannt ist. Eine 101. Zeile markiert
die Vorschau als begrenzt; es gibt keinen Gesamtbestands- oder Summenclaim.

## Zwei Werte vergleichen, nichts neu bewerten

- Gespeicherte **Netto-Dauer** kommt aus dem unveränderten
  `report_record_calculation`, nicht aus einem geratenen Anfangs-/Endwert oder
  einer ungeprüften `planned_minutes`-Spalte.
- Das hypothetische Tagessoll benutzt exakt `planned_minutes_value` mit den
  eingegebenen sieben Wochentagswerten. Bereits gespeicherte Feiertage ergeben
  wie im vorhandenen Planungsweg null. Höchstens 100 konkrete Tagesschlüssel
  werden gelesen; kein Import oder externe Quelle.
- Differenz bedeutet ausschließlich „hypothetisches Tagessoll minus
  gespeicherte Netto-Dauer“ dieser Zeile. Unbekannte/open/ungültige Werte bleiben
  unbekannt, nicht null Minuten. Keine Kontosaldo-, Urlaubsanspruchs-, Ledger-,
  Lohnexport- oder automatisierte Korrekturregel.
- Diese Vorschau prüft keine vollständigen Arbeits-/Abwesenheitsüberschneidungen
  oder Vollständigkeit der Feiertagsquelle. Einzelne numerische Zeilen sind kein
  Datenqualitäts-/Freigabeattest. Ein Historienlink verwendet den vorhandenen
  gescopten Lesepfad; er erteilt kein Korrekturrecht an fremden Buchungen.

## Rollen, Snapshot und Schreibtüren

Native Authentifizierung und POST-CSRF bleiben unverändert vorgeschaltet.
Die Vorschau benötigt zusätzlich einen aktuellen Admin mit identischem
serverseitigem Passwortfingerprint im selben DEFERRED-Lesesnapshot. Ziel-ID
ist eine Admin-Auswahl, niemals die Identität des Actors. Fehlende/veraltete
Identität beendet den neuen Pfad mit 401 statt einer veralteten Adminansicht.
Ungültige Eingaben oder ein gelöschtes Ziel erzeugen keinen unbeschränkten
Fallback. Datenbankfehler ergeben keine vermeintlich leere/saubere Vorschau.

Der neue Leser führt nur parametrisierte SELECTs und eine lokale
definitions-only Zeitparser-UDF aus; kein INSERT/UPDATE/DELETE/DDL oder
Auditdatensatz. Sein Ergebnis wird erst nach geschlossenem Lesesnapshot
angezeigt. Die Snapshot-/Bindung gilt ausschließlich für diese Vorschau:
späteres Speichern nutzt weiterhin den separaten unveränderten Writer und
bindet sich nicht an einen global eingefrorenen Vorherstand.

Das alte unmarkierte POST bleibt zum bisherigen Speicherweg kompatibel;
explizite unbekannte neue `model_action`-Werte werden abgewiesen statt als
Speicherauftrag interpretiert. Die neue Aktion `preview` erreicht den Writer
nicht. Dessen vorhandener IMMEDIATE-/COMMIT-/Audit-/Validierungscode bleibt
erhalten. Der schon vorhandene native Bootstrap kann weiterhin seine bisherigen
Migrationen bzw. gemeinsamen Feiertagsimport ausführen; die Vorschau führt
keine neue solche Nebenwirkung ein und ist keine Behauptung global schreibfreier
nativer Seitenaufrufe.

## Grenzen und Abnahme

Nur grober synthetischer Memory-/tatsächlicher Leser-/Formularfragmentnachweis,
PHP8.5.12-Syntax, Quellerhaltung und unabhängiger Source-Review. Keine echten
Konten, DBs, Credentials oder Keys; kein nativer Bootstrap, Login-, Session-,
CSRF-, HTTP-, Browser-, Layout-, Mehrprozess-, COMMITfehler- oder Großbestandpass.
Keine Remoteaktion, neue CI, Runtime-/Host-/Schemaänderung oder Deployment.
#61 bleibt offen: kein Ledger, keine automatisch geänderten Gutschriften und
kein fachlich geschlossener Monatsstand oder vollständiges Vertragszeitkonto.
