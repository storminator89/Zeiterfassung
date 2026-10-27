# FW-08 / #79 – bestehende Korrekturseite mit Laravel

**Begrenzte nächste lokale UI-Welle, kein produktiver Cutover und kein
vollständiges #79.** Aufbauend auf der [lokalen Webintegration](local-web-ui-integration.md)
und dem [gemeinsamen Korrekturdienst](local-web-corrections-service.md) wird
die vorhandene `adjustments.php`-Ansicht weiterverwendet, nicht ersetzt.

## Vorhandene Formulare und Antragskarten

`chrono.corrections` übernimmt das bestehende Markup, H1/Erklärung, Feldnamen,
CSS-Klassen, Offset-Platzhalter, Stornocheckbox, Fehler-/Gespeichert-Banner und
Antragskarten. Es wird keine neue Tabelle, Oberfläche, Zeitzonenautomatik oder
SPA eingeführt. Der gemeinsame vorhandene Header mit Theme/Navigation bleibt;
sein aktiver Menüpunkt ist nun explizit `adjustments.php` statt stets Home.
Alle neuen dynamischen Ausgaben sind escaped Blade. Die vorhandenen deutschen
Seitenlabels bleiben wie im Bestand deutsch. Keine Kalender-/Stundenneuberechnung.

Der dünne Controller verwendet ausschließlich bereits freigegebene Dienste:

- `ChronoReads::reportRows()` liefert eigene Einträge in bestehender Reihenfolge.
  Für den identischen 200-Optionen-Anzeigecap werden die ersten 200 Zeilen
  genommen und nur `id/startzeit/endzeit` dargestellt. Keine neue Query, kein
  fremder Benutzerselector. Der bestehende Reader lädt weiterhin alle eigenen
  Zeilen; keine neue Performanceabnahme für große Bestände wird behauptet.
- `ChronoReads::corrections()` liefert die vorhandene autorisierte
  `id DESC LIMIT 100`-Liste. Das Viewmodel enthält nur sichtbare Felder;
  `original_json` bleibt im Kern/Bestand und wird nicht ins HTML eingebettet.
- Headeridentität kommt vom nach Middleware aktualisierten `web`-Guard,
  Anwesenheitsstatus vom bestehenden Reader. Zeitpunkte werden nur mit dem
  vorhandenen reinen `attendance_local`-Formatter angezeigt. Explizit geladen
  werden dessen Funktionsdefinitionen, niemals `config.php`, `header.php`,
  `functions.php` oder native Session-/DB-Bootstraps.
- `LocalWebCorrections::apply(array): void` ist der einzige POST-Schreibweg.
  Controller/Blade enthalten keine SQL-, Vieraugen-, Team-, Snapshot-,
  Finalitäts-, Transaktions- oder Auditregeln. Frischer zugelassener Writer,
  aktueller Guard und derselbe private Bestand kommen aus der bestehenden
  expliziten Composition, nicht aus Formfeldern.

## Exakter lokaler Formtransport

`GET adjustments.php` geht ausschließlich an `index` und liest/rendert.
`POST adjustments.php` geht ausschließlich an `store` und darf `apply` aufrufen.
Es gibt keinen Raw-POST-Dispatch in einem GET-fähigen Handler: ein effektives
GET durch Laravels Method-Override erreicht deshalb keine Mutation unter
übersprungener CSRF-Prüfung.

Die POST-Methode reicht nur den Raw-Form-Bag für
`action,record_id,cancel,start,end,pause,reason,id,decision,review_reason`
unverändert weiter. Keine Query-/JSONmischung, Actor-/Role-/Auditfelder,
Carbon-/Boolean-/422-Neunormalisierung. Die vorhandene eng begrenzte Ausnahme
von globalem Trim/Leerstringkonvertieren schließt zusätzlich ausschließlich
`adjustments.php` ein; `cancel === '1'` und die vollständigen nativen
Transformationen verbleiben im gemeinsamen Kern.

Beide Routen benutzen den bestehenden Stack: Session/`LegacyFormCsrf` →
`RefreshLocalAccount` → `auth:web` → `auth.session`. CSRF wird nicht deaktiviert;
die Formulare verwenden `@csrf`. Erfolg ist der native Redirect
`adjustments.php?saved=1`; `saved` ist wie bisher Parameterpräsenz. Fachfehler
werden wie im nativen Handler im vorhandenen HTML angezeigt, auch wenn der
Kern etwa eine 403/409-Exception liefert; der alte HTML-Handler lieferte dann
keine neue JSON-/422-Antwort. Technischer Formularfehler bleibt generisch
`Antrag konnte nicht verarbeitet werden.`. Middleware-Auth/CSRF-Verweigerung
bleibt davon getrennt. Form-Action, GET-Vorauswahl und die bisher nicht
automatisch wiederbefüllten Fehlerfelder bleiben wie im Bestand.

Reviewformularsichtbarkeit bleibt lediglich Darstellung: pending + andere
Person + angezeigte Admin-/Supervisorrolle auf einer bereits gescopten Liste.
Die tatsächliche Freigabe prüft unverändert ausschließlich der gemeinsame Kern
unter Writerlock. Auch Admins dürfen sich nicht selbst freigeben. Storno und
Ablehnen sind keine Attrappen, sondern dieselben vorhandenen Befehle am Kern.
Es wird keine automatische POST-Wiederholung oder erfundene Ereignis-ID für
Korrekturanträge eingeführt. Der Stempel-Pendingvertrag ist nicht automatisch
ein Idempotenzversprechen für Korrekturformulare.

## Opt-in und begrenzte Abnahme

Der Registrar wird weiterhin nicht standardmäßig aufgerufen; normales Boot
behält ausschließlich `/up`. `Interactions::corrections` ist standardmäßig
false und nur eine vertrauenswürdige Anzeigefreigabe, keine Autorisierung.
Nur der bereits isolierte Fixture-Launcher schaltet die Seite an und ergänzt
seine feste HTTP-Whitelist um genau `/adjustments.php`. Keine Legacy-Fallbacks,
neuen Produktionsbindungen, DB-/Host-/Schlüssel-/Sicherheitsänderungen oder
Zugriffe auf die bestehende App 18083. Sämtliche Kern-/Writer-/Read-/Auth-/API-
Dateien und bestehenden Assets bleiben unverändert.

Die vorhandene synthetische Fixture besitzt schon zwei getrennte lokale
Konten: Mitglied ID 1 und Admin ID 2. Keine neue Account-/Team-SQL für diese
UI-Abnahme. Abgenommen wurde genau ein realer unterstützter CUA-Ablauf:
Mitglied beantragt Änderung einer vorhandenen Buchung → eigener Antrag Offen,
Projektion unverändert → Logout/Adminlogin → andere Person gibt begründet frei
→ Mitglied sieht Freigegeben und geänderte Buchung. Read-only Abschluss prüft
Originalsnapshot, finale Entscheidung/Reviewer, beabsichtigte Projektion und
Kernauditakteure `web:1`/`web:2`, keine zusätzlichen Stempelereignisse,
SQLiteintegrität sowie unveränderte Original-/Backuphashes.

## Tatsächliche grobe Abnahme am 27.10.2026

PHP 8.5.12 / Laravel 13.38.0: neue ganze Ansicht kompiliert/rendert synthetisch;
PHP-Syntax, Composer-/Plattformprüfung, `git diff --check` und explizite
GET-/POST-Routen-/Middlewareauflösung ohne DBzugriff bestanden. Natives
Sessionglobal unverändert, benannter Login auflösbar, normales Boot nur `/up`.

Der unterstützte native Terminal/CUA-Browser verwendete ausschließlich eine
neue private bekannte V2-Fixture auf Loopback 18090. Das Mitglied ID 1 stellte
genau einen Antrag für vorhandene Buchung 42 (23.10.2026, Beginn 08:00+02:00,
Ende 14:00+02:00, Pause 30). Die vorhandene Seite zeigte „Offen“; vor der
Entscheidung belegte ein read-only Zwischenhalt die noch unveränderte
Projektion 08:00–13:00 und den exakt erhaltenen Originalsnapshot.

Nach Logout meldete sich der andere Admin ID 2 an und gab denselben Antrag
begründet frei. Nach Adminlogout/erneuter Mitgliedsanmeldung sah das Mitglied
„Freigegeben“, den Entscheidungsgrund und die geänderte vorhandene Eintragsoption
bis 14:00+02:00. Die ganze Korrekturseite wurde grob visuell angesehen; vorhandene
Header-/Form-/Kartenstruktur bleibt, kein App-Runtimefehler beobachtet. Keine
Ablehnen-/Storno-/Supervisor-/DST-/Negativeingabesuite als zusätzliche Abnahme.

Read-only Abschluss: genau ein Antrag `approved`, requester=1/reviewer=2,
Originalsnapshot weiterhin exakt vor der Änderung; Buchung 42 nun
08:00–14:00+02:00/Pause 30. Eine neue Antragsauditzeile unter `web:1`, drei
Freigabe-/Projektionsauditzeilen unter `web:2`, jeweils mit den exakten Gründen
des gemeinsamen Kerns. Null Stempelereignisse, eine geschlossene
Korrektur-Arbeitssession, keine offene Session, SQLite `quick_check=ok`,
Original-/Backuphash unverändert. Backendinspektion war ausschließlich lesend;
sämtliche fachlichen Änderungen liefen über die tatsächlichen UI-Formulare.

Echte Screenshots/Textsichten, vorhandenes Serverlog und Vorher-/Pending-/Final-
DBberichte liegen als lokale Prüfarbeitsdaten außerhalb des Repositorys.
Keine neue Mikro-/Rollen-/DST-/Pixeltestsuite, CI, reale Zugangsdaten oder
produktive Aktivierung. Weitere Korrekturzweige sind am selben bestehenden
Dienst verdrahtet, aber nicht durch diesen einen Browserfall neu abgenommen.
Vollständige #79-/Sicherheits-/operative Cutoverfreigabe bleibt ausstehend.

Nach dem Abschluss wurde auch das Mitglied ausgeloggt, der eigene Browser-/
Terminaltab geschlossen und ausschließlich der temporäre 18090-Prozess beendet.
Der fehlende Listener wurde nativ bestätigt; die vorhandene 18083-Anwendung
und ihre Sitzungen blieben unangetastet.
