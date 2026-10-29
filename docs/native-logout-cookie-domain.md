# #62 — vorhandene Cookie-Domain beim nativen Abmelden bewahren

Enger Kompatibilitätsfix auf Main
`2aded277a1be1b3275b115c1f990232686cec0f2`, Basisbaum
`c6ecdc2133e8b2b1cac54ae081f83b644578e11f`.

`logout.php` und die native Abmeldung nach Passwortwechsel in `settings.php`
übernehmen beim vorhandenen Cookie-Ablaufheader zusätzlich die bereits von
`session_get_cookie_params()` gelieferte `domain`. Sonst bleibt jeder Quellbyte
beider Dateien unverändert. Keine neue Domain, Host-/Securitykonfiguration,
Laufzeitoption oder Cookiepolitik wird gesetzt. Name, Ablaufzeit, Path, Secure,
HttpOnly, SameSite=Lax, Sessioninhalt/-zerstörung, CSRF-/Authprüfung, Fehler- und
Redirectablauf bleiben gleich. Insbesondere kein Domainwert aus Request,
Hostheader oder Eingabefeldern und keine Lockerung der Attribute.

## Befund und Mechanismus

Der vorhandene Security-Bootstrap setzt Path/Secure/HttpOnly/SameSite, übernimmt
aber eine extern bereits konfigurierte `session.cookie_domain`. Dies ist unter
PHP8.5.12 isoliert geprüft. Beide bisherigen Löschaufrufe ließen dagegen die
Domain weg. Bei einer übergeordneten Cookie-Domain, die vom Antwort-Host abweicht,
adressiert der Ablaufheader damit nicht denselben Domain-/Path-/Namenseintrag.
Serverseitige Sessionzerstörung ist davon getrennt; ein zurückbleibender
Clientcookie beweist keine weiterhin gültige Autorisierung.

Das [PHP-Handbuch zu setcookie](https://www.php.net/manual/en/function.setcookie.php)
verlangt für das Entfernen passende Cookieparameter.
[RFC6265, Abschnitt4.1.2](https://www.rfc-editor.org/rfc/rfc6265#section-4.1.2)
beschreibt Ersetzung über Name, Domain und Path. Die Laufzeitparameter bleiben
entsprechend [session_set_cookie_params](https://www.php.net/manual/en/function.session-set-cookie-params.php)
requestlokal; dieser Patch ändert deren Konfiguration nicht. Bei leerer Domain
bleibt der vorhandene Host-only-Ablaufheader exakt gleich.

## Grobe synthetische Headerprobe

PHP8.5.12, zwölf echte `setcookie()`-Headerantworten: je Logout und Passwort-
Abmeldeaufruf, unveränderte Basis und Kandidat, über drei feste synthetische
Profile (leere Domain, übergeordnete Domain mit Secure, Domain mit führendem
Punkt und abweichendem Path). Die jeweiligen tatsächlichen festen Aufruftexte
werden aus den beiden Quelldateien extrahiert und in einer ausschließlich
Loopback-gebundenen, kurzlebigen Headerfixture ausgeführt. Kein Appbootstrap,
Sessionstart oder Account-/Passwort-/DBzugriff; der eigene Fixtureprozess wird
anschließend beendet. Keine vorhandene Anwendung oder Serverkonfiguration wird
umgestellt.

Beobachtet: leere Domain liefert bytegleichen Header zur Basis. Bei den beiden
synthetischen Domainprofilen fehlt die Domain im Baselineheader und entspricht
im neuen Header exakt dem konfigurierten Getterwert. Entfernen allein dieses
Domainattributs stellt sämtliche alten Headerbytes wieder her. Logout und
Passwort-Abmeldung emittieren für jedes Profil denselben Header; alle übrigen
Attribute und Rückgaben bleiben gleich. PHP-Lint, vollständige Quellinverse und
Diffcheck bestehen. Die Probe beginnt keine Session und erzeugt keine
verwendbaren Credentials oder Betriebskonfiguration.

Dies ist eine isolierte PHP-Transportprobe, keine tatsächliche Abmeldung,
Passwortänderung, CSRF-/Auth-/JWT-/Cookiejar-/Browser-/TLS-/Domain- oder
Produktivabnahme. Kein Nachweis für sämtliche Browser-/Partitionierungsprofile,
Proxy-/Headerverluste oder Sessionbackendfehler. Keine Schwachstellenfreiheit,
Runtime-/V5-Zulassung, neuer Dienst oder Deployment. #62 bleibt für die reale
Logout-/Cookie-/Loginreise und weitere Kontofunktionen offen.
