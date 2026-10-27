# FW-06 / #77 – gepflegtes JWT-Paket im inaktiven Laravel-Guard

Der Laravel-Guard verwendet jetzt `firebase/php-jwt`, nicht mehr die kopierte
Signaturprüfung. **Nur der inaktive Frameworkpfad wird umgestellt.**
`api.php`, `lib/api_tokens.php`, `admin.php`, `security.php`, vorhandene Issuer,
Schlüssel, Account-/DB-Konfiguration und alle produktiven Routen bleiben in
diesem Folgepaket bytegleich. Die Tokenprüfung im aktiven Altpfad wird nicht
nebenbei migriert. Die [erste Read-/Wiregrenze](api-read-compatibility.md) bleibt
unregistriert und #77 bleibt offen.

## Entscheidung und nachprüfbare Herkunft

Composeranforderung `firebase/php-jwt:^7.2.1`, Lock **v7.2.1**, offizieller
Quellcommit `0501f5729b9e2448516674aff07c7d1727869b8e`, BSD-3-Clause. Die
[offizielle Veröffentlichung](https://github.com/googleapis/php-jwt/releases/tag/v7.2.1)
ist vom 28.09.2026; die
[Packagist-Paketmetadaten](https://packagist.org/packages/firebase/php-jwt)
führen dieselbe Version und Quelle. Die
[offizielle Composerdatei](https://github.com/googleapis/php-jwt/blob/v7.2.1/composer.json)
verlangt PHP `^8.0` und keine weiteren Runtimepakete/Extensions. Der vorhandene
PHP-8.5-Pfad genügt; kein zusätzlicher Dienst und keine Hoständerung.
Alle anderen gelockten Pakete bleiben unverändert. Keine Vendorquellen werden
ins Repository kopiert. Composerplugins und Installationsskripte wurden für den
begrenzten Paketdownload nicht ausgeführt; keine Credentials oder Schlüssel
waren nötig. Die aktuellen
[aufgelisteten Paketadvisories](https://packagist.org/packages/firebase/php-jwt/advisories)
betreffen Versionen vor 7.0.0; der tatsächliche Lock wurde zusätzlich auditiert.
Das ist kein Versprechen dauerhafter Verwundbarkeitsfreiheit.

Alternative `lcobucci/jwt` wurde geprüft, hier nicht aufgenommen: dessen
[aktuelle Composeranforderung](https://github.com/lcobucci/jwt/blob/5.6.1/composer.json)
verlangt `ext-sodium`, das der aktuelle CLI-Prüfpfad nicht lädt. Für diesen
kleinen HS256-Adapter braucht es keinen neuen Runtime-/Extensiongate.

## Kleiner Profiladapter, keine neue Kryptoprüfung

`LegacyIssuedTokenVerifier` delegiert Dekodierung und Signaturprüfung an
`JWT::decode` mit ausschließlich `new Key(existingAsciiSecret, 'HS256')`.
Kein eigener HMAC-, Signaturvergleich, Algorithmusresolver, JWKS-/Netzzugriff,
Keyrotation, Tokenissuer oder Fallback auf den alten Verifier. Bestehende
Secret-Admission und aktueller Passwortwiderruf liegen unverändert in
`LegacyApiIdentity`: `sha256(current users.password)` gegen Token-`ver`;
keine `auth_source=local`-/Rollenbeschränkung für einen bestehenden Bearertoken.
Die bisherigen 401-Status und Meldungen bleiben unverändert; der zentrale
Antwortformatter liefert dafür nun wie die native API den JSON-Fehlerumschlag
`{success:false,message}`. Die Tokenakzeptanz wird dadurch nicht geändert.

Die Anwendung prüft bewusst ihr konkretes bestehendes Issuerprofil, nicht
sämtliche von einem allgemeinen JWT-Paket erlaubten Claims:

- Maximal 4096 Bytes und drei Segmente; `alg=HS256`, `typ=JWT` exakt.
- `iss` und `aud` müssen exakt die Zeichenkette `localhost` sein; keine
  Audiencearray- oder Issuermigration.
- `iat`, `exp` und positive `user_id` bleiben ganze Zahlen, `ver` eine
  Zeichenkette; keine neuen Claimnamen oder numerischen Stringkoerzierungen.
- Der Bestandsvertrag erlaubt `iat <= now+30`, aber **keine** zusätzliche
  Expiryfrist: `exp > now` bleibt verpflichtend. Ein privater, nicht nach außen
  gegebener anonymer Paketuntertyp hat feste Defaults `leeway=30` und
  `timestamp=null`. Beide statischen Felder sind neu deklariert und daher von
  den veränderlichen Elternglobalen anderer Paketkonsumenten isoliert. Der
  Produktionscode verändert niemals Clock/Leeway und gibt weder Untertyp noch
  Instanz aus. Die zusätzliche strikte Expiry-/Iatprofilprüfung verhindert,
  dass generische Paketleeway die alte Ablaufgrenze lockert.
- Paketverifikation vergleicht dekodierte Signaturbytes. Der Altverifier
  verlangte eine kanonische Base64url-Signaturzeichenfolge. Der Adapter bewahrt
  diese reine Formprüfung durch die **Paketcodecs**, damit eine Änderung nur
  ungenutzter Endbits nicht neu als gültige Tokenmanipulation akzeptiert wird.

Das Verhalten wurde anhand der
[exakten Paketquelle v7.2.1](https://github.com/googleapis/php-jwt/blob/v7.2.1/src/JWT.php)
reviewt. Keine Exceptionnachricht, Claims, Signatur oder Secret werden ausgegeben.
Das Paket entscheidet über kryptographische Gültigkeit; die Anwendung behält
nur ihren fachlichen Identitäts-/Wirevertrag.

## Bekannte Nichtgleichheit und weiter gesperrter Cutover

Der Altverifier ignoriert `nbf`; das gepflegte Paket prüft es. Die vorhandenen
Login-/Adminissuer erzeugen **kein** `nbf`. Deren tatsächlich ausgestellte
Tokens einschließlich der bisherigen Iat-Toleranz werden unterstützt.
Ein sonst korrekt signierter Token mit zukünftigem oder ungültig typisiertem
`nbf` kann dagegen neu abgelehnt werden. Weitere ungewöhnliche fremde
Header-/Claimkombinationen gehören nicht zum bewiesenen Issuerprofil. Es wird
**keine vollständige Äquivalenz beliebiger signierter Tokens behauptet**.
Vor einer produktiven Migration sind solche Unterschiede ausdrücklich zu
entscheiden; keine neue Parser-/Signaturengine soll diese Paketschranken
heimlich wieder umgehen.

Der [inaktive Login-/direkte Stempelkandidat](candidate-api-actions.md)
verwendet dieses Paket inzwischen auch zur Ausgabe. Offen bleiben persistente
geteilte Loginbudgets, vollständige Erweiterungsabläufe, Swagger und eine separat freigegebene
Routenaktivierung. Kein neuer Account, Schlüssel, Pfad, Cookie oder persistent
konfigurierter Secretprovider; `/up` bleibt die einzige ausgelieferte Route.

## Grobe, begrenzte Nachweise

```sh
cd framework
php tools/prove-issued-token-profile.php
php tools/rehearse-api-compatibility.php
```

Ein grober Profilnachweis verwendet ausschließlich einen zufälligen
Wegwerfschlüssel im Prozess: bestehendes Issuerformat und Paketausgabe,
repräsentative Expiry-/Iat-/Claim-/Signaturablehnungen, isolierte Paketclock/-leeway
und die ausdrücklich erwartete `nbf`-Nichtgleichheit. Der unveränderte
viergruppige Read-/Wireablauf läuft weiter durch Laravel-HttpKernel-Requests
mit Memoryfixture und prüft auch aktuellen Passwortwiderruf/eigenen Scope.
Keine Mikrosuite, realen Schlüssel/Zugangsdaten, Netzwerkfunktionstests,
Browser/UIabnahme, CI-Auslösung oder produktive Endpoints.
