# Explizite V5-API-Komposition für die bestehende FreshWeb-Instanz

Die vorhandenen V5-Dienste für API-Login, Token-Generation, aktuellen Kontostatus,
gesperrte Schreibautorität und gemeinsamen Loginbudgetvertrag waren integriert,
aber nicht durch die offizielle API-Komposition erreichbar: FreshWeb erzwang
`chrono_api.enabled=false`, die API-Factory akzeptierte nur V4. Dieses Paket
verbindet ausschließlich diese bereits vorhandenen Teile; es ersetzt keine API.

## Begrenzter Vertrag

- Default bleibt aus. FreshWeb akzeptiert unter dem ausdrücklich ausgewählten
  `account-lifecycle-v5`-Profil nur ein echtes boolesches API-Opt-in und friert
  dessen konkreten Wert gegen späteren Drift ein. V5 wird niemals als V4 behandelt;
  lokale Auth, ausgeschaltetes LDAP/Sync und gemeinsames Loginbudget bleiben Pflicht.
- Bei `true` muss die bestehende API-Factory den gesamten bisherigen Vertrag
  erfüllen: vollständige gleiche Runtime-Metadaten, Named-Readkonfiguration,
  Shared-Budgetobjekt, Produktion/non-debug, eigene API-Keyadmission und alle
  ursprünglichen Auth-/Hashing-/Servicegrenzen. Die bestehende Keyadmission bindet
  ausdrücklich alle vier V5-Liniensignaturen; kein optionaler Key-/Schemafallback.
- Zusätzlich muss das tatsächlich ausgewählte Runtimeobjekt V5 sein und die
  vorhandene gebundene API-Identität Generationen verlangen. Das genaue
  Runtimeobjekt wird für den V5-API-Kontext eingefroren und bei jeder bestehenden
  Profilprüfung erneut verglichen; auch ein gleichwertiger Ersatz oder eine
  spätere generationlose Identität wird abgelehnt. Diese Prüfung öffnet weder DB
  noch Writer oder Signing-Key. Der unveränderte V4-Pfad erhält keine neue
  Identitätsauflösung oder V5-Voraussetzung.
- Route/Wire/Bearer-Guard, API-Login/Tokenverifikation, Reader/Writer, Kontostatus,
  Generation, Rootkeys, DBzugang und Budget werden weiter von den vorhandenen
  Diensten umgesetzt. Kein neues Tokenformat, zweiter Writer oder Autoretry.

Der globale `RuntimeDatabase`-Hold und die vorhandenen Recovery-/Setupgates werden
hier **nicht** verändert. Mit diesem Paket allein registriert V5 weiterhin keine
Geschäftsrouten. Ein bloßes APIflag oder synthetische Metadaten sind weder physische
Keyadmission noch Runtime-/Recoveryfreigabe.

## Gemeinsame isolierte NEW-Installation

Nur der vollständige, getrennt geprüfte Runtime-/Setup-Handshake darf für die
nutzerbeauftragte isolierte End-to-End-Abnahme verwendet werden. Keine bestehende
Instanz, kein vorhandener Schlüssel und insbesondere kein fremder Port/Host werden
adoptiert oder verändert. Keine Defaults/Cutover-/Deploymentfreigabe entsteht hier.

Der vorhandene NEW-Producer `FreshApiSigningKeyFiles::createNew` unterstützt V5
bereits. Seine kanonische leere DB- und Budgetprüfung läuft aber über denselben
Runtimepfad und bleibt durch dessen aktuellen Hold blockiert. Dies wird nicht
umgangen. Nach vollständiger Freigabe ist derselbe private NEW-Pfad zu verwenden:
API-Key/Metadatentransport **vor** FirstAdmin-Setupprovisionierung, Setupabschluss
oder Nutzdaten. Der Producer verweigert vorhandene `jwt.key`, Environmentdatei,
`setup-profile.json`, `operator-setup-token.txt` und `setup-closed`, öffnet exklusiv
und erzeugt keine automatische Wiederholung. Der bestehende CLI-Befehl
`chrono:api-key --new-only --stopped` verlangt weiterhin ausgeschaltete
Fresh-/Runtime-/Budget-/APIflags. Schlüssel/Token/Passwörter gehören weder in
Nachweise noch in Versionskontrolle; die gemeinsame Probe nutzt nur eigene,
kurzlebige synthetische Konten in einer neu zugelassenen privaten Instanz.

## Bereits geprüfte Kompositionsgrenze; E2E noch offen

Wiederverwendeter tatsächlicher Laravel-Providerboot mit PHP8.5.12/Laravel13.38 auf
wegwerfbaren credentialfreien NEW-Dateikohorten: V4-API/Weberreichbarkeit wie zuvor;
V5-API aus und vollständig ausgewählte V5-API erreichen genau den unveränderten
Runtime-Hold, nur `/up`, keine Geschäftsrouten. Nichtbool, fehlendes Budget,
fehlende/falsch gebundene Keymetadaten und generationlose APIidentität werden
abgelehnt. Flag-, Generation- und Runtimeobjektdrift werden zurückgewiesen.
Null Writer-/DBmanagerauflösung und unveränderte Datenbank-/Manifestbytes.

Die Probe liest keinen Signing-Key: erfundene Metadaten verweisen absichtlich auf
eine nicht vorhandene `jwt.key`; der bekannte öffentliche App-Key-Formatliteral
wird nur als Metadatum genutzt. Das ist ausdrücklich **kein** positiver V5-Runtime-,
JWT-, physischer Key-, Recovery-, HTTP- oder Browsernachweis. Die gekoppelte
Installation → Setup → Login → Buchung/Pause/Ende → Korrektur/Historie sowie
API-Login/Status/Buchung, Generationwiderruf und Fehler-/Reloadfälle müssen auf dem
gemeinsam vollständigen zugelassenen Kandidaten tatsächlich geprüft werden.
