# FW-07 / #78 – inaktiver lokaler Web-Stempeldienst

**Separater kleiner Folgeschritt der [Lesedienste](domain-read-adapters.md),
kein vollständiges #78 und keine Route-/UI-/Loginumschaltung.**
`App\Chrono\LocalWebAttendance` ist eine typisierte Anwendungsgrenze für
Start/Pause/Weiter/Ende. `LegacyLocalWebAttendance` fügt nur die vorhandene lokale
Webidentität und den unveränderten Web-Quellnamensraum hinzu und delegiert an den
exakt reviewten [gemeinsamen #75-Writer](sqlite-write-proof.md).

## Eine Grenze, keine zweite Stempellogik

```php
$attendance->transition($eventId, $action, $confirm, $expectedRecord);
```

- Nur fachliche Eingaben: Ereignis-ID, Aktion, typisierte Bestätigung und optional
  erwartete Projektion. **Kein Benutzer, Akteur, Rolle, Kanal, `source`,
  Clientzeitpunkt, Auditgrund oder Writer-/Dateipfad aus dem Request.**
- Identität ausschließlich aus Laravels festem `web`-Guard als `LegacyUser`,
  `auth_source=local`, bekannte Bestandsrolle, positive ID. Ungültige/anonyme
  Identität wird vor Writerauflösung verweigert.
- `source` ist exakt **`web:{id}`**, wie bereits in `attendance.php` und
  `save.php`. Kein neuer Migrations-/Frameworknamensraum, sonst wären alte
  Pendingereignisse und gespeicherte Antworten nicht mehr wiederholbar.
- Aufruf von `AttendanceWriter::transition(id,source,eventId,action,confirm,expectedRecord)`
  ohne Änderung der Eingaben oder Antwort. Serverzeit, Payloadhash,
  `(source,event_id)`, gespeicherte JSON-Antwort, erwartete Projektion,
  Überlappungen, aktive Pause, Audit und Konflikte bleiben ausschließlich bei
  der vorhandenen Fachfunktion und dem einzigen #75-Transaktionsadapter.
- Keine eigene Transaktion, Query, Eloquentmutation, Idempotenz-/Auditablage,
  Rechenregel, Zufalls-ID, Queue, Fehlerumwandlung oder automatische Wiederholung.
  Fach-/Technikfehler werden unverändert weitergereicht; der spätere Transport
  muss deren bestehenden Status-/Antwortvertrag bewahren.

## Auth- und Betriebsvoraussetzungen bleiben ausdrücklich verpflichtend

1. Vor einem späteren lokalen Webaufruf zwingend die bereits freigegebene
   #76-Reihenfolge **`RefreshLocalAccount` → `auth:web` → `auth.session`**,
   zusätzlich Websession/CSRF für unsichere Methoden. Nur diese Kette stellt
   aktuelle lokale Quelle/Rolle und Passwortwiderruf her. Der Dienst ist **kein
   Login, kein nativer PHP-Sessionadapter und keine eigenständige Revokation**.
2. Keine neue Authprüfung unter Writerlock: die vorhandene Domäne überprüft dort
   weiterhin die Existenz des Kontos. Ein Wechsel der Authquelle nach der
   Middlewareprüfung wird nicht durch eine neue Lock-time-Regel abgefangen.
   Das ist die erhaltene Assurance, keine Behauptung einer stärkeren atomaren
   Auth-/Passwortprüfung. Eine Änderung benötigt eine separate Review.
3. `AppServiceProvider` registriert nur einen **lazy scoped** Dienst. Selbst
   Auflösung öffnet keine DB/Session und registriert keinen Writer. Nach gültiger
   Authentifizierung muss ein vertrauenswürdiger, separat freigegebener
   Operation-Scope ausdrücklich `AttendanceWriter::class` bereitstellen.
   Ohne diesen Binding verweigert der Dienst; der Container darf nicht versuchen,
   einen PDO oder eine Dateikonfiguration automatisch zu konstruieren.
4. Diese spätere vertrauenswürdige Composition muss die #74/#75-Zulassung,
   Offlinebackup/Adoption und einen frischen dedizierten unveränderten
   PHP-8.5-SQLite-PDO erfüllen. Auth-/Leseidentität und Writer müssen denselben
   zugelassenen Bestand mit denselben Benutzer-IDs meinen. Keinen Adopter-PDO,
   Scratchdefault, Replica, alten Scope oder parallelen Raw-Writer verwenden.
   Writer/PDO nicht als Singleton oder `instance` über Operation-/Request-/Job-
   Scopewechsel hinweg aufbewahren; nach jeder Operation verwerfen und neu zulassen.
   Dieses Paket stellt **keinen** produktiven PDO-/Pfadfactory oder eine
   `chrono`-Runtimeverbindung bereit und ändert keine SQLiteeinstellung.
5. Spätere Controller/Form Requests behalten ihre konkreten Transportverträge.
   Im Web bedeutet `confirm_end_pause` bisher exakt Zeichenfolge `'1'`; keine
   pauschale Laravel-`boolean`-Normalisierung/422-Antwort als Migrationsnebenwirkung.
   Direkte Web-Anwesenheit gibt keine erwartete Projektion weiter; deren
   Standard bleibt `null`. Die Zufalls-ID-Schwäche des fehlenden `save.php`-Alias-
   Events wird weder in den Dienst verlagert noch als sichere Idempotenz verkauft.
   Hier wird kein spekulativer Form Request ohne zugehörigen Endpoint eingeführt.

**API und Terminal verwenden diesen lokalen Webdienst nicht.** Ihre separaten
Guard-/Grant-, Quellen-, Rohbody-/Nonce- und Transportverträge müssen eigene
dünne Anwendungsadapter auf demselben Writer erhalten. Kein APIaufruf darf
zur Bequemlichkeit in den `web:{id}`-Namensraum umetikettiert werden.
Korrekturantrag/-entscheidung/Storno, Abwesenheit und Modellschreiben bleiben
weiterhin außerhalb dieses Teilpakets; keine CRUD-Abkürzung oder Eigenfreigabe.

## Grobe synthetische Abnahme

```sh
php framework/tools/rehearse-web-attendance.php
```

CLI-only, ohne Datei-/Konto-/Credentialargumente. Ein neuer Memory-V2-Bestand aus
dem vorhandenen Fixturebuilder wird unter einem frischen privaten
`/tmp/chrono-web-attendance-*`-Verzeichnis (`0700`, Dateien `0600`) in kalte
Original-/Backup-/Zielkopien persistiert und durch das vorhandene Offlineverfahren
adoptiert. Nur dieser synthetische Befehl bindet den Writer explizit; jeder
Aufruf erhält einen neu zugelassenen dedizierten PDO. Wegwerf-Frameworkschlüssel
und Arraysession bleiben im Prozess. `setUser` im Werkzeug ist nur synthetische
Zusammensetzung, **keine Auth-/Login-/Middlewareabnahme**.

Ein grober Lebenszyklus: anonyme/nichtlokale/unbekannte Rolle und fehlender
Writer werden verweigert; eigene Startbestätigung mit unverändertem `web:1`,
identische verlorene Antwort, andere Payload/erwartete Projektion als Konflikt;
Pause, verweigertes unbestätigtes Ende, Weiter, Ende und spätere identische
Retries. Vier Events persistieren genau einmal, Original-/Backuphash bleiben
unverändert, Auditkontext wird wiederhergestellt, keine Default-/`chrono`-
Managerverbindung wird konfiguriert oder geöffnet. Der Ablauf dauert Sekunden,
**kein echter achtstündiger Arbeitstag oder Pausenminuten-/Hardwaretest**.
Zeitberechnungen und Konkurrenz-/Rollbackfehler sind die bereits separat
abgegrenzten Read-/#75-Beweise, nicht neu kopierte Feinprüfungen.

Am 27.10.2026 mit PHP 8.5.12 / Laravel 13.38.0 bestanden; PHP-Syntax,
Composer-Validierung/Plattformanforderungen, `git diff --check` und unveränderte
Routenliste (`GET|HEAD /up`) geprüft. Keine produktiven Daten, Schlüssel,
Zugangsdaten, Port 18083, gemeinsamen Fixtures, lokale HTTPfunktionstests,
neuen CI-Läufe oder Remote-/Host-/Sicherheitsänderungen. Echte Middleware,
Cookies, Antwortverlust im Browser, Alt-Pendingstore und operative
Runtime-/Routeaktivierung bleiben gesonderte Gates. #78 bleibt offen.
