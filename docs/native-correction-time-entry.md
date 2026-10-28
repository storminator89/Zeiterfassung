# #50: geführte Berlin-Zeitwahl auch in der nativen Korrekturkarte

Basis Main `c9483797ff8470998fd018042d2e988e3b1f015d`, Baum
`9acde7d4819aee8428e7aa286780964f522537f9`. Der bereits vorhandene
[Laravel-Eingabehelfer](framework/correction-time-entry.md) wird nicht parallel
neu implementiert: Sein vollständiger Scriptbody einschließlich Script-Tag
liegt bytegleich im gemeinsamen `lib/correction_time_helper_script.php`.
Das bestehende Blade-Partial und die native Seite schließen genau diese feste
Datei serverseitig ein. Die Laravel-Ausgabe bleibt bytegleich.

## Bestehender Korrekturweg und Daten bleiben maßgeblich

Nur die vorhandene native Korrekturkarte erhält denselben aufklappbaren Helfer.
Datum und Uhrzeit werden als Europe/Berlin interpretiert, nicht als Gerätezone.
Eine bewusste Übernahme kopiert einen rückgeprüften expliziten ISO-Offset in
das vorhandene manuelle Beginn-/Endefeld. Die doppelte Herbststunde verlangt
Erstes/Zweites Mal; Frühlingslücken werden nicht verschoben. Es gibt keinen
automatischen Submit, Request, Speicherzugriff oder Kontosaldoversprechen.

Die unbenannten Hilfsfelder liegen außerhalb des POST-Formulars. Das Formular
erhält nur eine feste ID und eine standardmäßig nicht gesperrte Feldgruppe für
den bereits vorhandenen Scriptvertrag; feste lokale Styles neutralisieren
deren Browser-Standardrand und -abstände. Namen, Werte, Auswahl, CSRF, Storno,
Pause, Begründung und Submit bleiben unverändert. Nur der ausdrückliche
Übernehmen-Klick verändert das gewählte manuelle ISO-Feld; Storno und andere
Hilfseingaben verändern keine manuellen Werte. Bei ungültiger eigener
Buchungsauswahl bleibt der bestehende Formularschutz erhalten.

Ohne JavaScript, bei fehlender unterstützter Browserzone oder unvollständigem
DOM bleibt der Helfer verborgen; die manuellen ISO-Felder sind weiterhin der
bisherige Weg. Der unveränderte Backendparser und Vier-Augen-Dienst bleiben
alleinige Autorität. Browser-IANA- und Server-Zeitzonendaten können abweichen;
dies ist keine neue Zeitzonen-, Pausen-, Freigabe- oder historische Mappingregel.

## Pfad, Escaping und CSP

Die Includeziele sind feste Quellpfade: nativ relativ zu `__DIR__`, im Blade
relativ zum Framework-`base_path`. Keine Eingabe bestimmt Pfad oder Scriptbytes;
es gibt keinen neuen öffentlichen JS-/CDN-/Assetpfad. Der vorhandene
Laravel-Dockervertrag kopiert bereits `lib/` neben `framework/`; dafür wird
kein Build-/Host-/Packagingvertrag geändert. Die gemeinsame Datei enthält
ausschließlich den bisherigen statischen Scripttext, keine PHP-Anweisung,
Konfiguration, Datenbank-, Auth- oder Dateisuchlogik. Direkte `lib/`-Requests
bleiben nach den vorhandenen nativen Routingregeln ausgeschlossen.

Neue native Markupwerte kommen ausschließlich aus den festen Feldern
`start`/`end` und ihren festen Beschriftungen und werden mit `h()` escaped.
Das unveränderte Script setzt Hinweise und Optionsbeschriftungen mit
`textContent`; kein neuer HTML-Parser für Antragswerte. Vorhandenes Escaping
der Buchungsauswahl und Anträge bleibt erhalten.

Es wird keine CSP gelockert oder umgeschrieben. Native Sicherheitsheader und
Laravel-Responsemiddleware bleiben bytegleich; die bestehenden Korrekturseiten
haben dort keine zusätzliche `script-src`-Beschränkung für diesen bisherigen
Inlineweg. Strengere externe Deployment-CSPs sind hier nicht untersucht; wird
JavaScript blockiert, bleibt der manuelle Weg maßgeblich. Es wird weder eine
Nonce-/Hashzulassung noch eine Browser-CSP-Abnahme behauptet.

## Begrenzter Quell-/Modellnachweis

Die vollständige additive Rückprojektion der nativen Seite ergibt bytegenau
Main, einschließlich aller SQL-/POST-/Transaktions-/Auswahl-/Filter-/Karten-
und Vieraugensichtzweige. Der ausgelagerte Scripttext und die durch das
kompilierte Blade-Partial ausgegebenen Bytes entsprechen exakt dem bisherigen
Partial. Ein neuer Laravel-Anwendungsbootstrap oder Composerinstall ist nicht
erforderlich; die vorhandenen gelockten Compilerdefinitionen werden benutzt.

Ein vorhandener grober erfundener DOM-/Scriptjourney mit tatsächlichem
`Intl`-Berlin prüft explizites Übernehmen, beide Herbstoffsets, Frühlingslücke,
ungültiges Datum, Storno und manuellen Rückfall. Die zusätzlichen nativen
Markup-/Formvertragskontrollen prüfen unbenannte Felder außerhalb des Formulars
und dessen erhaltene Werte. Notwendige Node-24.22.1-/PHP-8.5.12-/kompilierte
Blade-Syntax und Diffcheck werden getrennt dokumentiert.

Keine reale Datei/DB mit Betriebsdaten, Konten, Anträge, Keys oder Secrets;
kein nativer Appbootstrap, Login, HTTP-/Session-/CSRF-/Browser-/Layoutnachweis,
kein Serverstart, neue Testsuite, CI-Start, Runtimeflag, Deployment oder
Remoteänderung durch dieses lokale Paket. Das ist insbesondere keine echte
Browserabnahme. #50 bleibt partiell/offen; Vertretung/Eskalation und weitere
Abnahmen sind dadurch nicht erledigt.
