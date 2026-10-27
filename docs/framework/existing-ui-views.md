# FW-08 / #79 – erste inaktive Bestandsansichten

**Begrenztes Präsentationspaket, keine fertige UI-Migration oder Aktivierung.**
Die vorhandenen `login.php`, `header.php` und `attendance.php` aus Main
`114dc7e3d8989c47f871239176b324cfca13bbf7` sind die Markupquelle, nicht
ein neues UI-Starterkit. Die bereits wiederhergestellte Oberfläche bleibt
unverändert in ihren PHP-Dateien bestehen. Keine dieser Dateien wird von Blade
includiert, keine Bootstrap-/Session-/Querylogik wird im Framework nachgebaut.

## Übernommen, nicht neu gestaltet

- `chrono.login`: vorhandene Login-Karte, Kolibri, Hintergrund, Typografie,
  Feld-/Buttonklassen, Fehler-/Erfolgsbereiche und optionaler Setup-Link.
- `chrono.layouts.app`: vorhandene 250px-Seitenleiste, rollenabhängige Navigation,
  feste Topbar, Status-/Buchungskontrollen, Theme-Schalter, About-Dialog und
  responsive Sidebarlogik. Markup und Inline-CSS stammen aus `header.php`.
- `chrono.attendance`: vorhandenes zweispaltiges Home-/Anwesenheitslayout,
  Buchungslabels, tatsächliche Pausen, Korrekturlink, Abwesenheitsfelder und
  Statistik-Karte. Keine neue Dashboardseite.
- Dieselben relativen `assets/…`-Pfade und vorhandenen DaisyUI-/Font-/CSS-URLs;
  `main.css`, `app_ui.css` und Kolibri werden nicht geändert oder neu gebaut.
  Login enthält weiter den vorhandenen Tailwind-CDN-Compiler. Das Paket ist
  **keine** Selbsthosting-/CSP-/Sicherheitsänderung dieser Abhängigkeiten.
- Aus `languages/de.php` und `languages/en.php` sind nur die tatsächlich
  verwendeten Konstantentexte unverändert in reine Laravel-Sprachdaten kopiert.
  Bereits im Bestand deutsch festgeschriebene Labels bleiben deutsch; vollständige
  Übersetzung wird nicht behauptet. Blade escaped sämtliche dynamischen Texte.

Die einzige absichtliche sichtbare Abweichung ist eine deutlich getrennte
Inaktivitätsnotiz und gesperrte Schreibkontrollen. Das ist kein endgültiges
Produktdesign. Login, Abwesenheit und Logout enthalten Laravel-`@csrf`, sind aber
noch nicht bedienbar; CSRF wird weder deaktiviert noch umgangen. Die bisherigen
`main.js`-/`attendance.js`-/`security.js`-Skripte werden hier **nicht** geladen:
deren Requests dürften sonst auf noch nicht freigegebene bzw. weiterhin
Legacy-verwaltete Endpunkte zeigen. Die Arbeitszeittabelle zeigt deshalb einen
ehrlichen Inaktivitätstext statt einer endlosen Ladeanzeige oder erfundener Daten.

## Explizite Präsentationsverträge

`App\View\ExistingUi\Copy`, `LoginPage` und `AttendancePage` sind reine,
ungebundene Datenobjekte. Sie öffnen keine Verbindung und lesen weder Guard
noch Session, Cookie, Uhr, Dateien mit Zugangsdaten oder Fachfunktionen.
`Copy` lädt ausschließlich die fest auf `de`/`en` begrenzten neuen Sprachdaten.

`AttendancePage::$status` im Konstruktor nimmt die bestehende Form von
`ChronoReads::attendanceStatus()` entgegen (`success,records,state,record_id`);
für diese erste Ansicht wird nur `state` angezeigt. Nutzername, ID und Rolle
muss der spätere Controller aus der **bereits aktualisierten authentifizierten**
Identität beziehen. Eine Rolle im Viewmodel ist nur Navigation, keine Freigabe.
Statistikwerte und Feiertage sind bereits formatierte Anzeigeprojektionen aus
einem später reviewten gemeinsamen Reportmodell. Ohne diese Werte erscheint
`—`, niemals eine erfundene Null oder eine zweite Überstunden-/Sollberechnung.
Die Vorschau setzt ausdrücklich synthetische Werte, ohne Fachabnahme zu behaupten.

Die aktuellen Dienste bleiben unverändert:

- `ChronoReads`: ausschließlich eigener authentifizierter Lesepfad.
- Separat reviewtes #78-Paket: `LocalWebAttendance::transition(string $eventId,
  string $action, bool $confirm = false, ?int $expectedRecord = null): array`,
  `LegacyLocalWebAttendance`, lazy scoped Binding im bestehenden App-Provider.
  Dieses UI-Paket ruft es nicht auf und registriert keinen Writer.
- Keine Controller, Provider, FormRequests, Middleware oder Aktivierungsflags
  werden hinzugefügt. Keine der Ansichten ist über eine Frameworkroute erreichbar.

## URL-Zuständigkeit nach diesem Paket

| Einstieg / Link | Tatsächlicher Eigentümer |
|---|---|
| `/`, `index.php`, `attendance.php` einschließlich Status/POST | weiterhin Legacy; die Blade-Anwesenheit ist ungeroutet |
| `login.php`, `logout.php`, `register.php` | weiterhin Legacy; neue Login-/Logout-Formulare sind gesperrt, Setup-Link nur explizite Anzeigeentscheidung |
| `save.php`, `get_time_records.php`, `get_latest_record.php`, `export_csv.php` | weiterhin Legacy; kein AJAX-/Form-/Exportadapter aktiviert |
| `dashboard.php`, `adjustments.php`, `history.php`, `work_models.php` | weiterhin vorhandene Legacy-Seiten; keine migrierten Attrappen |
| `settings.php`, `admin.php`, `supervisor.php`, `terminals.php` | weiterhin vorhandene rollenabhängige Legacy-Seiten |
| Framework `/up` | unveränderte einzige registrierte Frameworkroute |

Links behalten ihre realen bisherigen relativen PHP-Ziele, keine `#`-Platzhalter,
neuen Pfade oder vorgetäuschten Laravel-Funktionen. Die statische Vorschau
enthält diese PHP-Seiten bewusst nicht; ihre Links sind dort **nicht** als
abgenommene Navigation zu verstehen und dürfen nicht gegen eine echte App
umgebogen werden. Die Assetveröffentlichung am späteren Webroot ist ein eigenes
Cutover-Gate; auch ein bloßer Wechsel auf `framework/public/` ist nicht autorisiert.

## Grobe Prüfung und ausdrückliche Grenzen

```sh
cd framework
php tools/render-existing-ui-preview.php
php artisan route:list --json
```

Der parameterlose Renderbefehl verlangt ein inaktives Framework ohne
`chrono`-Konfiguration/offene DB-Verbindungen. Er kompiliert/rendert vier
synthetische ganze Seiten (DE/EN-Login, Admin-/Mitarbeiteranwesenheit) und kopiert
nur drei vorhandene Assets in ein zufälliges privates temporäres Verzeichnis.
Eine Arraysession und ein ausschließlich prozesslokaler Wegwerfschlüssel
ermöglichen Laravel-CSRF-Ausgabe; keine nativen Sessionglobals oder realen
Zugangsdaten. Kein HTTPserver, kein Port 18083, keine DB, kein CI und keine
Mikro-/Snapshot-/Pixeltests.

Am 27.10.2026 unter PHP 8.5.12 / Laravel 13.38.0 bestanden: Syntax,
Blade-Kompilierung/Rendering, null DB-Verbindungen, unveränderte native
Sessionglobals, nur `/up`, Composer-/Plattformprüfung und `git diff --check`.

**Grober visueller Ganzseitenvergleich bestanden:** Nach anfänglich blockierten
Browserwegen erfolgte die Prüfung über den unterstützten nativen Cloud-Browser
und einen separaten, ausschließlich Loopback-gebundenen read-only HTTP-Preview
auf Port 18089. Dieser lieferte nur sechs explizit erlaubte synthetische
HTML-Dateien und die drei kopierten Assets; sonstige Ziele waren 410, Schreiben
405, Browserverbindungen/Formaktionen gesperrt. Kein PHP oder Laravel wurde
dort ausgeführt. Die bestehende App auf 18083 blieb unangetastet.

Login und Admin-Anwesenheit wurden jeweils als ganze Seite bei 1180×757/DPR1
gegen rein statisch aus derselben Quelle extrahierte synthetische Vorher-HTML-
Referenzen angesehen. Hintergrund, Kolibri, Typografie, Sidebar, Header,
Karten-/Statistikspalten, Icons und Formularaufbau sind grob visuell erhalten;
keine groben Styles-/Assetausfälle oder App-Konsolenfehler. Sichtbare Unterschiede
sind die erklärte Inaktivitätsnotiz, deaktivierte/graue Schreibkontrollen und der
noch nicht verbundene Tabellenplatzhalter. Die vier Vorher-/Nachher-Screenshots,
DOM-Ausgaben und SHA256-Werte liegen im lokalen Prüfarbeitsverzeichnis, nicht
als neue Snapshot-Tests im Repository. Kein öffentliches Vorschauhosting,
Pixelgleichheitsnachweis, aktiver Login-/Buchungstest oder vollständige
Mobile-/Dark-Mode-/Sprach-/Rollenjourney-Abnahme wird damit behauptet.

Vor Aktivierung müssen unabhängig gemeinsam reviewt und grob abgenommen werden:
aktuelle `RefreshLocalAccount` → `auth:web` → `auth.session`-Identität, tatsächlicher
Login-/LDAP-/Setupvertrag, Laravel-/Legacy-Form-CSRF-Verträglichkeit, ausdrücklich
zugelassene auditfähige DB und operation-lifetime Writer, exakter Pending-Event-
Retryvertrag (Konto **und** Token, Doppelklick, unbestätigte Antwort, Reload/Zurück),
Status-/HTML-Fragment-/Abwesenheitsadapter sowie eine autoritative Statistik-
projektion. Danach ganze Seiten visuell und den groben kritischen Rollenjourney
prüfen. Dashboard/Korrektur/History/Modelle/Admin/Team/Settings und vollständige
#79-Abnahme bleiben offen. Kein produktiver Cutover, Hosting oder Deploy.
