# FW-10/#81: expliziter, standardmäßig geschlossener Terminal-HTTPingress

Basis: Main `a4292a950b0ed72cffd8c6d35bcb6770bd666470`, Tree
`567e5f15c851441a18125ca1bf404c52673caeda`, 27.10.2026.
[FW-10/#81](https://github.com/storminator89/Zeiterfassung/issues/81) bleibt offen.
Dieses Paket verbindet den vorhandenen konkreten Backendpfad mit Laravels
Request-/Kernel-/Router-/Responselebensdauer. **Keine Aktivierung, Schlüsselbindung,
Host-/SAPIänderung oder veröffentlichte Ende-zu-Ende-Abnahme.**

## Genau sieben Dateien

- `public/index.php`: nur für den exakten rohen Terminalpfad direkte Rawcapture;
  die bisherige gewöhnliche Zeile `$app->handleRequest(Request::capture());`
  bleibt unverändert. Der bestehende Maintenance-Prerenderblock bleibt gleich.
- `bootstrap/app.php`: eine vorgeschaltete terminal-spezifische Middleware;
  keine globale Middlewareliste wird ersetzt/entfernt und kein Web-/APIguard geändert.
- `config/chrono_terminal.php`: ausschließlich `http_enabled=false` als Default;
  `FRAMEWORK_CHRONO_TERMINAL_HTTP_ENABLED` ist vertrauenswürdige Deploykonfiguration,
  kein Requestfeld. Nur ein echter Boolean `true` erlaubt den Pfad; truthy Texte,
  Zahlen oder Header erlauben ihn nicht.
- `Terminal/LegacyTerminalHttpIngress.php`: konkreter Rawcapture-/Middleware-/
  Routeadapter. Er verwendet die vorhandene Terminalcontroller-/Backendkette,
  nicht ein zweites Protokoll oder Ersatzidentitäten.
- `routes/terminal.php`: genau `POST /terminal_api.php`, ohne `web`-/`api`gruppe,
  Session-, CSRF-, Bearer-, CORS- oder zusätzliche Cache-/Ratelimitmiddleware.
- `ChronoDomainServiceProvider.php`: lädt diese Route mit Laravels normalem
  `loadRoutesFrom()` nur bei explizitem `true`; keine Schlüssel-/Writerauflösung.
- Diese Dokumentation.

Native Dateien, gemeinsamer Terminalkern, bestehender Transportcontroller und
-kapseln, Backend/Writer/Audit, gewöhnliche Routen, Auth, UI und sonstige
Konfiguration bleiben unverändert. Es wird keine echte Rootquelle bereitgestellt.

## Warum Capture vor der normalen JSONcapture liegen muss

Die gelockten Quellen sind Laravel **13.38.0**, Symfony HttpFoundation **8.1.9**:

- `Illuminate/Http/Request.php:78–82,543–558` ruft bei normaler Capture
  `createFromBase()` auf und bei JSON bereits dort `json()->all()`.
  `Request.php:466–472` liest dafür unbeschränkt den Inhalt.
- `Symfony/Component/HttpFoundation/Request.php:338–351` kann schon bei
  `createFromGlobals()` für PUT/DELETE/PATCH/QUERY `request_parse_body()` ausführen.
- `Foundation/Configuration/Middleware.php:451–466` enthält vor dem Controller
  unter anderem CORS, Postgrößenprüfung und JSONtransformationen.
- `Foundation/Http/Kernel.php:137–145` erlaubt Methodenoverrides; Router/Symfony
  bereiten danach die Antwort vor (`Routing/Router.php:914–960`,
  `HttpFoundation/Response.php:250–291`).

Die vorhandene Transportdokumentation hat diese vorgelagerte Grenze bereits als
offen bezeichnet; der Backendpfad war dadurch nicht als öffentlich abgenommen
behauptet. Der neue Capturezweig verwendet den **öffentlichen Stockconstructor**
`new Illuminate\Http\Request([], [], [], [], [], $originalServer)` mit deferred
Content, ohne `capture()`/`createFromGlobals()`/`createFromBase()` auf diesem Pfad.
Er liest keinen Body und erstellt keinen JSONbag. Query/Form/Cookie/Filebags bleiben
leer: Der native Terminalendpunkt verwendet diese nicht. Die originalen Serverwerte
und Bodybytes werden nicht umgeschrieben oder neu kodiert.

Eine private `WeakMap` merkt die konkrete, von diesem Quellpfad erzeugte
Requestinstanz. Kein Header, Query-/Body-/Server- oder Attributfeld kann diese
Objektidentität markieren. Nur der Routing-Headerbag verliert die fremde
`X-HTTP-Method-Override`-Steuerung; der ursprüngliche Serverbag bleibt erhalten.
Die leeren Form-/Querybags liefern auch kein `_method`. Gewöhnliche URLs benutzen
weiterhin exakt die bisherige Capture und unveränderte Middlewarekette.

## Kanonischer Pfad, Opt-in und Fehlerreihenfolge

Nur der rohe Pfadteil vor dem **ersten literalen `?`** darf exakt
`/terminal_api.php` sein. Eine Query bleibt im originalen `REQUEST_URI` erhalten,
beeinflusst aber keine Eingabedisposition oder Admission. Keine Prozentdekodierung,
Slash-/Case-Normalisierung, Aliasannahme oder Weiterleitung. PATH_INFO, abschließender
Slash, Doppel-/kodierte Slashes, Subpfade und absolute-form URIs werden hier nicht
als weiterer Terminaleingang freigegeben. Das native Hostingverhalten solcher
Varianten bleibt eine explizite Cutoverfrage, nicht eine still erlaubte Route.

Bei geschlossenem Opt-in liefert der exakte Pfad 404 **vor** Backend-/Writer-/
Schlüsselauflösung. Der invokable Routeadapter prüft Boolean, exakten Pfad und
Quellmarker erneut. Auch eine alte gecachte Route bleibt damit bei `false`
geschlossen; ein fehlender Rawmarker oder technische Admission liefert generisch
unbestätigte 503 statt eines Native-/Keyfallbacks. Ungültige Flagtypen erlauben
keine Route. Normale Laravel-Config-/Routecachepflege bleibt ein eigener
Deployschritt; ein veralteter Cache wird nicht automatisch verändert.

Bei zugelassenem Ingress gelangen rohe Nicht-POSTmethoden direkt zum **bestehenden**
Controller, bevor Laravel sie als Routeverb oder CORS-OPTIONS abfangen könnte.
Nach dem unten beschriebenen Stock-Maintenancegate prüft dieser wie bisher
zuerst das Modul, dann exakt rohes `POST`, deklarierte
Größe, höchstens 8193 gelesene Bytes, JSON und den Backendpfad. So bleibt bei
abgeschaltetem Modul 404 vor 405/413 erhalten, ohne eine zweite Kopie dieser
Validierungsregeln. POST benutzt die normale einzelne Laravelroute. Es gibt
weder Weblogin noch Session-/CSRF-/Bearerersatz für die Terminal-HMACanmeldung.

Antworten benutzen die gepflegte Router-/Symfonyvorbereitung. Ein echtes rohes
HEAD behält den nativen Modul-/405status; die HTTP-HEADvorbereitung unterdrückt
den Antwortbody. Ein rohes POST wird durch fremde `_method`-/Overridefelder nicht
zu HEAD und verliert seinen JSONbody nicht. Technische Backend-Auflösungs-/Routingfehler
werden ohne sensible Details in die bestehende unbestätigte JSON-503form übersetzt.
Stock-Maintenanceresponses/-ausnahmen liegen ausdrücklich außerhalb dieser
Protokollfehlerübersetzung.
Kein Retry und keine Erfolgs-/Rollbackbehauptung. Native `AttendanceError`-Status
und Meldungen bleiben erhalten.

Nur auf Protokollantworten dieses zugelassenen Terminalpfads werden die vorhandenen
nativen Securityheader ergänzt: nosniff, DENY, Referrer same-origin, vorhandene CSP und
no-store; HSTS nur nach dem bisherigen rohen HTTPS-/`APP_HTTPS`-Kriterium.
Keine CORSfreigabe, Cookies oder globale Header-/Host-/Proxyänderung. Symfony darf
Header kanonisieren, beispielsweise `private` zu `no-store` sowie Date ergänzen;
keine byteidentische Headerreihenfolge wird behauptet. REMOTE_ADDR bleibt der rohe
Peerwert, nicht ein aus Forwarded/X-Forwarded-* abgeleiteter Ratelimitbucket.

## Bewusst enge Betriebsgrenze und verbleibende Gates

Der Ingress ist eine **eigene native-public Terminalpipeline**, nicht ein
Catch-all-Ersatz für gewöhnliche globale oder Web-/APIpolicies. Nur der exakte,
explizit zugelassene Terminalpfad wird vor JSONtransforms, Postsize- und CORS-
Interception behandelt. Alle anderen URLs gehen unverändert an `$next`.
Es werden weder bestehende Guards anderer URLs noch Hosteinstellungen verändert.

**Dynamische Laravelmaintenance wird nicht umgangen:** Nach der HTTPadmission und
vor jeglichem Terminaldispatch führt der Ingress die unveränderte gepflegte
`PreventRequestsDuringMaintenance`-Middleware aus. Sie ist eine operative äußere
Grenze und darf auch vor Modul-404, Methoden-405 oder Body-413 antworten. Ihre
Status-/Retry-After-/Refreshheader, Templates, Redirects, Ausnahmebehandlung und
bestehenden zulässigen Ausnahmen bleiben der Stockimplementierung überlassen;
sie werden nicht in die native unbestätigte Protokoll-503form umgeschrieben.
Rawcapture hat absichtlich einen leeren Cookieeingangsbag; vollständige
Maintenance-Cookieparität wird nicht behauptet. Die Stock-Secret-Bypassantwort
kann ihr eigenes Maintenancecookie setzen; die Aussagen zu keinen Cookies und
nativen Securityheadern betreffen nur Protokollantworten, nicht diese operative
Stockantwort. Die separat vor Laravel
laufende Maintenance-Prerenderdatei in `public/index.php` bleibt ebenfalls gleich.

Das ist eine ausdrücklich dokumentierte Abweichung vom nativen PHPendpunkt,
der Laravels Maintenancezustand nicht kennt. Es wird keine Maintenanceausnahme
registriert und kein Downzustand/Datei/Hostsetting verändert. Nur außerhalb
Maintenance gilt die native Modul-vor-Methode-/Bodyreihenfolge. Andere vorgelagerte
SAPI-/Webserver-/Proxyablehnungen oder Buffer sind ebenfalls keine nachträglich
behebbare Controllergrenze. PHP kann insbesondere Form-/Multipartdaten bereits
vor Userland verarbeiten. Keine Änderung von `enable_post_data_reading`,
`post_max_size`, Bodylimits, Rewrite, Documentroot, Proxytrust oder Maintenance-
Betriebspolitik ist enthalten. Klassische einzelne synchrone Requests sind die
geplante Grenze; langlebige Worker/Octane und deren statische Request-/Proxyzustände
sind nicht freigegeben.

Ein vorhandenes natives `terminal_api.php` kann beim aktuellen Hostmapping
weiterhin vor Laravels Frontcontroller gewinnen. Die neue Route ist deshalb
keine Behauptung, dass der veröffentlichte URL bereits Laravel erreicht.
Echte getrennte bestehende Rootquellenzulassung/-bindung, Datenbankadmission,
Host-/URL-/Maintenance-/Cachemapping, Rawinput-/Header-/HEADparität am tatsächlichen
SAPI sowie veröffentlichte Agent-/Hardwareabnahme bleiben vor Aktivierung offen.
V1 signiert weiterhin ID, Zeit, Nonce und Rawbody, **nicht** Methode oder Pfad.

## Tatsächlich begrenzter Nachweis

Nur PHPsyntax, Quell-/Unverändertheitskontrolle und eine grobe isolierte
In-Process-Laravel-Kernel/Routerreise mit künstlichem Speicherprotokoll und
synthetischem Rawstream, ergänzt um ein speicherinternes Maintenance-Modeobjekt
mit der echten Stock-Middleware (kein echtes Downfile/-setting). Sie ist kein Live-HTTP-, HMAC-, Writer-, Keyprovenienz-,
Host-, SAPI- oder Gerätebeweis. Keine echten Secrets, Credentials, Accounts,
Geräte, Pairing, Login, Live-DB, Browser, Server, Netzwerk, CIanforderung oder
Bereitstellung; kein tatsächlicher Opt-in oder echte Quellenbindung.
