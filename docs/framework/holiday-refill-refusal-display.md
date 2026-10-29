# #41 — abgelehnte Feiertagsergänzung ohne alten Erfolgsbeleg prüfen

Basis Main `6ec57d4007cf6d6129d3d2acddc5daf06ee6881f`, Baum
`1c7f6e7a23e85d38d0f16fde3909dee0afc13dce`. Nur Laravel-Anzeige des bereits
vorhandenen festen Feiertagsbefehls; kein neuer Import, Standort oder Abruf.

## Tatsächlicher Fehler und enger Nutzen

Ein noch nicht gelesener Erfolgsbeleg einer vorherigen Ergänzung konnte nach
einer **neuen abgelehnten** Ergänzung neben deren400-/409-Fehler erscheinen:
`refillHolidays()` renderte die Adminseite, deren bestehender Leser den alten
Sessionbeleg konsumierte. Die native Variante verwirft ihn bereits vor einem
neuen Versuch. Laravel verwirft jetzt ebenfalls nur diesen alten, nicht
berechtigenden Anzeigebeleg vor dem bestehenden festen Serviceaufruf. Ein
zurückgewiesener Versuch ist damit nicht scheinbar als neues Speichern bestätigt.

Nach den unverändert als bekannt behandelten `InvalidArgumentException`-/
`DomainException`-Antworten400/409 kann die Person ihre eingetippten Angaben
nun im **gleichen vorhandenen Formular derselben Antwort** prüfen, statt die
vollständige Quelle erneut einzufügen. Die Karte ist geöffnet; die vorhandene
BW-Bestätigung bleibt ausdrücklich **nicht angekreuzt**. Keine automatische
Wiederholung, neue POST-Aktion oder vorbestätigte Quelle.

`holiday_refill_rejected_draft()` ist eine reine Darstellungsprojektion:
- Nur der einzelne bekannte Feiertagsbefehls-Shape; keine fremden Formularfelder.
- Jahr als unveränderter1–8-stelliger Ziffernstring, einschließlich noch
  ungültiger Bereichs-/Leading-zero-Eingabe. Keine Typkonvertierung oder Reparatur.
- JSON als unveränderter gültiger UTF-8-String mit höchstens131072Bytes;
  Tab/CR/LF bleiben erhalten, andere ASCII-Steuerzeichen werden nicht angezeigt.
  Kein Dekodieren, Validieren als Feiertagsquelle, Trimmen oder Abschneiden.
- Nicht darstellbare Felder fallen einzeln weg, niemals als gekürzte gültige
  Quelle zurück. Tokens, BW-Checkbox, Passwort-/Credentialfelder werden nicht
  kopiert. Blade escaped beide Werte einschließlich `</textarea>`-Versuchen.

Der neue Entwurf bleibt eine private lokale Variable. Kein Flash/Sessionstorage,
Log, Datei, Queryparameter, DTOkanal oder Redirecttransport des JSON. GET,
Erfolg303 und unbestimmte503-Antwort bieten keinen alten Entwurf an. Geschützte
Authfehler bleiben geschützte Fehler ohne Quell-/Formularantwort. Bestehende
Erfolg303/minimaler neuer Einmalbeleg bleiben unverändert. Gemischte/unbekannte
Befehle werden weiterhin schon vor dieser Methode zurückgewiesen; deren alte
Dispatch-/Sessionsemantik wird nicht umgedeutet.

Die eigentliche Service-/Writerzulassung, CSRF-/Rollen-/Source-/V5-Grenze,
Transaktions-/COMMIT-/Rollbackregeln, Parser, JSONprüfung, Insert-/Auditlogik,
Feiertagsbestands-/Soll-/Gutschriftberechnung, Native-Seiten, Routen, Schema,
Runtime/Hosts, Assets, Dependencies und CI/Testquellen sind unverändert.
Kein Herkunfts-/Vollständigkeits- oder rechtliches Feiertagsattest; #41 bleibt
für regionale Herkunft, Vollständigkeit und Betriebsabnahme partiell/offen.

## Grober Nachweis und Grenzen

Ein siebenphasiger PHP8.5.12/SQLite3.46.1/Laravel13.38.0-Journey verwendet den
wirklichen alten/neuen Admincontroller, den unveränderten kanonischen
Geschäftsreader und die kompilierte tatsächliche Adminseite/Partial. Anonyme
query-only-Memoryfixture, Fake-RequestGuard, ArraySession und Serviceport mit
bekannten synthetischen Ergebnissen; kein echter Writer-/Runtimezulassungsnachweis.
Der alte Controller reproduziert den widersprüchlichen2030-Erfolgsbeleg neben
einem neuen400; der neue zeigt ihn nicht. Exakte escaped Felder nach400/409,
Bestätigungsreset, geöffnete Karte, neuer GET ohne Entwurf,503/Auth ohne Quelle,
byte-/Typ-/Unicode-/Steuerzeichenlimits ohne Kürzung, unverändertes Erfolg303/
neuer Einmalbeleg, gemischte Befehle sowie Member-/Directory-Verweigerung geprüft.
Daten-/Schema-/Änderungszähler, query-only/Transaktion und native Session/Zeitzone
bleiben erhalten. Der Baseline-Harness brauchte ausschließlich eine explizite
`__DIR__`-Abbildung für seine evaluierten unveränderten Definitionen; kein
Produktcode oder Zulassungsgate wurde dafür abgeschwächt.

Keine neue Repositorytestsuite oder manuelle CI. Notwendige PHP-/Blade-Syntax,
exakte Baum-/Patch-/Inverse-/Kaltbundleprüfung und unabhängiger Review separat.
Kein vollständiger App-/Bootstrap-/V5-/Login-/Auth-/CSRF-/HTTP-/Browser-/Sicht-/
Konkurrenz-/Last-/Produktivnachweis. Keine echten Konten, nutzbaren Credentials,
Keys, Dateidatenbanken, Server, Konfiguration, externe Quelle oder Deployment.
