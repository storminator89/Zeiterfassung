# #79/#62 — Settings zeigt die wirksame rohe Themeauswahl

Basis Main `4d6efa38dfa9175fbb14a3ca25812686bd701154`, Tree
`5d0ce27e13a74841ae7b4b2ec6982931ce8c4be6`, 29.10.2026.

Die bestehende Toolbar und die Settings-Vorschau verwenden dieselbe
browserseitige Themepräferenz. Der Settings-Select wurde jedoch nur aus der
älteren serverseitigen Sessionwahl gerendert und danach nicht synchronisiert.
So konnte die Seite bereits dunkel sein, während das Formular weiterhin
`light` anzeigte und beim nächsten normalen Speichern diesen alten Wert sendete.
Auch ein rohes `system` war nicht zuverlässig am Formular erkennbar.

Der unveränderte gemeinsame `setTheme`-Resolver setzt nun nach Auflösung und
Speicherung auch den **bereits vorhandenen** `theme_mode`-Select, falls vorhanden.
Die Auswahl entspricht der rohen Präferenz `light|dark|system`, nicht der aktuell
aufgelösten Systemfarbe. Initialisierung, Toolbar, Settings und Systemwechsel
bleiben so konsistent. Ohne Settingsformular passiert nichts Zusätzliches.
Die native Seite lädt ihren Header vor dem Formular; ihr vorhandener
DOMContentLoaded-Aufruf synchronisiert den dann vorhandenen Select. Blade lädt
den gemeinsamen Footer nach dem Settingsinhalt; beide vorhandenen Abläufe werden
bewahrt.

Nur vier JavaScriptzeilen im nativen Header und dieselben vier Zeilen im
Laravel-Layout kommen hinzu. Keine neue Präferenzpriorität, Storage-/Session-/
Cookiepolitik, kein neuer Schlüssel oder Listener. Der vorhandene Browserwert
bleibt maßgeblich; ungültige Werte verwenden weiter `light`. Bei gesperrtem/vollem
Speicher bleibt der bekannte dokumentlokale Fallback erhalten, ohne zugesagte
Persistenz nach Navigation/Reload. Ein Setzen von `.value` löst weder Change noch
Submit aus: kein automatischer POST, Writer oder wiederholtes Speichern.
Settings-PHP/Blade, Formulare, Passwort-/Carry-in-/Localeabläufe einschließlich
PR #342 sowie Auth/CSRF/Runtime und übrige Navigation bleiben unverändert.

## Begrenzter Nachweis

Node24.22.1: sechs grobe tatsächliche Scriptjourneys (Native/Blade jeweils mit
normalem, gesperrtem und vollem Speicher) in einem synthetischen
DOM-/Storage-/Media-Modell. Die unterschiedlichen ursprünglichen
Script-/Formularreihenfolgen werden berücksichtigt. Die jeweilige Baseline zeigt
ein dunkles Theme bei altem `light`-Formularwert; der Kandidat zeigt den tatsächlich
wirksamen rohen Wert. Toolbar in beide Richtungen, Settings/System samt wechselnder
Systemfarbe, manueller Vorrang, Invalid-Fallback, fehlendes Settingsformular und
vorhandene Sidebar-Öffnen/Escape/Close/Außenklick/Resize-Abläufe sind enthalten.
Fehlgeschlagene Storagewrites werden nicht als browserübergreifend gespeichert
ausgegeben. PHP-/JavaScript-/kompilierte Blade-Syntax und vollständige
Quelleninverse werden getrennt geprüft.

Keine tatsächliche Browser-/Layout-/OS-/Storageengine-/Mehrtab-/Reload-/HTTP-/
Session-/POST-/App-/Authabnahme oder Betriebsänderung. Keine Konten, Credentials,
Schlüssel, Server, Dienste, Assets, Dependencies oder neue Testsuite. #79/#62
bleiben für ihren weitergehenden Umfang offen; Browserabnahme bleibt beim Nutzer.
