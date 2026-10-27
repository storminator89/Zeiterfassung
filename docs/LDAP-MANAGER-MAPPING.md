# LDAP-Managerzuordnung — CH-21 / #27

Die vorhandene native Synchronisation in `admin.php` importiert zuerst alle bisher zulässigen Identitäten (`uid` und `mail`). Erst danach plant sie `supervisor_id`-Beziehungen gegen die vollständigen DNs dieser Identitäten. Damit kann ein Manager auch später im Suchergebnis stehen. Ein DN mit `cn` als Blatt wird ausschließlich über seinen vollständigen DN zugeordnet, niemals über einen geratenen Benutzernamen.

## Konfliktvertrag und Bestand

- Pass 1 schreibt bei bestehenden Konten **keinen** `supervisor_id`; auch vorübergehend wird er nicht geleert. Neue Konten beginnen mit dem vorhandenen NULL-Standard. E-Mail, Abteilung, `auth_source='ldap'`, die bestehende Behandlung von Passwörtern sowie neue Rolle `user` bleiben unverändert. Eine Managerbeziehung befördert niemanden zur Rolle `supervisor` oder `admin`.
- Pass 2 setzt nur eindeutig aufgelöste Beziehungen. Ein tatsächlich fehlendes `manager`-Attribut entfernt wie bisher die Beziehung. Unbekannte, ungültige oder mehrdeutige Identitäts-/Manager-DNs sowie Selbstzuordnungen und Zyklen sind Konflikte ohne Beziehungsschreibzugriff für das betroffene Konto. Vorhandene Beziehungen bleiben dabei erhalten; ein neues Konto bleibt ohne Manager.
- Der Zykluscheck berücksichtigt auch beibehaltene Beziehungen. Es wird keine neue Hierarchie oder Rollenregel erfunden. Bereits vorhandene Konflikte werden nicht automatisch repariert.
- Die Beziehungsplanung und -änderungen aus Pass 2 stehen in einer eigenen Transaktion mit Rollback bei Fehlern. So wird kein halbfertiger Beziehungsplan veröffentlicht; unveränderte Beziehungen lösen keinen zusätzlichen UPDATE aus. Der Identitätsimport aus Pass 1 bleibt wie bisher separat: Dies ist kein atomarer Gesamtimport.
- Konflikte erscheinen in der bestehenden escaped Fehleranzeige mit interner Konto-ID und festem Grund, ohne Bind-Daten, Zugangsdaten oder rohe Directory-DNs. Identitäten und konfliktfreie Beziehungen können bereits synchronisiert sein. Bei Konflikten wird keine uneingeschränkte Erfolgsmeldung ausgegeben.
- Ein Manager außerhalb der aktuell importierbaren Suchergebnisse wird nicht auf ein gleichnamiges lokales Konto geraten. Er bleibt sichtbar ungelöst. Es gibt keine zusätzliche LDAP-Abfrage und keine persistente DN-/Schemaänderung.

## DN-Verarbeitung und Grenzen

Die bestehende PHP-LDAP-Funktion [`ldap_explode_dn`](https://www.php.net/manual/en/function.ldap-explode-dn.php) validiert und zerlegt DNs mit Parameter **0**, also einschließlich Attributnamen. Der reine Helfer verarbeitet anschließend deren escapte RDNs: Attributnamen werden kleingeschrieben, escapte Zeichen/Hex-Oktette dekodiert und Attributreihenfolgen innerhalb eines mehrwertigen RDN sortiert. RDN-Reihenfolge und Wertebytes/-großschreibung bleiben erhalten. BER-Hexwerte werden opak verglichen. Es gibt kein einfaches Komma-/CN-Splitting und keinen UID-Fallback auf unbekannte DNs.

Das ist absichtlich **keine vollständige schemaabhängige LDAP-DN-Gleichheitsprüfung**: Attribut-OID-Aliase, Value-Casefolding, Unicode-/Whitespace-Matchingregeln und BER/Text-Äquivalenzen werden nicht geraten. Solche Abweichungen können einen sichtbaren Konflikt statt einer falschen Beziehung ergeben. Parserfehler oder ein fehlender Parser ergeben keine stillschweigende Managerlöschung.

## Begrenzter Nachweis

`tests/ldap_manager_mapping.php` lädt ausschließlich den reinen Helfer und benutzt einen expliziten synthetischen Parser-Ausgangsfixture. Er prüft die Planungslogik mit bereits vergebenen IDs: später gelieferter Manager, umgekehrte Reihenfolge, Wiederholung, verschachtelte DNs, Escapes/Hex-/Mehrwert-RDN-Normalisierung, fehlender Manager, unbekannte/ungültige/mehrdeutige Bezüge, Selbstbezug und Zyklen einschließlich beibehaltener Kanten. Hinzu kommen PHP-Syntaxprüfungen. Basis-Commit, Laufzeit und Ergebnisse stehen im Übergabenachweis.

Die Ausführung von `ldap_explode_dn` selbst ist **nicht geprüft**, da der vorhandene PHP-8.5-Prüfruntime keine LDAP-Erweiterung enthält. Der Fixture prüft die Parsergrenze, nicht deren Implementierung. Es wurden weder `config.php` noch reale Datenbanken, Accounts, LDAP-Verbindungen, Credentials, HTTP, Browser, Server oder Deployment benutzt. Der echte Directorylauf, Fehler-/Abbruchverhalten des Gesamtimports, Rollenwirkung im Betrieb und die native UI-Integration bleiben offen; #27 ist damit nicht als real abgenommen erklärt.
