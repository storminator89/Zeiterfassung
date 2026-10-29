# #67 / #18: begrenzter Darstellungsfallback des nativen Kalenders

Isolierter Quellschritt auf Main `84076afffc2e4225c3ebaeb6411eab4f4e1b3deb`,
Baum `2b3e1b23fdf4ca81435d48d3487142f6d174c894`. Die vorhandene native
Kalenderinitialisierung griff unmittelbar auf `tui.Calendar` zu. Wenn die
Bibliothek fehlte, brach dieser Scriptabschnitt ab und ließ eine leere Fläche
ohne passenden eigenen Hinweis. Dies ist ein reproduzierter Scriptfragment-
Befund, keine behauptete Produktionsstörung.

## Enger zusätzlicher Zustand

Ein dezenter, servergerenderter Statustext und vorhandener Anwesenheits-GET-Link
bleiben sichtbar, bis die bisherigen synchronen Initialisierungsschritte
erfolgreich zurückgekehrt sind. Ein statischer `noscript`-Text erklärt die
JavaScriptvoraussetzung. Es gibt keine neue Bibliothek, URL, CSSdatei, Route,
Mutation, automatische Wiederholung oder dynamische Nachladefunktion.

Der vorhandene Konstruktor, sämtliche Optionen/Templatefunktionen, Berliner
Wochenanker, Eventdaten und Klickregistrierung bleiben im Erfolgsablauf gleich.
Auch eine leere Woche ist erfolgreich und wird nicht als Ausfall gedeutet.
Die bisherigen `formatDate`-/`showModal`-/`closeModal`-Helfer bleiben global,
insbesondere für den vorhandenen Inline-Schließenknopf. Die statischen bisherigen
Zusatzstyles werden unverändert angefügt. Servernavigation und Buchungs-/
Auswertungsdaten bleiben außerhalb dieser Darstellungsschranke unverändert.

Fehlt ein nutzbarer Konstruktor oder wirft die synchrone Initialisierung einen
Fehler, wird nur ein fest lokalisierter Text angezeigt. Exceptiontext, Pfade,
Stack oder Eingabewerte werden weder reflektiert noch neu geloggt. Ein bereits
erzeugtes Kalenderobjekt wird nach Möglichkeit zerstört, der Kalender-DOM-Bereich
geleert; auch Cleanupfehler dürfen den festen Hinweis und die globalen
Modalhelfer nicht verhindern. Das Leeren betrifft ausschließlich Darstellung,
nicht Buchungen oder andere Seitenteile. Der vorhandene Link öffnet `attendance.php`
mit GET und wiederholt keine Buchung.

Dies erkennt nur fehlende JavaScript-Bibliothek und synchrone Initialfehler.
Ein ausgeblendeter Hinweis belegt keinen echten visuellen Renderpass, keinen
gesunden CDN-/CSS-/CSP-/Netz-/Browserzustand und keine vollständige Ereignis-
oder spätere asynchrone Fehlerabnahme. Bei deaktiviertem/blockiertem JavaScript
bleibt der statische Hinweis/Link als zusätzliche Orientierung vorhanden.
Bestehende Event-/Zeitformatierung wird nicht als Nebenwirkung neu implementiert
oder einer umfassenden Sicherheitsabnahme unterzogen.

## Separat vom Versionspin

Der vorhandene #67-Pin für zwei TOAST-URLpfade ist ausdrücklich ein getrenntes
Paket. Dieses Paket ändert **keine** URL oder Assetdatei, keine Bibliotheksversion,
SRI/CSP-/Host-/Security-/Runtime-/V5-Einstellung oder Integritätsregel. Bei späterer
serieller Integration müssen die dann aktuellen URLbytes erhalten bleiben;
keine stale Komplettdatei über einen inzwischen integrierten Pin kopieren.
Die optionale Laravel-Kalenderansicht nutzt einen anderen vorhandenen Renderer
und wird hier weder verändert noch aktiviert.

## Begrenzter Nachweis

PHP 8.5.12 rendert nur das tatsächliche alte/neue Inline-Scriptfragment sowie
die neue statische Status-/No-JS-Ausgabe mit erfundenen Kalenderdaten und
definitions-only DE-/EN-/zh-Sprachdateien. Keine App-/Header-/Configausführung,
Session, Datenbank oder echte Person wird verwendet. Alle drei Sprachdateien
emittieren dabei weiterhin null Bytes; PHP- und emittierte JavaScript-Syntax
sowie vollständige Quellerhaltung sind getrennt geprüft.

Ein grober Node 24.22.1-VM-/Fake-DOM-Journey reproduziert den alten fehlenden
Libraryfehler, vergleicht erfolgreichen und leeren-Wochen-Ablauf exakt nach
Konstruktoroptionen, Datum, Events, Klickverdrahtung, Stilen und Modalinhalt und
prüft dann fehlenden/falschen Konstruktor, Fehler bei Konstruktion/Datum/Events/
Registrierung/Cleanup, festen Hinweis ohne Fehlercanary sowie wiederholtes
globales Modal-Schließen und statischen GET-/No-JS-Fallback. Es wird kein echtes
TOAST ausgeführt, kein Netz aufgerufen und keine neue kleinteilige Repo-Suite
oder neue CI erzeugt.

Keine tatsächliche Login-/HTTP-/Browser-/Layout-/CDN-/CSP-/Produktionsabnahme,
Remoteaktion, reale Konten/Daten oder Deployment. #67 und #18 bleiben offen für
ihre übrigen Reproduktions-/UI-/Betriebsabnahmen; keine neue Verfügbarkeits- oder
Wartungszusage aus diesem Quellfallback ableiten.
