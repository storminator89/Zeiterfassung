# Optional MCP Apps UI (#425)

`App\Mcp\Resources\ChronoApp` liefert `ui://chrono/attendance` als
`text/html;profile=mcp-app`. Die Erweiterung bleibt standardmäßig aus:
`CHRONO_MCP_APPS_ENABLED=false`. Der lokale MCP-Kern muss separat zugelassen
und konfiguriert sein. Diese Oberfläche registriert keine HTTP-Route und
schaltet die bestehende Chrono-Oberfläche nicht um.

## Protokoll und Grenzen

Die Oberfläche verwendet ausschließlich Laravels mit `laravel/mcp` **v1.1.2**
gebündelten `createMcpApp`-Client aus `<x-mcp::app>`. Der Host vermittelt alle
Aufrufe mit `app.callServerTool`; Browser-Fetch, direkte APIaufrufe und
zusätzliche Backendaktionen gibt es nicht. Eine APIadresse oder ein Bearertoken
wird nicht an die Ansicht übergeben. Es werden keine CDN-Bibliotheken geladen.

`AppsCapability::available()` muss sowohl bei der Ressourcenregistrierung als
auch beim Ressourcenabruf erfolgreich sein. Zusätzlich zur lokalen Freigabe
muss der moderne Client bei jeder Anfrage unter
`_meta["io.modelcontextprotocol/clientCapabilities"].extensions["io.modelcontextprotocol/ui"].mimeTypes`
den Wert `text/html;profile=mcp-app` angeben. Das entspricht Laravels
`MetaKey::CLIENT_CAPABILITIES` und der MCP-Apps-Erweiterungskennung.
Die klassischen Textwerkzeuge benötigen keine Apps-Fähigkeit. Der SDK-Server
speichert die Fähigkeiten eines klassischen `initialize`-Aufrufs nicht als
Clientzustand; allein diese klassische Aushandlung aktiviert die UI daher nicht.

Die Ressource setzt auf `resources/list` und jedem `resources/read`-Inhalt eine
explizite leere CSP-Allowlist für Verbindungen, Ressourcen, Frames und Basis-URLs
sowie leere Browserberechtigungen. Die leeren Felder werden explizit erhalten,
weil Laravels v1.1.2-Builder leere Listen sonst entfernt. Eine zusätzliche
HTML-CSP sperrt Netzwerkzugriffe, externe Ressourcen, Formnavigation und
verschachtelte Frames. Nur das gebündelte Inline-JavaScript und die lokalen
Inline-Stile werden benötigt. Die verpflichtende Isolation und endgültige
CSP-Durchsetzung gehören zum MCP-Apps-Host.

## Eigene Daten und Bestätigung

Vier per Tastatur bedienbare Tabs zeigen eigene Anwesenheit, gespeicherte
Zeiteinträge, Korrekturstatus und gespeicherte Arbeitsmodelle. Das Arbeitsmodell
kann für ein Datum gewählt werden; daneben steht seine gespeicherte
Versionenliste. Listenseiten verwenden `limit: 25` und ausschließlich den vom
Kern gelieferten `pagination.next_offset`. Es gibt keine eigene Berechnung
von Salden, Ansprüchen oder Feiertagsgutschriften. Texte sind auf Deutsch,
Englisch und Chinesisch vorhanden. Daten werden ausschließlich mit
`textContent` ausgegeben; IDs bleiben Dezimalstrings.

Pausen-/Überlappungswarnungen und überlappende Datensatz-IDs aus dem exakt
bestätigten letzten Arbeitsende bleiben mit dessen Ereignis-/Datensatz-ID
sichtbar, auch nach erneuten Anwesenheitsabrufen. Ein weiterer bestätigter
Endebeleg ersetzt diesen Hinweis; ein Profilwechsel entfernt ihn. Damit gehen
APIhinweise zu vorläufigen Summen beim Statusaktualisieren nicht verloren.

Vor der Aktionsprüfung werden `chrono_identity` und
`chrono_attendance({workflow: true})` frisch gelesen. Das Dialogfenster zeigt
Profil, Aktion, exakte Datensatz-ID, Ereignis-ID und Profilbindung. Vor dem
ersten Senden werden Identität und Anwesenheit nochmals geprüft. Eine Änderung
von Profil, Status oder Datensatz verwirft diese erste Bestätigung. Ein Ende
während einer Pause verlangt ein zusätzliches ausdrückliches Häkchen.
Aktualisierung, Sprachwechsel und Host-Toolbenachrichtigungen schließen einen
offenen Bestätigungsdialog.

Jede erstmalig gesendete Aktion besitzt eine sichere UUID. `event_id` und
`confirmed_event_id` sind identisch; Folgeaktionen haben außerdem identische
`expected_record` und `confirmed_record_id`. Profilbindungen werden zwischen
erfolgreichen Kernantworten verglichen. Ein Profilwechsel verwirft angezeigte
Daten, stoppt weitere Aktionen und verlangt ein erneutes Öffnen mit dem
gewünschten Profil.

Sobald eine Aktion gesendet wurde, bleiben ihr Werkzeug und ihre Argumente bei
jedem unbestätigten Ergebnis erhalten. Neue Aktionen sind dann gesperrt.
Es gibt keine automatische Wiederholung. Die Person kann den Serverstatus
aktualisieren und nach erneuter Prüfung exakt dasselbe Ereignis erneut senden.
Ein inzwischen veränderter Anwesenheitsstatus verhindert diese Wiederholung
nicht: Ein bereits gespeicherter Erfolg muss unter derselben Ereignis-ID
abrufbar bleiben. Nur ein erfolgreicher Kernbeleg mit derselben Ereignis-ID,
Profilbindung, dem zur Aktion gehörenden Ergebnisstatus, einer positiven exakten
betroffenen Datensatz-ID und `outcome: confirmed` entfernt die offene Aktion.
Bei Folgeaktionen muss die betroffene ID mit der bestätigten ursprünglichen ID
übereinstimmen; auch beim Arbeitsende bleibt sie im Beleg erhalten. Auch eine
spätere negative Vorprüfung beseitigt die frühere Unsicherheit nicht.

Wenn der Host `sessionStorage` erlaubt, wird ausschließlich die offene Aktion
mit Werkzeug, Bestätigungsargumenten und Profilbindung dort gesichert. Keine
Token und keine Listeninhalte werden gespeichert. Bei einem opaken Sandbox-Ursprung
kann dieser Speicher gesperrt sein; dann gilt die Sicherung nur bis zum Ende
der laufenden iframe-Instanz. Bei einer wiederhergestellten offenen Aktion wird
bereits die erste erfolgreiche Antwort gegen deren gespeicherte Profilbindung
geprüft. Eine beschädigte vorhandene offene Aktion sperrt
weitere Aktionen und verweist zum Abgleich auf die Textwerkzeuge.

## Isolierte Browserprüfung

Am 30.10.2026 wurde die Ansicht mit Chromium und dem echten gebündelten
v1.1.2-SDK in einem `sandbox="allow-scripts"`-iframe gegen einen synthetischen
JSON-RPC-Host geprüft. Bestanden:

- `ui/initialize` und Werkzeugaufrufe über die echte SDK-Brücke; keine externen
  Ressourcen und keine JavaScriptfehler;
- doppelte Bestätigung erzeugt einen Schreibaufruf; IDs oberhalb `2^53` bleiben
  exakt; Profil- und Datensatztexte werden nicht als HTML ausgeführt;
- ein serverseitig ausgeführtes, zunächst unbestätigtes Ereignis sperrt neue
  Aktionen, übersteht Statusaktualisierung und wird mit identischen Argumenten
  trotz geändertem Status erfolgreich wiederholt;
- Arbeitsende während einer Pause ist ohne ausdrückliches Häkchen gesperrt;
- alle vier Tabs, Live-Paginierung, effektives Modell samt Versionen,
  Tastaturnavigation und Deutsch/Englisch/Chinesisch;
- Profilrotation zwischen Dialog und Senden schließt den Dialog, entfernt
  frühere Listendaten und verhindert den Schreibaufruf.
- Widersprüchliche Ergebnisstatus, falsche betroffene IDs sowie fehlende IDs im
  Start-/Endebeleg behalten das offene Ereignis; erst sein passender Beleg
  entfernt es.
- Timeout, eine danach verweigerte Identitätsprüfung, eine später negative
  Schreibantwort und eine verspätete ursprüngliche Antwort behalten Ereignis-ID
  und Argumente unverändert; keine automatische Wiederholung.

Dies belegt die UI und das Protokoll gegen einen synthetischen Host, keine
Freigabe eines bestimmten externen MCP-Clients und keinen Lauf gegen reale
Konten, APIzugänge oder Datenbanken. Die zusammengehörige PHP-/Blade- und
Kernprüfung ist separat auszuführen. Kein Deployment oder Runtimecutover.

Quellen: [Laravel v1.1.2 AppResource](https://github.com/laravel/mcp/blob/v1.1.2/src/Server/AppResource.php),
[gebündelte Blade-Komponente](https://github.com/laravel/mcp/blob/v1.1.2/resources/views/mcp/components/app.blade.php),
[gebündeltes SDK](https://github.com/laravel/mcp/blob/v1.1.2/resources/js/mcp-sdk.js),
[stabile MCP-Apps-Spezifikation 2026-01-26](https://github.com/modelcontextprotocol/ext-apps/blob/main/specification/2026-01-26/apps.mdx).
