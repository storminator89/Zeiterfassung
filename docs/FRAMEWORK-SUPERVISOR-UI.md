# FW-06 / #79 — bestehende Vorgesetztenansicht, noch inaktiv

Basis: Main `729250b0c866fcfc899eff47b052997144cf3842`.
Dieses Paket portiert **die vorhandene `supervisor.php`-UI**, kein neues Dashboard.
Der ursprüngliche Prerequisite-Teil registrierte keinen Einstieg. Die unten
dokumentierte optionale Integration bleibt standardmäßig geschlossen.

## Enger Quellumfang

- `lib/supervisor_projection.php`: mechanisch übernommene native Berechnung,
  Gruppierung und vorbereitete Anzeige; bestehende `report_*`- und
  `planned_minutes()`-Helfer bleiben die Fachautorität.
- `ChronoReads::teamReport()` / `LegacyChronoReads::teamReport()`: ausschließlich
  additive Lesemethode; bestehende Methoden und ihr Snapshot-Lebenszyklus bleiben
  unverändert. Die separat vorbereitete `calendarWeek()`-Erweiterung ist bei
  Integration **additiv zu erhalten**, nicht durch diese Basis zu ersetzen.
- Neuer `LocalWebSupervisorController`, `SupervisorPage` als **Inhalts-DTO**, eine
  Blade-Ansicht und zwei inerte de/en-Labeldateien mit exakten Bestandswerten.
  Kein paralleles Chrome-Modell: der Controller verwendet `AttendancePage`.

`supervisor.php` bleibt **byte-identisch**, ebenso die übrigen nativen Quellen,
Routes, Standard-Chrome/Layout, `AttendancePage`, Auth/Policies/Provider,
Konfiguration und Abhängigkeiten. Die Berechnung ist eine **vorübergehende
Kopie aus dem nativen Bericht**, keine unabhängig neu entworfene Formel.
Erst ein separat geprüfter Legacy-Cutover darf die native Stelle durch den
Helfer ersetzen. Dieses Paket behauptet diese Zusammenführung noch nicht.

## Herkunft und bewahrte Eigenheiten

Aus der unveränderten `supervisor.php` dieser Basis:

1. Zeilen 38–110: Zeilenvorbereitung und vollständige Überstunden-/Modalwerte.
2. Zeilen 306–360: Gruppierung, Mitarbeitersuche vor Paging, zehn **Gruppen** je
   Seite und begrenzte Seitenwahl. Native rohe `page`-String-/Arraysemantik
   bleibt erhalten; der Controller liest dafür den originalen Querystring.
3. Zeilen 367–385 und 406–419: numerische Tabellen-/Detailformatierung wird nur
   aus dem Renderpfad in vorbereitete Anzeigewerte verschoben.
4. Vorhandene Klassen, IDs, zwei Chartdefinitionen, vorzeichenbehaftete
   **Ganzzahl-Minuten** für Chart/Kartenfilter, Kartensuche, native
   Detaildialog-/Expandierungsskripte und native Sprachkonstanten.
   Chartdaten bleiben Zahlen, nicht aus `HH:MM` zurückgerechnete Werte.

Bewusst keine neue Fachpolitik: Überstunden gelten weiter für erfasste
abgeschlossene Starttage, nicht für sämtliche Kalendertage. Vortragsstunden,
Versionsmodelle und Feiertage verwenden weiter die Bestandslogik. Offene
Einträge erhalten keine abgeschlossene Zeitgutschrift; bestehende offene
Tabelleneinträge zeigen weiter ihre native Null-Dauer. Das Regelarbeitszeit-Chart
zeigt weiterhin `users.regelarbeitszeit`, nicht ein neu berechnetes Tagesmodell.
Suche/Paging beeinflussen nur die Tabelle, nicht die vollständigen Karten- und
Modalwerte. Keine Offboarding- oder neue Rollenlogik.

### Zeitzonenkontext, keine globale Umstellung

Native `config.php` setzt `Europe/Berlin`; die Foundation bleibt `UTC`.
Die Extraktion verändert **keinen** globalen Zeitzonenwert:

- Der native Gruppen-Ausdruck `date('Y-m-d', strtotime(startzeit))` unter Berlin
  wird durch den bereits vorbereiteten kanonischen `report_day()`-Wert ersetzt.
- Native `new DateTime(raw)` erhält explizit den bisherigen Standardkontext
  `new DateTimeZone('Europe/Berlin')`. Ein im Wert gespeicherter expliziter
  Offset bleibt dabei **unverändert wirksam**, wie nativ.
- Daher können Detail-Wanduhrzeit und Berlin-Gruppendatum bei expliziten
  Fremdoffsets weiter unterschiedlich aussehen. Das ist bewahrtes Verhalten,
  **keine behauptete allgemeine Normalisierung**.

Der grobe Äquivalenznachweis vergleicht originale native Ausdrücke in einem
separaten Berlin-CLI-Prozess mit dem Helfer in einem UTC-CLI-Prozess, einschließlich
Offset über die Tagesgrenze, offsetfreier Werte und DST. Keine globale
Zeitzonenmutation im Helfer oder Testskript.

## Autorisierter Umfang und nicht abschließende Ergebnisse

Nur die bestehende Guard-Identität bestimmt den Akteur. Der unveränderte
admittierte Lesesnapshot lädt dessen aktuellen Account/Rolle neu. Vor jeder
Berichtsabfrage muss diese Rolle `admin` oder `supervisor` sein; sonst wird
verweigert. Die SQL-Abfrage entspricht dem nativen Bericht:

```sql
WHERE u.supervisor_id = ? OR ?='admin'
ORDER BY z.startzeit DESC
```

Admins sehen den nativen gesamten Join-Umfang; Vorgesetzte ausschließlich ihre
direkt zugeordneten Accounts. Keine implizite Aufnahme eigener Einträge,
transitiven Teams, Request-Identität/-Rolle oder frei wählbaren Personen.
Die bestehende Rolle-`user`-Warnung bleibt im Inhaltsmarkup erhalten, ist aber
kein Ersatz für diese serverseitige Lesesperre.

**Vor** Suche/Paging prüft der gemeinsame Helfer alle freigegebenen Zeilen mit
`report_record_calculation()` und `report_absence_conflicts()`. Jeder ungültige
Eintrag oder Arbeitszeit-/Ganzabwesenheitskonflikt macht diesen ganzen Bericht
absichtlich nicht verfügbar. Das ist die freigegebene vorsichtige
Migrationsausgabe: keine numerischen Teilkarten, Charts, Modaldaten oder
Tabellensummen. Kein Löschen, Kürzen oder Ersetzen ungültiger Werte durch null
Minuten. Historie, eigene Arbeitszeitliste und Korrekturanträge bleiben verlinkt;
die betroffene Person klärt per bestehender Vier-Augen-Korrektur. Dies ändert
nicht die noch unveränderte native Ausführung.

## Historische Voraussetzungen des ursprünglichen Prerequisite-Pakets

Die folgenden Punkte beschreiben den Stand vor der unten ergänzten optionalen
Integration; sie sind keine Behauptung, dass deren expliziter Opt-in noch fehlt.

1. Separat geprüfte Aufnahme von `supervisor.php` in die **Standard-**
   `AttendancePage`-Activepage-Whitelist, gegebenenfalls Sidebar-Markierung und
   einen geschützten GET-/HEAD-Einstieg mit vorhandener Accountaktualisierung.
   Der neue Controller ist bis dahin absichtlich nicht dispatchbar; keine
   Parallel-Chrome- oder Whitelist-Umgehung.
2. Explizite Zulassung des **vorhandenen** Chart-Assets im bestehenden
   Sicherheits-/Assetkonzept. Dieses Paket fügt keinen externen ausführbaren
   CDN-Script und keinen Tailwind-JS-Compiler hinzu. `initializeCharts()` behält
   den nativen `typeof Chart`-Schutz; Chart-Funktionsabnahme steht aus.
3. Geschützte UI-/Integrationsabnahme im dafür autorisierten veröffentlichten
   Ziel. Kein Browser-/Auth-/Serverergebnis wird hier behauptet.

## Nur grober Quellnachweis

PHP-8.5-Lint, statische Syntaxprüfung der gerenderten Inline-Skripte und ein
zusammenhängender grober Nachweis mit anonymem `sqlite::memory:` für bestehende
Modell-/Feiertagsleser, originaler Formeläquivalenz und reinem de/en-/leer-/
Datenqualitäts-/Konflikt-Inhaltsrendering. Das Chrome wird dabei durch ein
**inertes Proof-Layout** ersetzt und nicht ausgeführt. Keine Anwendungsboot-,
Controller-, Auth-/Sessioninjektion, echte DB-, HTTP-, Server-, Browser- oder
JavaScript-Funktionsprüfung; keine neue CI und keine Veröffentlichung/Deployment.


## Optionale Standard-Chrome-/Routenintegration mit lokalem Chart.js

Basis dieser Ergänzung: Main `91880d9377306ec0d5a73354ba73c56ea4efee94`.
Genau elf Pfade: Registrar, bestehendes `AttendancePage`, `Interactions`,
Standardlayout, explizite synthetische Composition, deren fester Router, dieses
Dokument sowie vier neue Dateien unter
`framework/public/assets/vendor/chartjs/4.6.1/`. Keine Kalender-, Terminal-,
Controller-, DTO-, Reader-, Formel-, Policy-, Guard- oder Bootstrapänderung.

- `LocalWebRoutes::register(..., bool $includeSupervisor = false)` hängt nur
  einen optionalen Parameter an. Defaultaufrufe und alle bisherigen Parameter
  bleiben unverändert. Nur explizites `includeSupervisor: true` registriert
  `GET|HEAD supervisor.php` am vorhandenen Controller, innerhalb derselben
  `RefreshLocalAccount` → `auth:web` → `auth.session`-Gruppe und des unveränderten
  Websession-/CSRF-/Sessionblocking-Stacks. Kein Supervisor-POST/Export/Writer.
  Seine Authfehler gelten nur bei Opt-in als vollständige Seite und redirecten
  zu `login.php`. Kein globaler Fehler-, Middleware- oder Authwechsel.
- **Vor jeder Routenregistrierung** verifiziert der Supervisor-Opt-in die drei
  festen lokalen Upstream-Dateien gegen eingecheckte SHA-256-Werte. Fehlende,
  unlesbare, verlinkte oder veränderte Dateien verweigern die Registrierung.
  Kein Requestpfad, keine URL und kein Konfigurationsflag ersetzt diese Bytes.
  Ohne Supervisor-Opt-in wird diese zusätzliche Dateizulassung nicht ausgeführt.
- Das Standard-`AttendancePage` erlaubt nun `supervisor.php`; nur der vorhandene
  rollenbeschränkte Sidebarlink erhält den Aktivzustand. Das neue angehängte
  `Interactions::supervisor` bleibt standardmäßig `false` und ist nur eine
  ehrliche Verbindungsnotiz, keine Berechtigung und kein Loader-/Authgate.
- Nur bei `activePage === 'supervisor.php'` lädt das Standardlayout das feste
  **same-origin**-Asset `assets/vendor/chartjs/4.6.1/chart.umd.min.js` mit `defer`.
  Es kommt vor dem bereits vorhandenen `DOMContentLoaded`-Initializer zur
  Ausführung; diese Standard-Browserreihenfolge wurde hier nicht im Browser
  abgenommen. Keine externe ausführbare CDN-/Tailwind-Quelle, kein Fallback.
  Die beiden bestehenden Chart-Konfigurationen/Canvases, Daten-/Farbwerte,
  Kartensuche, Tabelle und Dialog bleiben byte-identisch in der Inhaltsvorlage.
- Nur die bereits explizit aufgerufene `LocalWebFixture` setzt die neue
  Präsentationsnotiz und `includeSupervisor: true`. Ihr vorhandener fester Router
  erlaubt zusätzlich `/supervisor.php` und genau das lokale Chart-Asset.
  Keine zusätzliche Assetwildcard, kein Repository-Webroot, Host, Loginweg oder
  Cookie-/Credential-/Sessionwechsel. Die Fixture wurde nicht gestartet oder
  authentifiziert. Die Foundation registriert weiterhin standardmäßig nur `/up`.

Der ursprüngliche `teamReport()` bleibt unverändert autoritativ: frisch
reloadete Admin-/direkte-Vorgesetztenrolle, native SQL-Sicht, gesamte
freigegebene Konflikt-/Datenqualitätsprüfung vor Suche/Paging. Keine
Teilberichtssummen, keine neue Transitivität oder eigene implizite Zeilen.
Das eigene Header-Statuslesen bleibt separat eigenbezogen. Keine Umgehung der
vorhandenen Browser-/Auth-/WIP-Holds durch die neue Verdrahtung.

### Exakte unveränderte Upstream-Bytes und begrenzte Zulassungsentscheidung

[Chart.js 4.6.1](https://github.com/chartjs/Chart.js/releases/tag/v4.6.1),
Upstream-Commit `76d8cf895a7485dc838c90ef37e2bc9d9e388fcb`, wird ausschließlich
als veröffentlichter unveränderter UMD-Build übernommen, nicht installiert oder
lokal gebaut. Das selbstenthaltende Bundle unterstützt die vorhandenen
Kategorie-/Linear-Bar-Konfigurationen ohne Plugin, Datumsadapter oder weiteren
JavaScript-Import. [Offizielle Integration](https://www.chartjs.org/docs/latest/getting-started/integration.html).

| Datei | Bytes | SHA-256 |
| --- | ---: | --- |
| `chart.umd.min.js` | 210082 | `87364cc2b2a76a6684929e7d6b80b18a897794db38400caff1fcf1791c6925d2` |
| `LICENSE.md` | 1093 | `41a84aa2caba645f966a18d9c2056b73e6d3a81d80bc0046bc0011a2634d4cce` |
| `LICENSE-kurkle-color.md` | 1085 | `2859c50313bad2ba77b081410c477beaf92b60ca13a77a248d89859a6dd6ac81` |

Chart.js und das gebündelte `@kurkle/color@0.3.2` sind MIT-lizenziert; beide
vollständigen Lizenztexte bleiben erhalten. `provenance.json` dokumentiert
Registry-URL, Version, Tarball-/Dateihashes, Commit, Lizenz und Grenzen. Das
Registry-Tarball-SHA-512/SHA-1 wurde abgeglichen; 117 reguläre sichere
Archivpfade, keine Links. Die veröffentlichten npm/SLSA-Subject-/Tag-/Commitfelder
passen zu den Bytes, aber **Registry- und Sigstore-Signaturen wurden nicht
unabhängig kryptographisch verifiziert**. Quellmaps sind keine Laufzeitabhängigkeit
und werden weder mitgeliefert noch zum Ändern der veröffentlichten Bytes benutzt.
Kein npm-Install, Lifecycle, Build oder Ausführen des Paket-/Bundlecodes.

**Keine pauschale Sicherheitsbehauptung:** Der in
[Upstream-Issue 12265](https://github.com/chartjs/Chart.js/issues/12265)
beschriebene ungesicherte Defaults-Pfadmechanismus ist im veröffentlichten
4.6.1-Bundle weiterhin statisch vorhanden. In den zwei unveränderten
Supervisor-Aufrufen sind Charttyp, Optionen und Schlüssel feste Literale;
Benutzernamen sind nur escaped Datenlabels, Berichtswerte nur Datasetwerte.
Keine `Chart.defaults`-Aufrufe, frei eingebrachten Optionen/Plugins oder
externen Defaults-Pfade wurden in diesem begrenzten Pfad gefunden. Die
Zulassung gilt deshalb nur für genau diese bestehende datenbezogene Verwendung.
Das ist eine **statische Erreichbarkeitsinferenz**, kein Upstream-Fix,
Exploit-/Laufzeitnachweis, vollständiges Audit oder „vulnerabilitätsfrei“-Urteil.
Eine spätere dynamische Chart-/Plugin-/Options-/Defaults-Erweiterung benötigt
neue Prüfung; sie ist nicht von dieser Entscheidung abgedeckt.

### Grober Nachweis und weiterhin offene Abnahme

Nur ein zusammenhängender grober Source-/Byte-/Inhaltsrender-/Escapingnachweis,
PHP-8.5-Lint, statische Blade-Kompilierung und JavaScript-Syntaxprüfung.
Keine Browser-/Canvas-/Auth-/Session-/Controller-/HTTP-/Server-/echte DB-Prüfung,
keine Paket- oder JavaScript-Ausführung, keine neue CI und kein Deployment.
Die geschützte grobe UI-Abnahme am freigegebenen veröffentlichten Ziel bleibt
offen. Optionale Source-Integration ist weder Defaultaktivierung noch ein
Produktionscutover oder Abschluss der gesamten Laravel-Umstellung.
