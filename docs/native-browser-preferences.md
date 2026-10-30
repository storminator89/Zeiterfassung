# Native Navigation und Theme bei Browser-Speicherfehlern — #18/#62

Basis Main `65cc1a7b889d56d61fb91973fbd2d3012979f306`, Tree
`bdddaaef49dfde5dfab2547a99b8d9ada5960652`. Übernommen werden ausschließlich
die bereits gemergten aktuellen Laravel-Scriptkörper für Theme/Sidebar und
Settings-Theme; der dortige Speicherfallback stammt aus PR #229. Keine neue
Browser-, Sicherheits- oder Providerabhängigkeit.

Der vorherige tatsächliche native Inline-Code konnte bei einem Fehler beim
Lesen oder Schreiben von `localStorage` abbrechen, bevor Sidebar-Handler
registriert waren. Nun sind diese Zugriffe wie im bestehenden Laravel-Stand
abgefangen. Fehlgeschlagene Schreibwerte für `theme`/`sidebarCollapsed` bleiben
nur im aktuellen Dokument verfügbar. Keine Persistenz über Navigation oder
Reload und kein gespeicherter Erfolg werden behauptet.

Header und Settings nutzen denselben vorhandenen Resolver: `light`, `dark`
oder die bereits angebotene Wahl `system`. Diese rohe Auswahl bleibt getrennt
vom gerenderten hellen/dunklen Theme; Systemänderungen folgen nur bei Systemwahl,
manuelle Auswahl bleibt manuell. Die native Systemwahl wird damit an das
bereits gemergte Laravel-Verhalten angeglichen, statt `system` als konkretes
Darstellungstheme zu setzen. Ungültige Werte verwenden den vorhandenen
Laravel-Fallback `light`. Dieselben beiden Browser-Schlüssel bleiben erhalten.

Nur Inline-JavaScript in `header.php` und `settings.php` ändert sich. Sämtliche
PHP-/HTML-Bytes außerhalb dieser exakten Scriptkörper bleiben erhalten,
insbesondere der native Settings-Sprachfix #276, seine Security-/CSRF-/Auth-
Reihenfolge, Passwortabläufe und alle Formulare. Keine Routen, Netzwerkaufrufe,
Cookie-/Session-/Konto-/Provider-/Security-/Laravel-/Assetpin-/Pagingänderung.
Keine Nutzung des Fallbacks für Anwesenheit, Pendingereignisse, Auth oder Writer.

## Begrenzte Nachweise

Der bestehende grobe synthetische JavaScript-DOM-Journey der Laravel-Vorarbeit
wird mit ausschließlich angepassten Dateipfaden für die tatsächlichen nativen
Scriptkörper wiederverwendet: normaler, gesperrter und voller Speicher;
Theme/System/Settings sowie mobile Sidebar öffnen, Escape/Schließen/Außenklick,
wieder öffnen und Desktop-Resize. Der alte native Code bricht in den beiden
Fehlermodi vor Sidebarregistrierung ab; der Kandidat durchläuft alle drei.
Unveränderte Quellkörper zum aktuellen Laravel-Stand, vollständige Datei-/
Bauminverse und Syntaxprüfung ergänzen den Nachweis. Keine neue Testbatterie.

Das ist kein tatsächlicher Browser-/Sperr-/Quota-/Layout-/Reload-/Mehrtab-/
Login-/HTTP-/DB-/Produktivnachweis. Kein Appbootstrap oder Konto-/Datenzugriff.
Keine Betriebs-/Runtime-/Host-/V5-Aktivierung, keine Abhängigkeit von PR #239.
#18/#62 und echte Browserabnahme bleiben für den weitergehenden Umfang offen.

## Settings-Auswahl in der tatsächlich gewählten Sprache — #62/#79

Die drei sichtbaren Theme-Optionen waren in Native und Laravel unabhängig von
der gewählten Sprache fest als „Hell / Dunkel / System“ hinterlegt. Beide
bestehenden Controls verwenden nun die vorhandenen DE/EN/ZH-Wörterbücher:
„Hell / Dunkel / System“, „Light / Dark / System“ und „淺色 / 深色 / 跟隨系統“.
Nur diese Beschriftungen ändern sich; die Formularwerte bleiben bytegleich
`light`, `dark`, `system`. Auswahlzustand, Name/ID, POST-Ziel, CSRF, Sprachauflösung,
Session-/Speicherablauf und sämtliche Inline-Scriptkörper sind unverändert.
Es wird weder eine Auswahl gespeichert noch eine Systempräferenz neu ermittelt.

Die sechs Wörterbücher erhalten nur dieselben drei Schlüssel. Alle alten
Wörterbucheinträge sowie sämtliche nativen Settings-Handler — insbesondere
Passwort, Übertrag, Importmeldungen und deren Erfolgs-/Fehlersemantik — bleiben
exakt. Die Änderung transportiert keinen Teil des gehaltenen PR #340 und
keine #57-Arbeit. Keine neue UI, Route, Abhängigkeit, Assetpin, Daten-/Schema-,
Runtime-, Host- oder Sicherheitsänderung.

Grober Beleg unter PHP 8.5.12 / SQLite 3.46.1 / Laravel 13.38.0: vorhandener
Reader-/Settingscontroller-/Query-only-Memory-Profiljourney erneut bestanden;
54 tatsächliche vollständige kompilierte Settings-Renderings und neun echte
quelltext-extrahierte Native-Select-Renderings prüfen drei Sprachen, alle drei
Auswahlen, User-/Admin-Darstellung sowie normale, Erfolgs- und Fehleransicht.
Native-/Laraveltexte sind gleich; Werte und ausgewählte Option bleiben gleich.
Nach Rückersetzung ausschließlich der Optionsbeschriftungen ist die gesamte
bisherige Settingsausgabe byteidentisch. Wiederholte Darstellung und unveränderte
Quellzeilen, Schema, Change-Zähler, Transaktionen, Session und Zeitzone geprüft.

Dies ist kein neuer Browser-/System-Media-/Speicher-/Layout-/Login-/Auth-/CSRF-/
HTTP-/Produktivnachweis. Die unveränderten Scripts werden nicht als neue
Funktionalitätsabnahme ausgegeben. Keine neue Testsuite, manuelle CI, echte
Konten/Credentials/Schlüssel, Writeroperation oder Bereitstellung.
