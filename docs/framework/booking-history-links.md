# Eigene Buchung → bestehende Historie (#52 / #79)

Die eigene Arbeitszeitliste bietet neben der unveränderten Korrektur-/Stornoaktion
einen kompakten schreibfreien Historiensprung. Er verwendet ausschließlich die
positive Integer-ID der bereits angezeigten eigenen Zeile. `entity=zeiterfassung`
verhindert Verwechslungen mit gleich nummerierten Konten, Modellen oder Anträgen;
`relations=1` nutzt die schon vorhandene begrenzte Bezügeansicht. Es gibt keinen
neuen Personen-, Rollen- oder Quellenparameter und keine zusätzliche Listenabfrage.

Die vorhandene `Interactions::history`-Komposition wird nur als optionales,
standardmäßig falsches Anzeigeattribut an `TimeRecordsPage` weitergereicht.
Bestehende Dreiargument-Controller- und DTO-Aufrufer bleiben kompatibel. Keine
Route oder Capability wird eingeschaltet. Die bestehende FreshWeb-Komposition
bindet History-Navigation und geschützte History-Route gemeinsam erst hinter ihren
unveränderten Gates. Ein URL-Parameter kann das neue Anzeigeattribut nicht setzen.

Beim Öffnen prüft die existierende Historie ihre aktuelle Identität und ihren
unveränderten Eigen-/Admin-/direkten Teamscope erneut. Der neue Link ist keine
Autorisierung und friert keine Berechtigung ein. Die vorhandenen 100er-Keysetseiten,
Bezügegrenzen und ausgewählten Ausgabefelder gelten unverändert. Fehlende Treffer
belegen weder Fehlerfreiheit noch fehlende frühere Änderungen; Bezüge sind keine
Kausalitäts- oder Vollständigkeitszusage. IDs größer als 2^53 werden ohne JavaScript-
Zahlenkonversion als genaue Dezimalzahl übertragen; 0/negative IDs erhalten keinen
neuen Link.

`target="_blank" rel="noopener noreferrer"` öffnet separat, sodass aktuelle
CSV-/andere Formulare nicht für die Navigation verlassen werden müssen. Ein kurzer
DE-/EN-/ZH-Linktext und Hinweis nennen diese Begrenzung. Buchungsdaten, Summen,
Paginierung, Korrekturlink, CSV-Formulargrenze, Leser/SQL, Controller-Kommandopfad,
Auth/Route/Provider, Writer, Assets und Runtime bleiben sonst bytegleich. Die
bewusst zurückgestellten PR197- und PR239-Operationen werden nicht wiederholt oder
anderweitig implementiert.

## Grober Nachweis

Wiederverwendete isolierte Laravel-Komposition mit tatsächlichem Readadapter,
`query_only`-Memoryfixture, HomeController, DTO und kompiliertem Blade: eigene
Zeilen auch für Admin/Supervisor; genaue große ID; 0/negative unterdrückt;
bestehende/default-false/injizierte Capability; fremder Abfrageparameter wirkungslos;
DE/EN/ZH; gleiche Nummer im anderen Auditbereich bzw. fremder Owner nicht als
eigener Treffer; vorhandener Teamfilter und frischer Rollencheck erhalten.
Unveränderte CSV-/Korrekturlinks und Listenclamp bleiben erhalten, null Writeraufrufe
und unveränderte Daten/Schema/Audit. PHP-/Blade-Syntax und vollständige
Pfad-/Modus-/Inverseprüfung ergänzen diesen begrenzten Funktionsnachweis.

Kein echter Browser-, HTTP-, Login-/CSRF-/Session-, V5-Admission- oder
Betriebsnachweis. Insbesondere werden weder Download/Tab-/Fenster-/Fokusverhalten
noch reale historische Vollständigkeit damit abgenommen.
