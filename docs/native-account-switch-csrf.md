# Native: CSRF beim tatsächlichen Kontowechsel

Ein erfolgreicher lokaler oder LDAP-Login regeneriert weiterhin die Session-ID.
Zusätzlich verwirft er das vorhandene CSRF-Token **nur**, wenn die vorherige
`user_id` nicht exakt dem erfolgreich authentifizierten Konto entspricht. Der
unveränderte Security-Bootstrap erzeugt im nächsten Request das neue Token.
Alte Formulare/Tabs der vorherigen Anmeldung sollen daher mit ihrem alten Token
abgewiesen werden, statt als das andere Konto zu schreiben.

Erste Anmeldung und erneute Anmeldung desselben Kontos behalten ihr Token.
Exakte positive SQLite-IDs werden ohne Floatkonvertierung verglichen; unsichere
oder falsch typisierte vorhandene IDs dürfen kein Token in die neue Anmeldung
übernehmen. Der Helper ändert sonst keinen Sessionwert und erzeugt keine Token,
Konten, Schlüssel oder Sessions. Er trifft keine Login-, Rollen-, LDAP-,
Passwort- oder Revokationsentscheidung und liegt nur in den zwei bestehenden
Erfolgspfaden. Fehlgeschlagene Anmeldungen bleiben unberührt.

Dies ist ein separater Voraussetzung-Fix, **nicht** die vollständige Lösung von
#57: unbestätigte Buchungs-IDs im Browserspeicher werden weder gelöscht noch
umgebunden; der vollständige Reconciliation-Slice bleibt gesondert gesperrt.

Nachweis: grobe reine Marker-Sessionprüfungen, beide Quell-Aufrufstellen und
Inverse aller übrigen Loginbytes. Kein echter Login-/LDAP-/HTTP-/CSRF-/Browser-
Durchlauf oder operativer Testzugang wird damit behauptet.
