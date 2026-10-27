# FW-06 / #77 – inaktiver Login- und direkter API-Stempelkandidat

Dieses Folgepaket ergänzt ausschließlich den **weiterhin unregistrierten**
`LegacyApiRoutes::registerCandidate()` um `POST /api.php/login` und
`POST /api.php/attendance`. Kein produktiver Routenschalter: `api.php`,
Altissuer/-verifier, `security.php`, Schlüssel, Konten, DB-/Hash-/Hostkonfiguration,
Web-/Terminaldienste und UI bleiben unverändert. Ausgeliefert bleibt nur `/up`.
Die Aliasrouten `/workentry` und `/setendzeit` sind weiterhin ausdrücklich
unadmitted (503 im Kandidaten), #77 bleibt offen.

## Lokale Credentials mit Laravel statt zweitem Passwortsystem

`LegacyApiLoginController` liest den rohen JSONbody. Wie im Bestand werden nur
ein Array und Zeichenkettenfelder `username,password` verlangt. Keine neue
`required`-/Trim-/Nullnormalisierung: auch leere Strings gehen unverändert in
den bisherigen Login-/Versuchsvertrag, fremde Felder werden ignoriert. Ungültige
Form bleibt Text-400 `Username and password required.`.

`LegacyApiLogin` nutzt den vorhandenen Laravel-`chrono_users`-Eloquentprovider
und dessen stock Passwortprüfung. Abgerufen wird der **exakte**, ungetrimmte
Username; `auth_source` muss `local` sein, `defaultpassword` wird ausdrücklich
verweigert. Es gibt **keine Rollenallowlist**, keine LDAPbindung/-fallback und
keine lokale Websession als Ersatz. Die bereits vorhandene, unveränderte
`config/hashing.php` setzt `bcrypt.verify=false` und `rehash_on_login=false`:
PHP-`password_verify`-kompatible vorhandene Hashalgorithmen werden nicht auf
einen neuen Algorithmus beschränkt. Der Adapter ruft nur Providerprüfung,
niemals Sessionlogin, Remember, Rehash oder Accountsave auf. Ungültige
Credentials bleiben JSON-401 `{success:false,message:'Invalid credentials'}`.

Ausgabe erfolgt ausschließlich mit dem bereits gelockten gepflegten JWT-Paket:
HS256, `typ=JWT`, `iss=aud=localhost`, ganzzahlige `iat=now`, `exp=now+3600`,
`user_id` und `ver=sha256(current password hash)`. Ergebnis bleibt
`{success:true,token}`. Die Paketheaderreihenfolge ist kein stabiler Tokenbyte-
vertrag; die Ausgabe wird vom vorhandenen produktiven Verifier akzeptiert.
`AdmittedApiSigningKey` vereinigt lediglich die vorherige unveränderte
Admissionprüfung für Guard und Issuer. `LegacyApiTokenKey` bleibt ungebunden:
kein Loader, Keygen, `APP_KEY`-Fallback, Hexdekodieren oder Schlüsselwechsel.

## Loginbudget: echte Sperre, aber keine heimliche Speicheraktivierung

`LegacyApiLoginBudget` hat **keine ausgelieferte Standardbindung**. Login
verweigert ohne ausdrücklich zugelassenen Budgetdienst und benannte
`chrono`-Verbindung. `LaravelApiLoginBudget` verwendet Laravels vorhandenen
`RateLimiter`, nicht einen eigenen Zähler-/Zeitfenstermechanismus:

- 60 Versuche je rohem `REMOTE_ADDR`, 15 je PHP-`strtolower(username)`, 600s;
  bestehende SHA-256-Bucketnamen, keine neue Cookie-/Requestidentität und kein
  Trimmen des Usernamens.
- Erfolgreiche und fehlgeschlagene Versuche verbrauchen Budget; kein Reset bei
  Login. Erst IP-, dann Nutzerprüfung wie im bestehenden Ablauf.
- Überschreitung bleibt Text-429 mit `Retry-After: 600`; keine neuen
  X-RateLimit-Erfolgsheader oder veränderte Credential-Fehlerform.

**Aktivierungsblocker:** Der aktuelle produktive Altcode zählt weiterhin in
SQLite-`login_attempts`, unverändert und ohne Reset/Migration. Die synthetische
Abnahme bindet einen einzigen ArrayStore über den ganzen Ablauf. Das beweist
keine geteilten persistenten Zähler oder bytegleiche Ablauf-/Aufräumgrenzen
gegenüber der alten Tabelle. Vor jeder echten APIaktivierung muss ein
zugelassener persistenter Budgetadapter die verbleibenden Web-/API-Loginpfade
und alten Zähler ausdrücklich zusammenführen beziehungsweise einen sicheren
vollständigen Übergang nachweisen. Eine getrennte leere Cacheinstanz würde
Budgets aufteilen und ist **keine** erlaubte Aktivierung. Kein Defaultfilecache,
DB-Cachetable, neues persistentes Backend oder produktiver Counterzugriff wird
in diesem Paket eingerichtet.

## Ein direkter APIpfad auf demselben synchronen Writer

Zwingend: `LegacyApiWire` → `RequireLegacyApiToken` → Controller →
`ApiAttendance` → ausdrücklich zugelassener frischer `AttendanceWriter` (#75).
Der Requestguard prüft für jeden Request JWT, aktuelles Konto und aktuellen
Passwortfingerabdruck, bevor ein Writer aufgelöst wird. Websessions, Payload-
`user_id`, Rollen oder ein frei gesendetes `source` sind keine Akteurquelle.

`LegacyApiAttendance` übernimmt ausschließlich diese Guardidentität und bildet
den **bestehenden** servereigenen Namensraum `api:{id}`. Gültige bestehende
LDAP-/Legacyrollentokens bleiben wie in der alten Bearer-API verwendbar;
kein lokaler Webdienst und keine neue Rollen-/Authquellenallowlist. Die zwei
kleinen Wrapperklassen stammen unverändert aus dem getrennt vorbereiteten
Domainartefakt; Provider/Transport bilden hier die gemeinsame Reviewgrenze.

Der Controller delegiert nur `event_id`, `action` und
`confirm_end_pause === true` aus rohem JSON. Keine neue Bool-Koerzierung,
422-Validierung, Clientzeit oder manuelle historische Buchung.
**`expectedRecord` bleibt beim direkten Endpoint weggelassen** wie im Bestand;
[#36](https://github.com/storminator89/Zeiterfassung/issues/36) wird nicht als
Nebenwirkung behoben oder durch ein Requestfeld umgangen. Eventvalidierung,
Lock, eine Transaktion, Account-exists unter Lock, Projektion, Audit,
Idempotenz/Payloadkonflikt und gespeicherte Retryantwort bleiben ausschließlich
im bestehenden #75-/Legacy-Domänenpfad. Kein zweiter PDO, Retry oder
Transaktionshandler wird implementiert. Die Authprüfung vor dem Lock ist keine
neue atomare Passwortwiderrufsprüfung unter Writerlock.

`AttendanceError` behält Status und JSON-`success,message`; technische Fehler
bleiben 503 mit der vorhandenen unbestätigten Buchungsmeldung. Erfolgreiche
Antwort-/Replayfelder und JSONbytes werden nicht neu zusammengesetzt.
Die Kandidatrouten verwenden Laravelpfadmatching für die erfassten Verben;
die Wiremiddleware prüft allein den tatsächlichen Rawmethod-/Rawpfadvertrag.
Methodoverrideheader/-parameter werden nur in dieser Familie entfernt, damit
ein echter POST weder als anderer Request noch bei Antwortvorbereitung als
HEAD behandelt wird. Keine globale Webmiddleware oder erlaubte Methode wird
nebenbei geändert.

## Ein grober zusammenhängender Nachweis

```sh
cd framework
php tools/rehearse-api-attendance.php
```

Ein parameterloser Ablauf mit frischen synthetischen gestoppten V2-Dateikopien,
verifiziertem Backup/Adoption und bereits vorhandenen DB-/Audit-/Writerhelfern;
Wegwerfsignierschlüssel nur im Prozess und ausdrücklich gebundenem Arraybudget.
Echte Laravel-HttpKernel-Requestobjekte, kein HTTPserver oder Port 18083.
Vier Gruppen: inaktive Admissions/private Fixture; roher lokaler Login ohne
Hash-/Accountänderung und kompatibler Token/eigene Leser; `api:{id}`-Start,
bytegleiches Replay, Pause/strikt bestätigtes Ende; anonymer und widerrufener
Retry vor Writerauflösung sowie 429 des zugelassenen Budgets. Die ursprüngliche
Quelle und das Backup bleiben unverändert; keine reale Datei oder Zugangsdaten.

Die vorhandenen groben Tokenprofil-/API-Wire-/Read-/Webprüfungen werden
wiederverwendet, keine Mikrotests oder neue Vollsuite. Noch offen: persistentes
geteiltes Loginbudget, APIaliase/Erweiterungskorrekturen #22/#23/#25,
`expectedRecord`-Entscheidung, `nbf`-Profilentscheidung, Swagger, echte
Browsererweiterungs-/Verteilungsabnahme und separat reviewter Gesamtcutover.
