# Laravel-Home: ungültige eigene Einträge direkt prüfen — #16/#79

Quellbasis Main `f517dd7ab3078ea275459dd4755e3039fa9b9771`, Baum
`ab5c08b3666e7bec71e2aef87d516e5cd71f3b50`. Native Vorarbeit: der unveränderte
reine Helfer `lib/own_invalid_record_links.php` aus dem gemergten #16-Slice.
Keine Runtime-/Provider-/Routenaktivierung und keine Abhängigkeit von PR #239.

Der bestehende Home-Reader lädt bereits eigene Zeilen und berechnet deren
Diagnose innerhalb seines frisch autorisierten Lesesnapshots. Nur dort wird
nun eine begrenzte IDliste aus **denselben** vorhandenen Diagnose-IDs/Zeilen
abgeleitet: dieselben ersten höchstens zehn positiven Diagnosekandidaten wie
nativ, zusätzlich bestätigt durch `id` und `user_id` der vorhandenen Ownrows.
Kein zusätzlicher SQLaufruf, keine neue Spalte/Zeile, neue Diagnose, Rundung,
Zeit- oder Authentscheidung. Alle bisherigen Summaryfelder bleiben erhalten;
die zusätzlichen IDs sind bereits in der bisherigen Diagnose enthalten.

Controller und ein optionales rückwärtskompatibles AttendancePage-Feld reichen
nur diese IDs weiter. Die bestehende Warnung erhält die nativ bereits bekannte
geschlossene Linkliste, ausschließlich wenn die vertrauenswürdige bestehende
`corrections`-Interaction verbunden ist und bestätigte IDs vorliegen. Andere
Seiten ohne diese Metadaten behalten ihren allgemeinen Korrekturweg. Die
Interaction ist **kein** Autorisierungsgate. Kein Requestparameter schaltet
die Liste frei oder wählt eine andere Person.

Die neuen Links sind feste GETziele mit bestätigten IDs, angekündigt im neuen
Tab mit `noopener noreferrer`. Keine rohe Diagnose, Zeit, Notiz oder fremde ID
wird hinzugefügt. Eine volle Diagnose-/Klärungsliste wird nicht zugesagt. Ohne
sichere ID wird kein Record0-/Nachtragsfallback erzeugt. Der unveränderte
Zielselector prüft den dann aktuellen Ownscope erneut, kann eigene Altbuchungen
außerhalb seiner 200 Optionen nachladen und weist fehlende/fremde/ungültige IDs
kontrolliert zurück. Ein Link ist weder Antrag noch Freigabe oder automatische
Reparatur; alle Schreib-/Vier-Augen-/CSRF-Regeln bleiben maßgeblich.

## Grober Nachweis und Grenzen

Stock Laravel-Reader/Controller/DTO und vollständiges stock Blade werden mit
erfundenen Daten in query-only SQLite-Memory über den bereits vorhandenen
`chrono_fixture`/RequestGuard-Komponentenpfad geprüft. Bisheriger Warnhinweis
ohne gezielte Links, neuer begrenzter Ownfluss, nicht verbundene Corrections
und gültige Daten bilden einen groben Journey. Eine unverfälschte große ID
`9007199254740993` wird aus dem gerenderten Link an den tatsächlichen
unveränderten GET-Auswahlfragment-/Helperpfad übergeben: eigene Zeile jenseits
der neuesten 200 gewählt, fremde ID ohne Record0-Fallback abgewiesen. Alte
Summarywerte und Quelldaten bleiben gleich, Writeraufrufe bleiben null.

Das ist keine tatsächliche SessionGuard-/Provider-/V5-Zulassung, kein
Appbootstrap, Login-/CSRF-/HTTP-/Mehrtab-/Browser-/Layout-/Formular-/
Freigabe-/Writer-/Produktivnachweis. Keine reale DB, Konten, Schlüssel oder
personenbezogenen Betriebsdaten. Keine neue Testbatterie/Abhängigkeit und keine
SQL-/Provider-/Security-/Native-/Asset-/Runtime-/Hoständerung. #16/#79 bleiben
für weitere Ansichten und echte Integrationsabnahme offen.
