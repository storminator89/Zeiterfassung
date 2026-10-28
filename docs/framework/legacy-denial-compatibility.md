# Laravel: kompatible Sperrantworten für delete.php und import.php

Dieses enge Teilpaket zu #79/#82 übernimmt zwei **bereits gesperrte** native
Einstiege in den bestehenden ausdrücklich aufgerufenen `LocalWebRoutes`-Stack.
Es aktiviert weder Standardrouten noch Löschung, Teilrestore, Uploadverarbeitung,
Writer oder Dateizugriff. Basis ist tatsächlicher Main
`37b5b4d6dd2178f55249c38819226b14f4a5f06e`, Baum
`17f22e4d0f2f1d91e03e81d397c066387f1f5294`.

| Einstieg | Bestätigte Identität | Antwort |
| --- | --- | --- |
| `delete.php` | angemeldeter aktueller Benutzer | 409, nativer Korrekturantrag-Hinweis |
| `delete.php` | keine gültige Anmeldung | 401, `Nicht angemeldet.` |
| `import.php` | aktueller lokaler Admin | 409, nativer Offline-Vollrestore-Hinweis |
| `import.php` | anonym oder kein lokaler Admin | 403, `Administrator access required.` |

Die Texte sind die vorhandenen nativen Antworten, UTF-8-Text statt JSON oder
Login-HTML. `no-store` und `nosniff` verhindern unnötiges Caching/Content-Sniffing.
Ein LDAP-Admin wird nicht neu eingeführt; bestehende LDAPkonten sind ausschließlich
gewöhnliche Benutzer. Die Controller lesen weder IDs/Formaktionen noch Dateien,
Uploads oder Datenbankzeilen und lösen keinen Operationswriter auf. Der normale
Session-/Authstack kann unabhängig davon seine bestehenden Reads/Sessionwrites
ausführen; es wird keine vollständige Nebenwirkungsfreiheit des HTTPstacks behauptet.

## Methoden, Auth und CSRF

Native Dateien besitzen keinen eigenen Methodenguard. Deshalb liefern die neuen
Routen für alle von Laravel unterstützten Routermethoden sowie die zusätzlichen
Standardmethoden TRACE/CONNECT dieselbe Sperrantwort, statt für diese Methoden
still neue 405 einzuführen. Unbekannte nichtstandardisierte Methoden und
vorgelagerte Webserver-Methodensperren sind keine neu zugesagte Kompatibilität. Das ist **keine** Freigabe einer
Mutation. Browsermethoden GET/HEAD/OPTIONS behalten den vorhandenen sicheren
Methodenvertrag, unsichere Methoden durchlaufen unverändert `LegacyFormCsrf`.
Fehlender/falscher CSRFtoken liefert weiterhin 403 **vor** Authentifizierung bzw.
Adminprüfung. Die beiden festen Pfade sind in der bestehenden Raw-Form-Ausnahme,
damit insbesondere CSRFfelder nicht durch globales Trimmen/Leernullung verändert
werden. Keine CSRFausnahme oder Same-Origin-Abkürzung.

Die Routen liegen im vorhandenen verschlüsselten Session-/CSRF-/Bindingsstack
und im selben `RefreshLocalAccount` → `auth:web` → `auth.session`-Bereich wie die
bestehenden geschützten Nutzerwege. Aktuelle DBidentität/Rolle/Quelle und
Passwortwiderruf bleiben damit vor der Controllerantwort maßgeblich. Auch die
routeabhängige Behandlung einer AuthenticationException erhält die getrennten
401-/403-Textverträge. Die zusätzliche direkte Controlleridentitätsprüfung ist
kein Ersatz für diese zwingende Middleware und behauptet keinen eigenen
DB-Snapshot. Nur lokale aktuelle Administration erhält den Restorehinweis.

## Abgrenzung und Integration

- Kein direkter CRUD-, Storno-, Import-/Restore- oder Dateiuploadweg; bestehende
  Korrektur-/Vier-Augen-/Audit- und Offline-Vollrestoregrenzen bleiben erhalten.
- `get_latest_record.php` ist dagegen ein echter eigener HTML-Leseweg und wird
  nicht durch eine pauschale Sperrantwort verfälscht; dessen Portierung bleibt offen.
- `LocalWebHomeController`, `save.php`, der gesondert gehaltene #197-Delta,
  Reader/Writer, native Dateien, Admin-/LDAP-/Fresh-Quellen bleiben unverändert.
- Nur additive `LocalWebRoutes`-Hunks: Import, Raw-Formpfade, Authfehlerabbildung,
  zwei geschützte Routes. Spätere LDAP-/Fresh-/Admin-Union separat prüfen;
  insbesondere bestehende Opt-ins, Limiter, CSRF und Raw-Adminfelder bewahren.
- Keine tatsächliche Profil-/Runtime-/Host-/Cutoveraktivierung. Die gewöhnlichen
  Standardrouten bleiben ohne ausdrücklichen Registraraufruf unverändert.

## Begrenzter Nachweis

Ein grober synthetischer Controller-/Routerablauf prüft anonyme/gewöhnliche/
lokale Admin-/LDAPidentitäten, feste native Text-/Status-/Headerverträge,
verschiedene Methoden und unveränderte Middlewarezuordnung. Notwendige PHPsyntax
und Diffhygiene sind geprüft. Keine neue Testsuite/CI.

Beim zusätzlichen TRACE-/CONNECT-Nachweis legt Symfonys synthetischer Request-
Builder übergebene Parameter zunächst in die Query statt den POSTbag. Diese
Fixtureannahme wurde sichtbar abgewiesen und belegt aufbewahrt; der Nachweis
nutzt dort anschließend den auch nativ vorgesehenen CSRFheader. Anwendung und
Assertions wurden dafür nicht abgeschwächt; die regulären Raw-Body-Fälle bleiben
zusätzlich erhalten. Das ist keine tatsächliche Webserver-Methodenabnahme.

Das ersetzt keine reale HTTP-/Session-/Login-/Passwortwiderrufs-/CSRF-/Browser-
oder Produktivabnahme. Keine echten Konten, Credentials, Schlüssel, DBs,
Imports, Löschungen oder GitHubaktionen; #79/#82/#65 bleiben ausdrücklich offen.
