# ADR 0001 – Laravel einführen, Fachlichkeit und Oberfläche bewahren

Status: Architekturentscheidung für FW-01; Laufzeit-/Cutoverfreigabe ausdrücklich offen. Basis und Beweisgrenzen stehen in [README](README.md).

## Entscheidung und Gründe

1. **Laravel 13, PHP 8.5, nur `framework/`.** Das offizielle Gerüst und Laravel-Standardkomponenten verwenden; konkrete Version über Composer-Lock. Die [Supporttabelle](https://laravel.com/docs/13.x/releases#support-policy) führt PHP 8.3–8.5 für Laravel 13. Das ist kein Nachweis der tatsächlich installierten Extensions oder Drittanbieterpakete. PHP-/Containerupgrade gehört #58, Foundation #73. Root-`router.php`, `.htaccess`, Docker-Webroot und bestehende URLs bleiben zunächst unverändert.
2. **Keine neue UI.** Die Quodara-Seiten, Sidebar, Kolibri, Typografie, Farben, Theme, Tabellen, Dialoge und bestehenden CSS-/JS-Assets aus Draft #7 weiterverwenden. Laravel-Views sind später ein Transport-/Datenbindungswechsel, kein Starter-Kit. Keine verpflichtende SPA, Livewire oder Inertia. Kein ungeprüftes `include config.php` aus einer Laravel-View: es startet Sessions, erzeugt Schlüssel und führt Request-Migrationen aus.
3. **Standardinfrastruktur statt Eigenbau-Framework.** Laravel besitzt Routing, Middleware, Container, Konfiguration, Validierung, Auth/Policies, Exceptions, Logging und Migrationsverwaltung. Kleine protokollspezifische Adapter sind erlaubt; kein zweiter Router, generischer Auth-Unterbau oder eigenes ORM. Bestehende `attendance_transition[_locked]`, `booking_*`, `planned_minutes` und `report_*` bleiben fachliche Referenz, bis einzelne Extraktionen grob abgenommen sind. Keine zweite, ähnlich aussehende Buchungs-/Saldoformel.
4. **SQLite bleibt.** Eine Operation verwendet einen schreibenden PDO-Handle samt Audit-UDFs, Lock und Commit. Alle Kanaladapter rufen denselben synchronen Buchungsdienst auf; weder Queue noch ORM-Observer lösen eine zweite Buchung/ein zweites Audit aus. `BEGIN IMMEDIATE` ist ausdrücklich nicht durch einen gewöhnlichen `DB::transaction()`-Aufruf als bewiesen ersetzt.
5. **Keine Identitäts-/Zeitneuerfindung.** Bestehende IDs, Passwort-/PIN-/Karten-/Payloadhashes, JWT-/Verschlüsselungs-/Geräteschlüssel, Originalzeittexte und UTC-Epochwerte bleiben erhalten. Laravel `APP_KEY` ist ein neues Framework-Geheimnis und kein Ersatz für `jwt.key`, `encryption.key` oder die Terminalschlüssel. Kein Massenrehash, Reseed, Timestamp-Cast oder neue User-Standardtabelle.

## Identität, Sessions und unvermeidliche Reauth

Der Bestand nutzt native PHP-Sessions, `user_id`, `username`, `role`, `auth_version=sha256(password)`, `csrf_token`, `lang`, `theme_mode` sowie kurzlebige UI-Meldungen/Kopplungsdaten. Autorisierung und Passwortfingerprint werden pro Request aus der DB erneuert. Cookie: nativer PHP-Sessionname (nicht im Code umbenannt), HttpOnly, SameSite=Lax, Pfad `/`, Secure bei HTTPS/`APP_HTTPS=1`; ID-Regeneration beim Login. Die [Laravel-Sessionverwaltung](https://laravel.com/docs/13.x/session) ist **kein** Beleg kompatibler PHP-Sessionserialisierung/-IDs.

**Festlegung:** Foundation nutzt isolierten Framework-Session-/Cache-Namensraum, keinen Zugriff auf Legacy-Sessions. Keine automatische Sessionkopie, kein Unserialize fremder Cookies. Für den späteren ersten authentifizierten Web-Cutover wird **bewusste einmalige Reauthentifizierung** eingeplant. Login/Logout und die jeweils umgeschaltete Webroutenfamilie müssen dabei demselben Guard und CSRF-Vertrag gehören; sie dürfen nicht bei jedem Request zwischen zwei Login-Zielen pendeln. Während einer Übergangsphase dürfen zwei getrennte Cookies existieren, aber keine Middleware darf eines davon als Beweis für die andere Sitzung übernehmen. Ein verlustfreier Sessionimport wäre eine separat zu reviewende Alternative, nicht stiller Bestandteil dieser ADR.

Vor Reauth: unbestätigte Webereignisse nicht verwerfen oder mit neuer `event_id` automatisch erneut buchen. Draft #7 bindet Pendingdaten an numerische Nutzer-ID **und CSRF**. Nach CSRF-Wechsel/Accountwechsel keine automatische Wiedergabe unter neuer Identität; serverseitigen Status/ursprüngliche Ereignisbestätigung prüfen und dem Benutzer einen klaren Wiederanmelde-/Klärungsweg geben. Antwortverlust bleibt #57. Auth-Cutover muss diesen Ablauf gemeinsam abnehmen, bevor Web-Schreibrouten wechseln.

CSRF-Kompatibilität: Formfeld `csrf_token`, Header `X-CSRF-Token`, JSON-Fehler-/Reloadverhalten explizit adaptieren; Laravels `_token`/419-Defaults nicht unbemerkt ausrollen. Sessionpfade bleiben geschützt; nur bereits cookielose Bearer-/Terminalfamilien sind eigene Middlewaregruppen. Keine globale CSRF-Ausnahme.

JWT bleibt bis eigener API-Abnahme HS256, `typ=JWT`, `iss=aud=localhost`, ganzzahlige `iat`, `exp`, `user_id`, `ver=sha256(users.password)`, eine Stunde Gültigkeit, Prüfung gegen aktuelle DB und vorhandenes `jwt.key`. Admin-Tokenerzeugung ist ein Token des Admin selbst, kein Team-/Globaltoken. `users.token` ist keine alternative Whitelist: der Validator liest den Passwortfingerprint. **Sanctum/Passport sind keine automatisch kompatiblen Ersetzungen.**

## Gemeinsamer Buchungs-/Audit-/Verbindungsvertrag

Geplante, fachlich begrenzte Schnittstellen (noch nicht implementierte Klassen):

- `AttendanceService::transition(actor, source, eventId, action, confirm, expectedRecord?)`: eine synchrone Operation; vorhandene Zustandsmaschine, Antwort und Serverzeit, keine Backgroundqueue. Quellnamensräume bleiben `web:{user}`, `api:{user}`, `terminal:{device}`. Gleiches Ereignis/Gleiches Payload liefert gespeicherte Antwort; geändertes Payload unter gleicher ID wird 409. Hashreihenfolge/JSON-Serialisierung nicht ändern.
- `ImmediateTransaction::run(connection, auditContext, operation)`: genau eine Lock-/Retry-Grenze. Der Terminalpfad besitzt bereits eine Transaktion und ruft daher die **locked**-Variante auf; kein verschachteltes `BEGIN`. Commit beinhaltet Originalintervalle, Projektion, Ereignisantwort, Audit und Terminalgrantverbrauch. Retry darf keine neue Ereignis-ID erzeugen. Konkurrenz-/Busy-/Reconnectnachweis ist FW-04, kein hier bestandener Test.
- `AuditContext::run(actor, reason, callback)`: Context gilt nur für diese Operation und wird auch bei Fehler in `finally` zurückgesetzt. `audit_actor()`/`audit_reason()` auf demselben echten Writer-PDO vor jedem DDL/Write registrieren, ebenfalls nach Reconnect. Keine Credentials loggen, keine ORM-Auditevents zusätzlich zu DB-Triggern. Foundation startet noch keinen Legacy-Writer; FW-03 besitzt den späteren Connector.
- Routenfamilien einzeln per geprüftem Routingmanifest umschaltbar. Ein Request hat **einen** Eigentümer. Kein Dualwrite, kein paralleler Legacy-/Laravelbootstrap, kein ungeprüfter HTTP-Forwarder als dauerhafter Fachadapter. Rückschaltung setzt schema- und authkompatiblen Code voraus.

## Paket-/Dateiverantwortung und Abhängigkeiten

| Paket | Exklusiver Bereich / Ergebnis | Abhängigkeit und Freigabegrenze |
|---|---|---|
| FW-01 #71 | `docs/framework/`: ADR, Route-/Abnahmematrix | Vor jedem Auth-/Route-/DB-Cutover verbindlich; keine Laufzeitänderung. |
| FW-02 #73 | Initial ausschließlich `framework/`; offizieller isolierter Boot, Composer-Lock, sichere Defaults | Parallel zur Aufnahme; keine Legacy-Schreibrouten oder Datenanbindung. `database/` nur nach expliziter Übergabe weitergeben. |
| FW-03 #74 | Zuerst Schema/Mapping; danach `framework/database/` und abgegrenzter DB-Provider/Connector | FW-01 reviewed + FW-02 übergeben. Vorprüfung vor **jeglichem** Schreibzugriff einschließlich Migrationstracking; keine Request-Migration. |
| FW-04 #75 | SQLite-Immediate-/Konkurrenz-/Idempotenz-PoC und Transaktionsadapter | FW-03/Writer-PDO, danach erst Schreibfreigabe. Kein Auth-/UI-Neubau. |
| Auth/API #76/#77 | Bestehender Session-/CSRF-/JWT-/Policyvertrag als Laravel-Middleware/Guard/Controller | FW-01, Datenvertrag und abgestimmte Login-/Reauthfamilie; #25 besitzt API-Vertragsfehler. |
| Domain/UI #78/#79 | Buchungs-/Korrektur-/Modell-/Reportadapter und vorhandene Views | Daten/Auth + FW-04 für Writes. UI-Dateien/Assets nur mit eigenem abgegrenztem Auftrag bearbeiten. |
| Integrationen #80/#81 | Erweiterungs-/Terminal-/LDAPadapter | Jeweiliger Protokoll-/Scopevertrag; reale Geräte/Directory-Abnahme separat. |
| Cutover/Abbau #82 | Routingmanifest, Sicherung, Rollback, später Entfernen abgelöster Infrastruktur | Alle betroffenen Gates, passende Laufzeit und ausdrückliche Freigabe; keine automatische Folge dieses Dokuments. |

## Paketauswahl

| Bedarf | Entscheidung jetzt | Gate vor späterer Auswahl |
|---|---|---|
| Routing, Sessions, Policies, Validierung, Logging, DB/Views | Laravel-Standard; Dateisessions/-cache isoliert für Foundation | Konkrete Lock-/PHP8.5-/Extensionprüfung, Fehler-/CSRF-Mapping. |
| JWT HS256 | Vorhandenen Wirevertrag erhalten, noch kein Zusatzpaket festgelegt | Wartung, Laravel13/PHP8.5, Lizenz, Algorithmus-/Claim-/Revocationkompatibilität, keine Tokenneuausstellung als Migration. |
| LDAP | Vorhandene TLS-/Bind-/Attributannahmen inventarisiert; kein Paket vorsorglich installiert | Wartung/Version/Lizenz und tatsächlicher Directorypilot, Recovery-/Syncvertrag. |
| Queue, Redis, Horizon, Scheduler | Nicht hinzufügen | Nur konkreter bestätigter asynchroner Bedarf; Stempeln/Audit bleiben synchron. |

## Offene Go/No-Go-Gates

1. **DB:** vollständige Schema-/Beziehungsaufnahme vor Änderung, sichere leere/Altbestand-Übernahme zweimal, keine doppelten Baselines, Zeitwerte/IDs unverändert; [Schema](schema.md).
2. **SQLite:** effektive FK-/Journal-/Busy-Einstellungen ermitteln und bewusst übernehmen; Laravel aktiviert standardmäßig FKs. Konkurrenz/Antwortverlust/rollback/reconnect mit demselben PDO und wirksamen Audittriggern nachweisen.
3. **Auth:** native/Laravel-Sessiongrenze, Reauth/Pendingevent, CSRF, Rollenänderung/Passwortfingerprint und Teamfilter grob abnehmen. LDAP-Single-Session-Effekt nicht unbemerkt verändern.
4. **Protokolle:** echte Status-/Content-Type-/JSON-/CSV-Verträge aus [Matrix](compatibility.md) erhalten. Bekannte fehlerhafte Altverträge nur über zuständige Issues ändern, nicht als Frameworkdefault.
5. **UI:** derselbe vorhandene Seitenrahmen und Assets; zentrale Bedienabläufe ohne Neugestaltung. Keine neue Funktion, kein unfreigegebener Host-/Securitywechsel.
6. **Rollback:** vollständige private Ablage und dazugehöriger Code/Lock/Schema; nach Cutover entstandene Buchungen/Audit/Schlüssel dürfen bei Rückschaltung nicht verschwinden. Kein blindes destruktives `down()`.

## Wenige grobe Abnahmen statt Mikrotests

Diese Matrix ist ein **Plan**, keine Aussage bestandener Tests. Ausführung erst in freigegebener isolierter Zielumgebung, nicht gegen bestehende lokale Betriebs-/Testdaten; externe Geräte/LDAP nur mit autorisiertem Pilot.

| Grober Fall | Entscheidender Beleg / Abbruchkriterium |
|---|---|
| Boot und Übernahme | Laravel/PHP8.5 startet isoliert; leerer und repräsentativer synthetischer Altbestand zweimal; Daten-/ID-/Audit-/Index-/Triggervergleich, unbekanntes Schema stoppt vor Write. |
| Login und Rechte | Lokale Anmeldung, einmalige Reauth, CSRF, Rollen-/Passwortänderung, eigene vs. direkte Teamdaten; keine wiederholte Login-Pendelschleife, keine Account-übergreifende Pendingwiedergabe. |
| Ein kompletter Arbeitstag | Kommen–Pause–Fortsetzen–Gehen inkl. Bestätigungsabbruch, identischer Retry und konkurrierender Start; genau eine offene Sitzung/Pause, gespeicherte Antwort, atomarer Audit. |
| Korrektur und Historie | Eigener Antrag, zweite berechtigte Person, Storno/Finalität, alte Intervalle und Audit bleiben; keine Selbstfreigabe/zweite Entscheidung. |
| Anzeigen, Modell, Abwesenheit, CSV | Ein Wochenmodell samt Feiertag, gespeicherte Sollgutschrift und repräsentative DST-/Altzeitwerte unverändert; gleiche zentrale Anzeige/Export; bekannte Abweichung ausdrücklich separat kennzeichnen. |
| Kanal und Rückschaltung | Ein repräsentativer Bearer-/Erweiterungsablauf und Terminalprotokoll, LDAP wenn verfügbar; Code-/Datenrücknahme mit nach Cutover gebuchten Ereignissen ohne Verlust. Nicht ausgeführte Hardware-/Directoryteile als offen ausweisen. |

## Bekannte Abweichungen bleiben eigene Arbeit

Assessment-Core: UTC-Datumsfilter der API, paginierte Tagessummen, HH:MM-Diagrammwerte, begrenzte Kalenderhistorie; statische Risiken alter Tabs/fehlender erwarteter Record-ID sowie Arbeit über spätere Abwesenheit hinweg. Integrationsinventar: Extension erwartet alte Antwortfelder und besitzt keine stabile `event_id`, Plaintext-Fehler trotz JSON-Annahme, LDAP-Sync/Recovery und wechselnder LDAP-Passworthash, unvollständige Admin-Auditfelder, FKs nicht explizit aktiviert. Das sind keine durch diese ADR behobenen Fehler und keine pauschal bewiesenen Laufzeitausfälle. Draft #7 verbessert Web-Pendingbindung und UI, nicht sämtliche Mehrtab-/Supervisor-/Antwortverlustfälle.

#25 (API), #55 (Datenqualität), #57 (Antwortverlust), #58 (PHP8.5) behalten ihre Zuständigkeit. Feiertagsversorgung, Urlaubskonto/Genehmigung und vollständiges Vertragszeitkonto werden nicht als Nebenwirkung der Frameworkmigration erfunden.
