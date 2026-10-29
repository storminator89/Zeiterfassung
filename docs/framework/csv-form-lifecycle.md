# CSV-Zeitraum: Formular bleibt außerhalb des Listenfragments (#53 / #79)

Das vorhandene Zeitraumformular lag im per `main.js` ersetzten
`timeRecordsContainer`. Ein erfolgreicher Seiten-/Reviewfilterwechsel oder eine
Listenaktualisierung nach einer Buchung entfernte deshalb bereits eingegebene
Datumswerte. Ein reiner stiller Attendance-Statusrefresh ersetzt die Tabelle
hingegen nicht.

Das unveränderte GET-Zeitraumformular wird jetzt einmal auf der Attendance-Seite
außerhalb dieses Containers eingebunden. Es erscheint nur mit bestehender
Home-Interaktion **und** vorhandener `chrono.export-csv`-Route; das ist dieselbe
Routenverfügbarkeit, die das bisherige Listenfragment für den CSV-Export nutzt.
Die Anzeige ist keine Autorisierung: die geschützte Route und der eigene
Lesesnapshot bleiben maßgeblich. Im AJAX-Fragment bleibt nur der vorhandene
Gesamtexportlink, kein zweites Zeitraumformular.

Beide GET-Downloads öffnen mit `target="_blank" rel="noopener noreferrer"`
separat, damit auch eine Fehlerantwort die aktuelle Eingabeseite nicht ersetzt.
Ein kurzer DE-/EN-/ZH-Hinweis kündigt dies an. Die Attribute entsprechen dem
[HTML-Formularstandard](https://html.spec.whatwg.org/multipage/forms.html#attr-form-rel).
Es gibt keinen Script-Opener, Browser-/Sessionspeicher, automatischen Retry oder
neue Übermittlung von Akteur-/Personenauswahl.

Action, GET, `period=1`, Feldnamen/-IDs, `type=date`, `required` und Grenzen
0001-01-01 bis 9999-12-31 bleiben gleich. Serverseitige Datumsvalidierung,
Berliner Starttagszuordnung samt vollständiger Nachtschicht, vollständige
Bestandsqualitätsprüfung **vor** Zeitraumfilter, eigene Daten, CSV-Schutz und
Fehlerstatus werden nicht geändert. Keine Berechnung, Speicherung oder
Writer-/Runtimeöffnung; auch der bestehende #239-Denial bleibt unberührt.

## Grobe Prüfung und Grenzen

Die tatsächlichen Blade-Views mit den bestehenden DTOs werden kompiliert und in
einer isolierten Laravel-Komposition gerendert. Strukturprüfung: genau ein
Zeitraumformular außerhalb des Containers; keine verschachtelten Formulare;
gleiche Validierungsattribute; Route-/Home-Ausblendung; aktiver Gesamtexport
weiterhin nur bei bestehendem Fragmentflag; neuer Kontext ohne Opener.
Mehrfacher modellierter Austausch der Containerkinder durch echte gerenderte
Fragmente bewahrt die außerhalb liegenden Datumsfelder und deren gesetzte
Werte. Das alte Fragment dient als strukturelles Gegenbeispiel. DE/EN/ZH und die
vorhandenen Abwesenheits-/Feiertags-/Own-Open-Blöcke bleiben additiv erhalten.

Dies ist eine begrenzte Komponenten-/DOM-Strukturprüfung, **keine echte
Browserabnahme** von Download, Fehlerantwort, Tab-/Fensterverhalten, Date-Picker,
Fokus oder Back/Forward. Die Browserentscheidung über Tab oder Fenster bleibt
bestehen. Ein vollständiges Neuladen oder Verlassen der Attendance-Seite wird
nicht abgefangen; es gibt keine dauerhafte Entwurfsaufbewahrung.
