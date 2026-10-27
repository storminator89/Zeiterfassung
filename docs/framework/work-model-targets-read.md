# #78 – Zieloptionen für die vorhandene Arbeitsmodellansicht

`ChronoReads::workModelTargets(): array` ist eine parameterlose Ergänzung für die
vorhandene Admin-Auswahl in `work_models.php`, keine Schreibfreigabe oder neue
Benutzersuche. Die bestehende `snapshot`-Grenze lädt den aktuellen Guard-Akteur
auf demselben PDO in einer `DEFERRED`-Lesetransaktion erneut. **Vor** dem Lesen der
Benutzerliste muss seine aktuelle Rolle `admin` sein; andernfalls wird
`AuthorizationException('Nur die Verwaltung darf Arbeitszeitmodelle festlegen.')`
ausgelöst. Veraltete Rollen im Guard erweitern diese Berechtigung nicht.

Für Admins wird exakt `SELECT id,username FROM users ORDER BY username` als
assoziative Rohzeilen zurückgegeben. Keine zusätzlichen Konto-, Rollen-, Team-
oder Authquellenfilter, Casts, Defaults, HTML oder Escaping. Der spätere Controller
ruft die Methode nur für die Admin-Auswahl auf; die Dienstgrenze prüft unabhängig.
`workModels()` und dessen eigene/Admin-Historie bleiben unverändert.

Keine neuen Routen, UI-, Provider-, Writer-, Datenbank- oder Hoständerungen.
Die benannte Verbindung bleibt ohne gesonderte Zulassung unkonfiguriert.

```sh
cd framework
php tools/rehearse-work-model-targets.php
```

Ein grober anonymer Memory-Nachweis vergleicht die Adminliste mit der nativen
SQL-Projektion, verweigert einen Nichtadmin trotz veralteter Guard-Adminrolle und
prüft unveränderten SourceSnapshot inklusive Journal/Sequenzen, native
Audit-/Sessionglobals und abgeschlossenes Lesesnapshot. Keine HTTP-/Rollenjourney,
echte Konten, Datei-DB, Credentials, Ports oder neue CI-Läufe.

Am 27.10.2026 unter PHP 8.5.12 / Laravel 13.38.0 bestanden: grober Memory-
Nachweis, Syntax der drei betroffenen PHP-Dateien, Composer-/Plattformprüfung
und `git diff --check`. Eine mechanische Quellgegenprüfung erhält sämtliche
bestehenden Adaptermethoden einschließlich `workModels()` und der privaten
Snapshotgrenze bytegenau. Keine Laufzeit-/UI-Abnahme wird behauptet.
