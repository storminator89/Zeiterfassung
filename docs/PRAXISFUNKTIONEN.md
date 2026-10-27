# Praxisfunktionen und Upgrade

## Zeiterfassung und Pausen

Web und Benutzer-API verwenden immer denselben Buchungsdienst mit Serverzeit. Das physische Terminal bleibt optional (`APP_TERMINAL_ENABLED=1` plus Freigabe in der Verwaltung). Ein fehlender oder zu kurzer Pauseneintrag löst beim Gehen nur einen Hinweis aus. Es wird keine Mindestpause erfunden. Einzelintervalle sind sekundengenau; die vorhandene Berichtsprojektion verwendet die abgerundete Summe ganzer Minuten.

`POST /api.php/attendance` erwartet `action` (`start`, `pause_start`, `pause_end`, `end`), eine eindeutige `event_id` (16–80 Zeichen, Buchstaben/Ziffern/Unterstrich/Bindestrich) und optional `confirm_end_pause`. Wiederholungen derselben ID mit demselben Inhalt sind idempotent. Für zuverlässige Wiederholungen auch bei den alten Alias-Endpunkten `workentry` und `setendzeit` eine eigene ID liefern. Historische API-Nachträge, direktes Bearbeiten und direktes Löschen liefern 409. Clients müssen auf Korrekturanträge umgestellt werden. `swagger.json` beschreibt den aktuellen Vertrag.

## Arbeitszeitmodelle und Abwesenheiten

Die Verwaltung hinterlegt unter `work_models.php` sieben Tages-Sollwerte in Minuten, Gültigkeitsdatum und Begründung. Mehrere Modelle bleiben erhalten; maßgeblich ist das letzte gültige Datum, bei Gleichstand die höchste Versions-ID. Nur Administratoren dürfen Modelle anlegen. Beschäftigte können das Soll nicht mehr über Einstellungen ändern. Ein neues Konto erhält zunächst sein bisheriges Tages-Soll an Montag bis Freitag; individuelle Verteilungen müssen ausdrücklich eingerichtet werden.

Urlaub und Krankheit unter `attendance.php` erhalten genau die Sollminuten des betroffenen Datums. Freie Tage und vorhandene Feiertage werden ausgelassen. Der Feiertagskalender muss für den Praxisstandort vollständig geprüft sein; die bisherige automatische Quelle ist Baden-Württemberg. Doppelte Abwesenheiten oder Überschneidungen mit Arbeitsbuchungen werden abgelehnt; ein konfliktbehafteter Zeitraum wird vollständig zurückgerollt. Eine Abwesenheit am heutigen Datum verhindert zusätzliches Einstempeln, bis sie korrigiert/storniert wurde.

Eine bereits laufende Nachtschicht wird auch bei vorgebuchter Abwesenheit am Folgetag mit ihrem tatsächlichen Ende geschlossen. Betroffene Summen bleiben bis zur manuellen Vier-Augen-Klärung als „Klärung offen“ gekennzeichnet; keine Zeiten/Gutschriften werden automatisch gelöscht, gekürzt oder als null bewertet. Führungsbericht und CSV verweigern in der ersten Phase betroffene abschließende Auswertungen. [Umfang, Wiederholungsvertrag und verbleibende Grenzen](CH29-NIGHT-ABSENCE.md).

Die Gutschrift wird als `absence_kind` und `planned_minutes` gespeichert. Für bestehende Auswertungen liegt parallel ein Zeitintervall ab Tagesbeginn mit genau dieser Dauer vor; dies ist ausdrücklich eine Gutschrift und keine Anwesenheitsmessung. Webübersicht und CSV kennzeichnen sie. Rückwirkende Modelländerungen ändern das Soll in Berichten, aber nicht stillschweigend gebuchte Gutschriften. Bereits gebuchte Gutschriften müssen über einen begründeten Korrekturantrag angepasst werden.

Diese Anwendung enthält keine Urlaubsanspruchsverwaltung, keine automatische medizinische Prüfung und keinen Lohnabrechnungsabschluss. Historische Gesamtüberstunden berücksichtigen weiterhin die vorhandenen Buchungstage plus manuelles Startguthaben; ohne Eintritts-/Austrittsdaten wird kein vollständiges Vertragszeitkonto rekonstruiert. Wochen-, Monats- und Jahressoll verwendet die hinterlegten Modelle/Feiertage.

## Korrekturen, Stornierung und Protokoll

`adjustments.php` nimmt Nachträge, Änderungen und Stornierungen mit Begründung an. Eine andere berechtigte Person (Administrator oder zugeordnete Führungskraft) muss entscheiden. Ein alleiniger Administrator braucht für eigene Anträge eine zweite berechtigte Person. Aktuell laufende neue Arbeitszeiten zuerst beenden. Offene Altbuchungen können beendet oder per Antrag bereinigt werden. Gleichzeitige Änderungen am Original machen einen Antrag ungültig; die Freigabe erfolgt transaktional.

`history.php` zeigt Beschäftigten ihre Historie, Führungskräften die ihrer zugeordneten Personen und Administratoren alle Einträge. Datenbank-Trigger erfassen Änderungen an Buchungen, Arbeits-/Pausenintervallen, Anträgen, Modellen, Feiertagen und relevanten Kontostammdaten einschließlich Überstundenguthaben. Erfasst werden Zeitpunkt, Akteur, Vorgang und Vorher-/Nachher-Werte. Passwörter, PINs und Geräteschlüssel gehören nicht in das Protokoll.

Das Journal, ursprüngliche Stempelereignisse und Modellversionen erlauben keine UPDATE-/DELETE-Operationen. Abgeschlossene Originalintervalle und abgeschlossene Freigaben bleiben geschützt. Eine freigegebene Stornierung entfernt nur die Berichtszeile; Originalwerte, Antrag, Entscheidung und Ereignisse bleiben erhalten. Schreibvorgänge ohne Audit-Kontext schlagen fehl. Die Anwendung hat keinen Weg zum Löschen der Historie; ein Betreiber mit direktem Datenbank-/Serverzugriff kann Schutz-Trigger entfernen oder alte Backups einspielen. Gegen diesen Betreiber ist das Protokoll **nicht extern manipulationssicher versiegelt**. Dafür wären separat verwaltete unveränderliche Sicherungen/Archivierung erforderlich.

## Bestehende Installation übernehmen

Vor dem Upgrade App stoppen und gesamte private Ablage einschließlich Schlüssel sichern. Schema-Migrationen laufen automatisch und transaktional. Bestehende Zeilen bleiben erhalten; der beim Upgrade sichtbare Stand wird als `BASELINE` protokolliert. Frühere Änderungen sind unbekannt und werden nicht nachträglich erfunden. Das bisherige tägliche Soll wird als Startmodell für Montag–Freitag übernommen; frühere Vertragsänderungen sind damit nicht rekonstruierbar.

Zeitangaben ohne Offset werden als Europe/Berlin interpretiert. Vor Einführung prüfen, ob die alte Installation diese Zeitzone verwendete. Eine einzelne offene Altbuchung wird beim nächsten Pause-/Gehen-Vorgang übernommen; eine eventuell dort stehende automatische Pause wird durch tatsächlich ab dann erfasste Intervalle ersetzt. Der alte Wert bleibt im Journal. Mehrere offene Altbuchungen müssen per Korrekturantrag bereinigt werden. Alte abgeschlossene Pausenwerte werden nicht ungefragt umgeschrieben.

Vollständige Offline-Wiederherstellung siehe [TERMINAL.md](TERMINAL.md). Browser-Teilimport bleibt gesperrt. Der Administrator-Download enthält sämtliche Datenbanktabellen und Trigger, aber keine externen Schlüssel. Konten mit Historie bleiben erhalten. Retention und Archivierung müssen betrieblich festgelegt werden.

## Prüfung

Destruktive Tests ausschließlich gegen eine frische `tests/seed.php`-Fixture:

1. `tests/security_http.py`: 54 Prüfungen (Berechtigungen, CSRF, JWT, XSS/CSV, Sperren und geschlossene alte Schreibwege).
2. `tests/terminal_http.py`: 57 Prüfungen (Pairing, PIN/NFC, Signaturen, Wiederholungen, echte Pausen, Parallelität und Korrekturen).
3. `tests/practice_http.py`: 46 Prüfungen für Praxisabläufe mit deaktiviertem Terminal, Sollmodelle, Abwesenheit, Historie, Stornierung und Altbuchungen.
4. `tests/import_http.py`: vier Sicherungs-/Wiederherstellungsprüfungen einschließlich aller Tabellen und Schutz-Trigger.
5. `tests/attendance_time.php` und `tests/practice_migration.php`: Zeitumstellungen, >24h, Migration bestehender Teilzeitdaten und Sollberechnung.

Die CI führt diese Prüfungen mit PHP 8.3 und vier HTTP-Workern aus. Physischer Leser, Produktions-HTTPS, Praxisnetz sowie externe Sicherungsablage sind separate Abnahmepunkte; lokale Browser-/Datenbanktests ersetzen diese nicht.

`tests/practice_browser.py` prüft zusätzlich mit Playwright die Webbuchung einschließlich Bestätigung, Abwesenheitsformular und mobile Ansichten.

## Korrekturen an Buchungslogik und Auswertungen

- Kalenderwochen werden als Montag–Sonntag einschließlich Jahreswechsel ausgewertet. Wochenkennungen enthalten das ISO-Wochenjahr; gleiche Wochennummern verschiedener Jahre werden nicht zusammengezählt.
- Abgeschlossene Arbeitszeiten und Gutschriften behalten ihre ganzen Minuten in Monats-/Jahressummen. Offene Buchungen fließen nicht als vermeintlich abgeschlossene Zeiten in Summen oder Überstunden ein. Die vorhandene Zuordnung einer vollständigen Nachtschicht zum Startdatum bleibt bestehen; es erfolgt keine nachträgliche Tagesaufteilung.
- Arbeitszeitmodelle mit null Sollstunden verursachen keine Division durch null. Wochenfortschritt und verbleibende Wochenzeit verwenden das individuelle Modell statt fünfmal eines pauschalen Tageswerts. Rundung erzeugt keine Uhrzeiten wie `01:60`.
- Mindestpausen-Hinweise richten sich nach der Nettoarbeitszeit ohne tatsächlich erfasste Pausen. Eine zurückspringende Serveruhr darf keine Ereignisse vor bereits erfassten Ereignissen/Pausen derselben Arbeitssitzung erzeugen.
- Eine Abwesenheitsgutschrift blockiert bei Überschneidungsprüfungen den ganzen lokalen Kalendertag – auch einen 23-/25-Stunden-Tag und auch bei alten Einträgen mit Beschreibung Urlaub/Krankheit/Feiertag. Arbeitsnachträge dürfen erst nach dem tatsächlichen Ende beantragt und freigegeben werden; künftige Abwesenheitsgutschriften bleiben möglich. Bei einer Gutschriftenkorrektur beginnt das technische Zeitintervall um 00:00 Ortszeit, enthält ganze Sollminuten und keine Pause. Historische widersprüchliche Daten werden nicht automatisch umgeschrieben.
- Einstempeln wird abgewiesen, wenn eine vorhandene abgeschlossene Buchung den aktuellen Zeitpunkt bereits umfasst. Direkt aneinander anschließende Arbeitsintervalle bleiben erlaubt.
- Nach bestätigten Webbuchungen aktualisieren sich Status und letzte Buchungen. Ein veralteter Tab kann nach einem Zustandskonflikt wieder die tatsächlich zulässigen Aktionen anbieten. Die Liste zeigt die zuletzt angelegten Einträge; geplante Abwesenheiten verdrängen keine danach neu gestempelte Buchung. Die Kachel „letzter Zeiteintrag“ bevorzugt offene Arbeitszeit und behandelt zukünftige Gutschriften nicht als letzte Arbeitszeit.

Zusätzliche Regressionen: `tests/booking_logic.php` (32 Prüfungen) und `tests/booking_http.py` (13 Prüfungen). Zusammen mit den bisherigen Suites sind es 221 serverseitige Prüfungen. Die Playwright-Prüfung deckt zusätzlich aktuelle Buchungszeilen und konkurrierende Browser-Tabs ab. Keine Datenmigration ist für diese Fehlerkorrekturen nötig; vorhandene Journal- und Originalereignisse bleiben unverändert.
