# Initiale Laravel-Browsersprache (Erweiterung zu #37)

Begrenzte Erweiterung der bisher bewusst nativen Parität: Die Laravel-Seiten für
Login, Registrierung und First-Admin-Setup benutzten nur die ersten zwei Zeichen
von `Accept-Language` und initial nur DE/EN. Dadurch führten etwa
`fr-FR,en;q=0.9` und `zh-CN` zu Deutsch, obwohl die englische bzw. chinesische
Oberfläche bereits auswählbar ist. Die vorhandenen nativen Änderungen werden
nicht erneut umgesetzt; `lib/language.php` und Native-Bootstrap bleiben bytegleich.

Der neue reine `InitialWebLanguage`-Helper verwendet den bereits installierten
Symfony-`AcceptHeader`-Parser und dessen Qualitätsreihenfolge. Er wählt das erste
konkrete unterstützte DE-/EN-/ZH-Sprachangebot mit positiver Qualität, einschließlich
Region-/Schrift-Tags wie `en-US` oder `zh-Hant-TW`. Angebote mit `q=0`, unbekannte
oder ungültige Tags und Wildcards sind keine konkrete unterstützte Auswahl.
Fehlt eine Auswahl oder überschreitet der Header 4096 Bytes, bleibt der bestehende
deutsche Fallback. Dies ist eine Displaypräferenz, keine vollständige HTTP-
Sprachverhandlung mit 406-Antworten oder neuer Übersetzungs-/Abdeckungszusage.

**Vorrang bleibt unverändert:** explizites Query-`lang` → vorhandene Sitzungswahl
→ nur bei fehlender Sitzungswahl Browserpräferenz → Deutsch. Ungültige vorhandene
oder explizite Werte verwenden weiterhin `valid_language()` und damit Deutsch;
sie werden nicht durch den Header ersetzt. Der bestehende Sitzungs-`lang`-Write
bleibt in denselben Controllern an derselben Stelle. Der Helper selbst liest oder
schreibt keine Session, Konten, Cookies, Schlüssel, Datenbank oder Konfiguration.

Nur der Selectoraufruf und Import in diesen drei Controllern ändern sich. Alle
Anmelde-/Registrierungs-/Setupdienste, Autorisierung, CSRF, Fehlerstatus, Formdaten,
Routen, Runtime/V5-Gates, Spracheinstellungen, Templates und Sprachdaten bleiben
unverändert. Keine neue Dependency oder Sicherheits-/Hosteinstellung und keine
Credential-/Server-/Remoteaktion.

## Grobe Prüfung / verbleibende Grenzen

PHP8.5.12/Laravel13.38.0, vorhandener realer Symfony-Parser: DE/EN/ZH, gewichteter bzw.
unterstützter nachrangiger Fallback, Region-/Schrifttags, Nullqualität,
malformed/überlanger Header und Vorrang vorhandener Auswahl. Die tatsächlichen
Locale-Anweisungen der drei Controller werden isoliert mit echten Laravel-
Request-/ArraySessionStore-Komponenten geprüft: explizite Querywahl, gespeicherte
Wahl, bisheriger Validator und identischer `lang`-Write. PHP-Syntax und vollständige
Diff-/Inverse-/Pfad-/Modusprüfung ergänzen diese begrenzte Kompositionsprüfung.

Zusätzlich wird der tatsächliche öffentliche `LocalWebSessionController::create`
mit realem Request/ArraySessionStore und der kompilierten unveränderten Login-
Blade samt DE-/EN-/ZH-Wörterbüchern geprüft: 15 Eingabefälle und jeweils ein
Folge-GET mit derselben Array-Sitzung und geändertem Header. Eine frische englische
Präferenz liefert das englische Dokument samt Titel; jede gültige gespeicherte
oder explizite Auswahl behält Vorrang. Formular, Capabilityanzeige und fremde
Sitzungs-/Formdaten bleiben erhalten. Der Auth-Tripwire wird nie aufgerufen.
Dies ist keine erfolgreiche Anmeldung oder echte Cookie-/Sessionpersistenz.

Die geprüften unveränderlichen Basis-/Kandidatenbäume, Patchhash und Laufzeit-
Receipts werden im zugehörigen PR-Nachweis genannt. Kein HttpKernel-/Browser-/
Native-Livebootstrap-/Auth-/CSRF- oder Setup-/Registrierungsnachweis. Der reale
Native-Bootstrap würde private Schlüssel und eine Datenbank benötigen; diese
werden für den begrenzten Sprachtest nicht angelegt oder aus Beständen übernommen.
Keine Native-Headerverhandlung geändert. Der ursprüngliche Native-Umfang von
Issue #37 ist separat abgeschlossen. Diese Laravel-Erweiterung ändert dessen
Abnahme nicht und erklärt weder einen Native-Browsertest noch eine UI-/
Migrationsgesamtabnahme oder Cutover.
