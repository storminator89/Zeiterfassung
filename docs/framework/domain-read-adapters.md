# FW-07 / #78 – inaktive gemeinsame Lesedienste

**Erster begrenzter #78-Schritt, kein abgeschlossenes FW-07 und kein Cutover.**
Auf Grundlage der Verträge aus [FW-01](README.md),
[Kompatibilitätsmatrix](compatibility.md) und des
[Authkerns](local-auth-foundation.md) gibt es einen über den Laravel-Container
injizierbaren Vertrag `App\Chrono\ChronoReads` mit einem dünnen
`LegacyChronoReads`-Adapter. Keine bestehende PHP-Seite, UI, Assetdatei,
URL, API-/Terminalantwort, Datenbankdatei oder Hostkonfiguration wird geändert.
Die Routenliste bleibt ausschließlich `GET|HEAD /up`.

## Konkrete Aufrufpfade statt zweitem Regelbestand

| Dienstmethode | Imminenter Bestandskonsument / unveränderte Quelle | Ergebnis und Grenze |
|---|---|---|
| `attendanceStatus()` | `attendance.php?format=status`, `attendance_status()` | Eigener Nutzer, eine Lesetransaktion: `success,start_metadata_version,records,report_finality,expected_record,state,record_id`. `records` enthält zusätzlich die exakte Dezimalzeichenfolge `id_string`; `expected_record` ist die exakte Dezimalzeichenfolge der aktiven Buchung oder `null`. Die zuletzt **angelegten** 30 Projektionen nach `id DESC`, keine neue Startzeit-Sortierung. |
| `reportRows(bool $chronological = false)` | Eigene Berichte/Export, `zeiterfassung`-Lesepfad | Eigene vollständige unveränderte Projektionszeilen nach `startzeit DESC`; offene Zeilen, gespeicherte Abwesenheitsart und Sollgutschrift bleiben erhalten. Nur opt-in Home verwendet `chronological: true` mit nativer Instant-/ID-Sortierung; Standard/Korrekturen bleiben unverändert. Kein fertiger CSV-Serializer oder API-Paginator. |
| `period(start,end,subject?)` | Dashboard-/Teamprojektion | `report_days`, `report_period_minutes`, `planned_hours`, `report_format_hours`, `report_percentage` bleiben die einzigen Rechenfunktionen. Liefert interne Felder `days,actual_minutes,planned_hours,actual_formatted,percentage`, **kein neues HTTP-Antwortformat**. Ohne Subjekt eigene Daten; explizites Subjekt nur nach bestehender `view`-Policy. |
| `corrections()` | Antragshistorie in `adjustments.php` | Derselbe Join, Scope, `id DESC LIMIT 100`, Originalsnapshot und finale Entscheidung wie im Bestand. Keine Antrag-/Freigabe-/Stornofunktion. |
| `history(before)` | `history.php` | Derselbe Cursor `max(1,before)`, `id DESC LIMIT 100`, eigene/direkte Team-/Adminsicht; Admin sieht auch Benutzer-0-Kalenderjournal und Historie gelöschter Konten. Keine Audit-CRUD-API. |
| `workModels()` | `work_models.php` | Derselbe Join, `valid_from DESC,id DESC LIMIT 200`; nur eigene Modelle oder alle für aktuelle Admins. Supervisorrechte werden **nicht** auf fremde Modelle ausgeweitet. |
| `workModelsPage(int $page = 1, ?int $selectedModelId = null)` | Opt-in Home/Modell-UI nach PR136 | Ein autorisierter Snapshot: 50er-Versionenliste, Anzahl/Seite, scoped Auswahl und heute maßgebliches Modell desselben Eigentümers. `null` ohne Auswahl; `0` ungültige explizite Auswahl. Alter `workModels()`-Vertrag bleibt erhalten. |

Die weiteren Listen verwenden bewusst die aufgenommenen SQL-Projektionen:
deren Logik liegt noch in den Seiten, nicht in aufrufbaren Fachfunktionen.
Es gibt keinen kopierten Schreibablauf und kein direktes Eloquent-CRUD.
`lib/attendance.php`, `lib/practice.php`, `lib/reporting.php` und deren
`booking.php`-Abhängigkeit werden ausschließlich als Funktionsdefinitionen
geladen. `config.php`, `functions.php`, Migrationen, `practice_connection`,
Session-/JSON-/Header-/Exit-Wrapper werden niemals aufgerufen.

## Authentifizierte Grenze und Verbindungsfreigabe

- Die Schnittstelle akzeptiert **keinen Akteur, keine Rolle, Teamfelder,
  `source`, Auditfelder oder frei zusammengebauten Modellobjekte**. Ausschließlich
  Laravels aktueller Guard liefert die numerische Identität als `LegacyUser`.
  Rolle und direkte Teamzuordnung des Akteurs/Subjekts werden innerhalb derselben
  Lesetransaktion erneut aus `users` gelesen. Unbekanntes/gelöschtes Konto und
  unbekannte Rolle werden verweigert. Ein veralteter Adminwert im Guard kann
  daher keine aktuellen Teamrechte erweitern.
- Das ist **keine eigenständige Authentifizierung oder Passwortwiderrufslogik**.
  Ein späterer lokaler Webaufruf muss weiterhin die freigegebene Reihenfolge
  `RefreshLocalAccount` → `auth:web` → `auth.session` aus #76 durchlaufen.
  Authquelle und Passwortänderungs-Widerruf bleiben dort; HTTP-/CSRF-/Loginfehler
  werden nicht hier vereinheitlicht. Künftige API-/Terminaladapter benötigen
  vorher ihren separat freigegebenen Guard-/Grant-Vertrag. Kein Aufruf ist
  heute öffentlich erreichbar.
- `subject` ist ausschließlich ein auszuwählender Berichtsnutzer. Die bestehende
  `view`-Policy erlaubt eigene Daten, Admin alle und Supervisor nur unmittelbare
  `supervisor_id`-Zuordnung; keine rekursiven Teams/Departments. Sichtbarkeit ist
  keine Schreib- oder Korrekturfreigabe. Die finale Vier-Augen-Prüfung bleibt
  dem späteren Writerdienst unter Lock vorbehalten.
- Providerregistrierung und Dienstauflösung öffnen weder DB noch Authsession.
  Die fest benannte Verbindung `chrono` bleibt im ausgelieferten
  `config/database.php` **nicht konfiguriert**. Ein Methodenaufruf verweigert das
  vor Guard-/DB-Auflösung; kein Rückgriff auf Scratch-`sqlite`, `DB_*`,
  `APP_DATA_DIR`, `CHRONO_DB_PATH`, URI oder selbst erzeugten PDO.
- Eine spätere separat reviewte Aktivierung muss die ausdrücklich zugelassene,
  auditfähige SQLite-Leseverbindung `chrono` bereitstellen. Der Adapter akzeptiert
  nur SQLite beziehungsweise den vorhandenen anonymen Memory-Fixturedriver,
  keine `read`-/`write`-Replica, URL oder fremde Treiber. Snapshotmodus ist
  `DEFERRED`; kein Beitritt zu einem laufenden Laravel-/Raw-Writer und kein
  eigener Lock-/Retry-/Transaktionsadapter. Laravels normale Lesetransaktion
  läuft genau einmal auf demselben PDO. Diese Prüfung ist **keine Datei-Adoption**
  und ersetzt keine #74/#75-Laufzeitfreigabe.

## Erhaltene Rechenbedeutung und offene Grenzen

Epoch-/Offsetinterpretation und Berlin-DST kommen aus `booking_time` und
`booking_local_date`. Abgeschlossene Nachtschichten gehören weiterhin zum
Starttag; offene Zeilen werden nicht in "bis jetzt" umgerechnet. Minuten,
tatsächliche gespeicherte Pause, Rundung und Prozentbegrenzung kommen unverändert
aus den bestehenden Helfern. Zeitraumgrenzen sind inklusive lokale Datumswerte;
auch die vorhandene leere Auswertung bei umgekehrtem Zeitraum wird nicht
heimlich in eine neue 422-Regel geändert.

Historisches Soll kommt aus `planned_minutes`/`planned_hours`: jüngstes passendes
`valid_from,id`, Feiertage null. Bereits gespeicherte Abwesenheitsgutschriften
werden **nicht** durch später geänderte Modelle neu berechnet. Die Dienstgrenze
erfindet keine Nettozeitkonto-, Urlaubs- oder Payrolllogik und übernimmt keine
unreferenzierten fachlichen Fixes aus #35/#36. Die bekannte direkte
API-`expectedRecord`-Lücke und API-Datums-/Paginationgrenzen bleiben außerhalb
dieses reinen Lesepakets.

## Tatsächlich geprüft und noch offen

```sh
cd framework
php tools/rehearse-domain-reads.php
```

Eine grobe synthetische Integration, **keine Mikrotests oder Vollsuite**. Der
parameterlose Befehl verweigert bereits konfigurierte/offene Verbindungen,
verwendet ausschließlich einen neuen anonymen SQLite-`:memory:`-Bestand aus
dem vorhandenen V2-Fixturebuilder, einen Wegwerfschlüssel nur im Prozess und
Arraysession. Kein HTTPserver, Port 18083, gemeinsames Fixture, reales Konto,
Datei-/Schlüssel-/Credentialzugriff, Netzwerk oder CI-Lauf.

Vier Gruppen: inaktiver Boot/namentliche Verweigerung ohne Defaultconnection;
exakte Status-/Rohzeilenparität plus Nacht-DST, offener Datensatz, Modellwechsel,
gespeicherte Gutschrift und Feiertag; eigene/direkte Team-/Adminsicht mit
veralteter Guardrolle, unbekanntem Konto und anonymem Aufruf; unveränderte
SourceSnapshot-Digests einschließlich Audit/Sequenzen und unveränderte
Audit-/native Sessionglobals. Authentifizierte Modelle werden nur in diesem
synthetischen Befehl über `setUser` gesetzt; das ist kein produktiver Loginpfad.

Am 27.10.2026 bestanden unter PHP 8.5.12 / Laravel 13.38.0; notwendige
PHP-Syntaxprüfung, Composer-Validierung/Plattformprüfung und `git diff --check`
bestanden. Dies ist **keine** Abnahme gegen einen echten Datenbestand,
Web-/API-/Terminalcookies oder -geräte, keine CSV-/UI-/Exportabnahme und kein
Nachweis der vollständigen #78-Abnahmeliste.

Am 30.10.2026 wurde ausschließlich die veraltete Statuserwartung dieses
bestehenden Read-Rehearsals an den bereits vorhandenen additiven Vertrag
angepasst. Vorher brach der parameterlose Befehl in der Status-/Projektionsgruppe
ab, weil `start_metadata_version`, `expected_record` und `records[].id_string`
fehlten. Der genaue Arrayvergleich bleibt erhalten; keine Felder werden vor dem
Vergleich entfernt. Dieselbe synthetische Reise prüft nun eine aktive Buchung
mit ID `9007199254740993` (oberhalb der JavaScript-Ganzzahlpräzision) samt exakter
Stringbindung sowie ein Konto ohne Buchung mit `expected_record=null`.
Alle bisherigen DST-/Modell-/Scope-/SourceSnapshot-Prüfungen bleiben bestehen.
Das repariert einen begrenzten Laravel-Lesenachweis für die weitere
#79/#82-Abnahme, nicht Reader, Runtime, Schreibpfad, HTTP-/UI-Aktivierung oder
Cutover. Die Prüfung bleibt ausschließlich anonymes SQLite-`:memory:` und
ersetzt keine der vorstehenden offenen Abnahmen.

**Nächster separater Schritt:** Anwendungsdienst für Stempeloperationen auf dem
exakt reviewten gemeinsamen #75-`AttendanceWriter`, ohne zweite Transaktion,
Idempotenzablage oder Auditierung. Antrag/Entscheidung/Storno, Abwesenheit und
append-only Arbeitsmodell benötigen zunächst eine gesondert reviewte
transportfreie Extraktion ihrer heute seitengebundenen Schreibpfade. Ihre
bestehenden Regeln, Serverzeit, Grund, Originale und Vier-Augen-Rechte bleiben
unverändert; sie werden hier weder kopiert noch als fertig behauptet.
