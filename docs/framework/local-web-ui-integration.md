# FW-08 / #79 – explizite lokale Webintegration

**Separater Folgeschritt der [Bestandsansichten](existing-ui-views.md).
Kein produktiver Cutover, kein vollständiges #76/#78/#79.**
Die wiederverwendbaren Laravel-Controller und der explizite Route-Registrar
verbinden ausschließlich lokalen Login, eigene Anwesenheit/Status und Logout
mit den bereits reviewten Diensten. Das vorhandene Markup/Design bleibt bestehen.
`routes/web.php`, Bootstrap, Provider, Legacy-Seiten und vorhandene
Auth-/Read-/Write-/API-Dienste werden nicht aktiviert oder umgeschrieben.
Die einzige Änderung eines bestehenden Assets ist die ausdrücklich genehmigte
einzelne Pending-Löschbedingung für 401/403 in `assets/js/attendance.js`.

## Wiederverwendbarer Transport, nicht zweiter Fachkern

- `App\Http\LocalWebRoutes::register($app)` wird **nirgendwo standardmäßig
  aufgerufen**. Nur eine ausdrückliche separat zugelassene Composition darf
  diesen Registrar benutzen. Er öffnet keine Datenbank und bindet keinen Writer.
- Anonyme `GET/POST login.php`: normale Laravel-Cookies/Session, `LegacyFormCsrf`
  **anstelle** des Standard-CSRF-Filters; POST zusätzlich
  `throttle:chrono-web-login`, dann `LocalSession::attempt`. Erfolg wie bisher
  Redirect `index.php`, Fehler mit den unveränderten DE/EN-Logintexten im
  vorhandenen Formular. Keine Registrierung, LDAP, Passwort-/Tokenmutation.
- Geschützte `GET/POST /`, `index.php`, `attendance.php` und `POST logout.php`:
  Session/CSRF → **`RefreshLocalAccount` → `auth:web` → `auth.session`** →
  Controller. Der Registrar ergänzt Laravels Standard-Middlewarepriorität nur
  bei explizitem Aufruf, damit der Sortierer diese Reihenfolge erhält.
  Authfehler bei Status-/Stempelaufrufen liefern zwingend 401 Text
  `Please sign in again.`, niemals erfolgreiches Login-HTML oder Weiterbuchung.
  Seiten-Navigation/Logout verweist zur Neuanmeldung auf `login.php`; keine
  globale API-/Authfehlerumschaltung oder zweiter Passwort-/Fingerprintmotor.
  Die widerrufene asynchrone Bestandsantwort wird damit erhalten. Anonyme
  asynchrone Requests bekommen ebenfalls 401 statt des früheren Redirects;
  widerrufene ganze HTML-Seiten redirecten statt des früheren 401-Textes.
  Diese ausdrücklich begrenzten Navigations-/Anonymtransportabweichungen bleiben
  vor einem produktiven Cutover zu entscheiden, nicht als Vollparität behauptet.
- `GET …?format=status` delegiert unverändert an `ChronoReads::attendanceStatus`.
  HTML erhält ausschließlich die bereits aktualisierte Guardidentität und
  den bestehenden eigenen Status. Nicht verfügbare Statistikwerte bleiben `—`;
  keine neue Formel oder direkte Query im Controller.
- `POST attendance.php` und seine vorhandenen Root-/Index-Includealiase lesen
  **nur den POST-Form-Bag**: `event_id`, `action`,
  `confirm_end_pause === '1'` und die neue kanonische positive Stringbindung
  `expected_record`. Der feste Webdienst verlangt für neue Nicht-Start-Aktionen
  eine Bindung; bereits gespeicherte alte Null-Payloads werden zuerst unverändert
  wiederholt. Fehlende neue Bindung und abweichende aktuelle Sitzung ergeben409
  ohne neue Buchung, nicht automatisch eine Aktion auf der neuen Sitzung.
  [Exakter #36-Vertrag und bewusste Kompatibilitätsgrenze](../CH30-EXPECTED-RECORD.md).
  Für genau diese Anwesenheitspfade werden die normalen Laravel-Stringtrimmer/
  Leerstring-Konverter übersprungen: `' 1 '` darf nicht unbemerkt zu `'1'`
  werden, leere Event-/Aktionsfelder bleiben leere Strings statt `null`.
- `LocalWebAttendance::transition` bleibt der einzige Web-Anwendungsdienst,
  `AttendanceWriter` der einzige Schreib-/Transaktionsadapter, `web:{id}` der
  bestehende Quellnamensraum. Fachfehler behalten Status/JSON-Nachricht,
  technische Fehler liefern generische 503/ungeklärte Bestätigung. Keine eigene
  Buchung, Transaktion, Wiederholung, Auditablage oder Eingabe von Akteur/Quelle.
- `Interactions` ist ausschließlich eine vertrauenswürdig injizierte
  Präsentationsfreigabe für Login/Stempeln/Logout. Alle Defaults bleiben
  `false`; keine Requestvariable steuert sie und sie ersetzt keine Middleware.
  Buchungsknöpfe starten stets deaktiviert, bis der zugelassene Client bereit ist.

## Vorhandener Client und ungeklärte Ereignisse

Nur bei freigegebener Anwesenheitsinteraktion wird der kleine Framework-
Startup-Adapter `chrono-local-web.js` geladen. Er prüft vor dem vorhandenen
`assets/js/attendance.js` die vorhandene Sessionstorage-Nachricht. Fremde
Konto-/CSRF-Bindung, kaputtes JSON, ungültige Felder oder nicht zugänglicher
Storage lassen den rohen vorhandenen Wert unberührt; Stempeln bleibt gesperrt
und der vorhandene Feedbackbereich erklärt den Klärungsbedarf. Es gibt weder
stilles Löschen noch Umbenennen/Neubuchen oder automatischen Kontowechselretry.
Nur gültiges gleiches Konto **und** Token darf den bestehenden stabilen
Ereignis-ID-/In-page-/Reloadretry des ursprünglichen Clients verwenden.

Die ausdrücklich genehmigte einzelne Änderung am bestehenden Client nimmt
**401 und 403** von seiner bisherigen Löschbedingung bei Fehlern unter 500 aus.
Auth-/CSRF-Verweigerung darf ein bereits unbestätigtes Ereignis nicht löschen.
Alle anderen Status-, Retry-, ID-, Buchungs- und UI-Regeln bleiben unverändert;
keine künstliche 503 oder neue ID als Ausweichweg.

Der #36-Folgeschritt ergänzt ausschließlich die unveränderliche Recordbindung
neuer Pendingevents und validiert sie, wenn sie vorhanden ist. Alte Pendingevents
ohne Feld bleiben unverändert für die serverseitige Replay-zuerst-Entscheidung
erhalten; keine nachträgliche Bindung an die aktuelle Sitzung. Webstatus liefert
zusätzlich den exakten eigenen Record als String, ohne die numerische `record_id`
oder den APIstatus umzudeuten. Nicht-Start-Aktionen bleiben ohne aktuelle Bindung
gesperrt; eine historische Buchungsbestätigung hebt diese Sperre nicht auf.
Erst ein neuer gültiger Statussnapshot liefert wieder eine aktuelle Bindung.

**Offene #57-Grenze:** Erhalt des Pendingevents plus Startup-Gate ist noch keine
vollständige konto-/sitzungsübergreifende Klärung oder Antwortverlustbehandlung
nach erneuter Anmeldung. Fremdes/altes Pending wird bewusst blockiert; ein
produktiver sicherer Klärungs-/Wiederanmeldepfad ist noch gesondert erforderlich.
Kein automatisches Löschen zur Freigabe der Oberfläche und kein Abschluss des
übergeordneten Pending-Themas durch diesen begrenzten Fix.

`main.js`, historische AJAXeditoren und HTML-Fragmente werden nicht geladen.
Abwesenheit bleibt gesperrt. Dashboard/Settings/Admin/Team/Korrekturen/Modelle/
Historie/Terminals/CSV/Setup/API/Terminal gehören nicht zu dieser Welle; der
isolierte Fixture-Einstieg verweigert sie ausdrücklich, statt einen Legacy-
Fallback oder eine fremde Anmeldung vorzutäuschen. Die bisherigen Linkziele
bleiben unverändert und sind nicht als schon migrierte Funktionen ausgewiesen.

## Synthetischer isolierter Laufzeitnachweis

Nur der gesonderte Fixture-Launcher/-Router richtet für die grobe lokale
Abnahme einen neuen privaten bekannten V2-Bestand mit Wegwerfkonten, eigener
stabiler Frameworkverschlüsselung, privaten persistenten File-Sessions/Cache
und ausdrücklich isoliertem Cookienamen ein. Keine Produktionspfade,
Zugangsdatenargumente, native Sessionkopie, `.env`- oder Host-/Proxyänderung.
Secure/HttpOnly/SameSite-Defaults werden nicht abgeschwächt; tatsächliche
Secure-Cookie-Annahme über Loopback muss der unterstützte Browser nachweisen.
Eine lokale HTTP-Abnahme ist kein Nachweis produktiver HTTPS-/Proxyfreigabe.
Die Fixture aktiviert zusätzlich Laravels Standard-Sessionblocking für den
gesamten opt-in Webstack: eigener persistenter File-Lockstore, **60 Sekunden
Lock-TTL / 10 Sekunden Wartezeit**. Der Registrar verweigert fehlende oder
prozesslokale Sperrkonfiguration vor Routenregistrierung. Die
[Betriebs-/Aktivierungsgrenzen](local-auth-foundation.md#lock-betriebsgrenze-vor-aktivierung)
gelten unverändert; der einzelne CLI-Serverworker ist kein Konkurrenznachweis.

Auth/Reads benutzen denselben ausdrücklich zugelassenen Bestand/Benutzer-IDs,
mit eigener read-only `chrono`-Verbindung und DEFERRED-Snapshots. Jede Mutation
erhält einen neu zugelassenen dedizierten PDO und unveränderten #75-Writer;
kein Singleton, alter Adopterhandle, Manager-/Read-PDO oder Wiederverbindung.
Offlinevorprüfung/Backup/Adoption kommen aus den vorhandenen #74-Bausteinen.
Der HTTP-Einstieg liefert nur die expliziten Seiten und notwendigen Assets,
nie einen Repository-Webroot oder beliebige PHP-/Daten-/Manifestdateien.
Der bestehende Server auf 18083 bleibt unverändert.

```sh
cd framework
php tools/prepare-local-web-fixture.php
# Nur den ausgegebenen isolierten Launchbefehl mit dem neuen Manifest verwenden.
# Dann die ausgegebene localhost:18090-URL im unterstützten Browser öffnen.
```

Die Vorbereitung startet keinen Listener; es gibt keine Datenbank-/Credential-
Pfadargumente. Ausgegeben werden ausschließlich die neu erzeugte private
Fixture und ausdrücklich synthetische lokale Konten. Ein Betrieb mit echten
Konten ist durch diesen Launcher nicht freigegeben.

## Tatsächlich grob geprüft am 27.10.2026

Unter PHP 8.5.12 / Laravel 13.38.0: PHP-Syntax/JS-Parse, Composer-/Plattform-
prüfung, `git diff --check` und erneutes Rendern aller vier standardmäßig
inaktiven Seiten bestanden. Explizite isolierte Routenauflösung zeigt die
geforderte Middlewarefolge ohne DB-Verbindung; normales Boot weiter nur `/up`.

**Ein realer normaler Browserjourney** über den unterstützten nativen Cloud-
Terminal/CUA-Browser bestand auf einer neuen bekannten V2-Fixture:
Login → Kommen → Pause beginnen → Pause beenden → Gehen → Logout.
Alle vier Serverbestätigungen und die jeweiligen Statuswechsel waren sichtbar,
danach die Loginseite. Die vorhandene Gestaltung wurde auch in den tatsächlichen
aktiven Ansichten angesehen, ohne neue Oberfläche. Eigene verschlüsselte
Secure/HttpOnly/Lax-Sessions waren unverändert konfiguriert; der reale
Login-/geschützte POST-/Logout-Roundtrip auf localhost gelang ohne Abschwächung.
Cookieinhalte oder produktive HTTPS-/Proxybedingungen wurden nicht geprüft.

Read-only DB-Abschluss: exakt vier Events für `web:1`/Benutzer 1, jede der vier
Arten einmal, eine geschlossene Arbeitssession und eine geschlossene Pause,
keine offenen Intervalle, neun Web-Auditzeilen, SQLite `quick_check=ok` sowie
unveränderte Original-/Backuphashes. Keine achtstündige Zeit-/Pausenmessung.

**Ein kritischer Folgefall und dessen begrenzte Fehlerbehebung:** Nach frischer
Anmeldung wurde nur das private Fixturepasswort unter Audit geändert. Der erste
Lauf blieb zwar ohne neue Buchung und erhielt den Retryhinweis, zeigte aber im
vorhandenen Serverlog einen echten `RouteNotFoundException`: der explizite
Registrar läuft nach Laravels normalen Boot-Callbacks, während der Standard-
`auth.session`-Widerruf bereits den benannten Loginredirect auflösen muss.
Dieser Lauf wird nicht als bestandene Authverweigerung umgedeutet.

Der separat unabhängig reviewte Fix aktualisiert am Registrarende ausschließlich
Laravels Standard-Namens-/Aktionsindizes (`refreshNameLookups` und
`refreshActionLookups`). Vorher war `route('login')` reproduzierbar unbekannt,
danach auflösbar. Keine eigene Authprüfung oder Middlewareausnahme.

Nur der betroffene Fall wurde auf einer **weiteren neuen** privaten V2-Fixture
wiederholt: frischer erfolgreicher Login → genau eine auditierte
Passwortrevokation → genau zwei gleiche Kommen-Fehlerversuche. Beide behielten
sichtbar den Hinweis „Dieselbe Aktion erneut versuchen“, blieben abwesend und
zeigten weder Login-HTML noch Navigation. Im neuen vorhandenen Serverlog keine
ERROR-/Exception-/Fatal-/500-Zeile, insbesondere kein RouteNotFound mehr.
Final read-only: **null** Events, Arbeitssessions, Pausen oder Web-Auditzeilen,
`quick_check=ok`, Original/Backup unverändert. Der normale Vier-Event-Journey
wurde nicht erneut als zusätzliche Suite ausgeführt.

**Messgrenze:** Der dokumentierte Browser-/Serverweg lieferte für diese
Routerrequests keine unabhängigen HTTP-Statusnummern oder tatsächlichen Event-
IDs. Eine gemessene Folge 401→403, identische Wire-ID oder vollständige
Antwortverlust-/sitzungsübergreifende Parität wird deshalb **nicht behauptet**.
Belegt sind reale Retry-/Pendinganzeige, keine unberechtigten Buchungs-/Audit-
änderungen und die Beseitigung des beobachteten Laufzeitfehlers. Die expliziten
401-/403-Handler sowie deren einzelne Pending-Löschbedingung sind zusätzlich
quellstatisch unabhängig geprüft, nicht mit Wiremessung gleichgesetzt.

Lokale Prüfarbeitsdaten außerhalb des Repositorys enthalten die echten
Screenshots/Textbelege, ursprünglichen Fehlerlogs, gezielten Fix-Repeat und
Vorher-/Nachher-DBberichte. Kein Chromium-Unterprozess, `file://`, DevTools-
Emulation, reale Zugangsdaten, Port 18083, Mikro-/Pixeltests, neue CI-Läufe,
öffentliche Vorschau oder produktive Runtimeänderung. Alle übrigen Seiten,
Abwesenheit, Statistikprojektion, LDAP und vollständige #79-Abnahme bleiben offen.

Beide temporären 18090-Läufe wurden beendet, ihre eigenen Browser-/Terminaltabs
geschlossen und der fehlende Listener nativ bestätigt. Testanmeldungen wurden
durch Logout beziehungsweise Passwortwiderruf beendet; keine hostweiten Cookies
oder bestehende 18083-Sitzung wurden zur Bereinigung angefasst.

### Ein grober isolierter Session-Konkurrenznachweis

Eine separate private CLI-Probe unter PHP 8.5.12 / Laravel 13.38.0 verwendete
unveränderte installierte `StartSession`, `SessionManager`/`EncryptedStore`,
File-Sessions und persistente Standard-Filecachelocks in zwei Prozessen. Kein
Anwendungsboot, Browser, HTTP-Listener, Authproviderquery oder Datenbankzugriff.
Kontrollierte Reihenfolge ohne Blocking: Statusrequest liest den synthetischen
Authsnapshot → Logout entfernt den alten Sessionbestand nachweislich → späterer
Statusabschluss erzeugt die alte Datei samt Auth-/Passwortmarkern erneut.
`StartSession` liefert dabei den alten Session-Cookiewert, Logout einen neuen.

Dieselbe grobe Probe mit Standardblocking hielt den gemeinsamen Lock während
des Statusrequests; unabhängige Lockversuche konnten ihn nicht erwerben. Der
Logouthandler trat erst nach dessen Abschluss ein und entfernte anschließend
die alte Datei; Auth-/Passwortmarker blieben weg. Secure/HttpOnly/Lax blieben in
beiden Varianten erhalten. Die Probe zeigt den Server-Sessionsnapshotfehler und
seine Serialisierung, **keine beobachtete Browser-Cookierücknahme**, keine echte
Anmeldung und keine vollständige Kontowechsel-/Pendingparität. SQLite-#75-Proofs
ersetzen diesen Sessionnachweis nicht. Standardboot/aktive Legacyanwendung und
die vorhandenen 18083-/18090-Läufe wurden nicht berührt; die Lücke betrifft die
ausdrückliche Mehrrequest-Aktivierung, nicht einen behaupteten produktiven Vorfall.
