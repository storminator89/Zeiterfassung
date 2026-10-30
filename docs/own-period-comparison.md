# #40 – eigener vorläufiger Zeitraumvergleich

Das vollständige Vertragszeitkonto aus #40 bleibt offen. Die bestehende
Anwesenheitsseite bietet nun zusätzlich einen ausdrücklich gewählten eigenen
Zeitraumvergleich. Navigation, Quodara-Chrono-Karten, Tabellen und Buchungsabläufe
bleiben erhalten; kein neues Frontend, keine Route und kein Writer.

## Tatsächliche Bedeutung

- `compare_from` und `compare_through` sind inklusive Berliner Kalendertage.
  Beide sind erforderlich, streng `YYYY-MM-DD`, höchstens 366 Tage und vollständig
  vor dem Berliner Datum des Lesestands. Ohne explizite Auswahl nur Formular,
  keine zusätzliche Bestandsabfrage. Die vorausgefüllte Auswahl umfasst die
  letzten sieben abgeschlossenen Tage und ist keine Beschäftigungsannahme.
- Ausschließlich die frisch authentifizierte eigene Person; auch für Admins.
  Ein fremdes `user_id` aus der URL wählt keinen anderen Bestand. Native liest
  Konto/Fingerprint/Rolle und Daten im selben eigenen Snapshot; Laravel verwendet
  die bestehende geschützte Route und die aktuelle Identität im Readadapter.
- Bestehende Netto-Istminuten und gespeicherte Gutschriftprojektionen werden nach
  dem **Berliner Starttag** summiert. Nachtschichten werden nicht aufgeteilt;
  tatsächliche Pause und Rundung bleiben `report_record_calculation`.
- Das bestehende Tagessoll wird **einmal je ausgewähltem Kalendertag** angesetzt,
  auch ohne Buchung. `valid_from DESC,id DESC`, hinterlegte Feiertage und der
  bestehende Tagesstunden-Fallback bleiben erhalten. Die Berechnung verwendet
  `planned_minutes_value`; unvertretbare gespeicherte Modelle werden vorher
  verweigert. Abwesenheitsgutschriften werden nicht nachträglich auf aktuelle
  Modelle umgerechnet. Die Tagestabelle zeigt auch Tage mit null Buchungszeilen.
- Rechnerische Differenz = Nettoarbeit + gespeicherte Gutschrift − ausgewähltes
  Tagessoll. Eine Nachbuchung verändert nur Ist/Gutschrift, nicht nochmals Soll.
  **Kein** bisheriger undatierter Übertrag wird eingerechnet.
- Nicht sicher zuordenbare eigene Startzeiten verhindern die Summe, da ihre
  Zugehörigkeit nicht sicher ausgeschlossen werden kann. Ausgewählte offene,
  ungültige oder künftig endende Zeilen, bestehende erkennbare Arbeits-/Abwesenheits-
  bzw. Arbeitsüberschneidungen oder unvertretbare Sollregeln verhindern ebenso
  eine scheinbar vollständige Zahl. Vorhandene Diagnose-/Rechenhelfer werden
  wiederverwendet; keine Reparatur oder neue Konfliktregel.

## Bewusste Grenzen

Ein expliziter Lesebereich ersetzt **keine** fachliche Entscheidung zu Eintritt,
Austritt, Teilperioden, wertgestellter Anfangsbuchung, Abschluss oder Payroll.
Die Anzeige ist deshalb vorläufig und nicht als Vertragsforderung oder
abschließender Kontostand bezeichnet. Nur der gespeicherte Feiertagsbestand
wirkt; dessen Vollständigkeit/Region wird nicht behauptet. Offene Anträge und
spätere Korrekturen können aktuelle Projektionen verändern. Keine vollständige
Datenqualitäts-, Metadaten- oder Abwesenheitskonfliktabnahme; insbesondere keine
Übernahme des gehaltenen #352-Pakets. #40 wird damit nicht geschlossen.

## Grober Nachweis

Auf reinem gemergtem Main `4c2038128d6057f8d51f4f78a6d5a34a62e22698`
(Baum `8f16fbce2edb71801e927f54fdfa1e6ec0247efc`) plus ausschließlich diesem
Paket, PHP 8.5.12 / SQLite 3.46.1 / Laravel 13.38.0:

- Eine synthetische Memory-Journey prüft fehlenden Solltag, mehrere Buchungen
  ohne doppeltes Soll, gleichen Modellstichtag mit höherer ID, gespeicherte
  Gutschrift trotz später anderem Soll, Feiertag, DST-Nachtschicht mit Pause,
  Nachbuchung und unveränderten undatierten Übertrag. Native-/Laravel-Readparität
  und DE/EN/ZH-Native-/Blade-Darstellung stimmen überein. Eigene problematische
  Zeilen und Modelle liefern keine Summe; fremde problematische Zeilen wirken
  nicht auf die eigene Auswahl. Gesamter SourceSnapshot einschließlich Audit
  und Sequenzen bleibt beim Lesen unverändert; veralteter Native-Fingerprint
  wird zurückgewiesen und die Lesetransaktion beendet.
- Eine echte temporäre NEW-V5-Installation mit normalem First-Admin-Setup,
  verschlüsselter Filesession/CSRF und neuem App-Boot je Kernel-Request prüft
  zusätzlich die neue bestehende Attendance-GET-Anzeige, eigene Scopes trotz
  fremder URL-IDs, alle drei Sprachen, rohe ungültige/fehlende Datumsfelder und
  unveränderten Status-/Buchungs-/Korrektur-/History-/CSV-Fluss. Keine manuell
  gesetzte Benutzeridentität in dieser HTTP-Journey, kein Listener.
- Nur Syntax, `git diff --check` und diese groben lokalen Funktionsproben;
  keine neue Repository-Testsuite und kein manuell gestarteter CI-Lauf.

Alle Konten, Schlüssel, Cookies und Dateidatenbanken waren neu erzeugte,
isolierte Testdaten und wurden danach entfernt. Kein echter Betriebsbestand,
Browser-/TLS-/Hardwarepilot, Restore, Routingwechsel oder vollständiger
#74/#79/#82-Cutover. Unveröffentlichte/abgelehnte #404/#405/#410-, Buchungsfilter-,
API-Artenfilter- und V3-CLI-Pakete sind kein Bestandteil.
