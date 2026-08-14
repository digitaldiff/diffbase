# Changelog

## [Unreleased]

## 5.1.2 - 2026-08-12

### Added
- `composerPath` setting (CP-Feld auf der Plugin-Settings-Seite) als manueller Override für den Composer-Update-Endpoint, falls die automatische Suche das Binary auf einem Host nicht findet

### Fixed
- Automatische Composer-Suche verwendete `getenv('HOME')`, das unter PHP-FPM oft leer ist (anders als in einer SSH-Shell) — auf `posix_getpwuid()` umgestellt, das das Home-Verzeichnis zuverlässig direkt aus der OS-User-Datenbank liest
- `/opt/cpanel/composer/bin/composer` (cPanel Composer Manager) zur Standard-Suchliste hinzugefügt

## 5.1.1 - 2026-08-12

### Fixed
- Update endpoint failed on hosts with `exec()` disabled — switched to `Symfony\Process` (`proc_open()`) with automatic composer binary detection

## 5.1.0 - 2026-08-12

### Added
- `/api/info` liefert jetzt einen `users`-Block (`total`, `admins`, `pending`, `suspended`, `locked`, `last_admin_login`) — bewusst ohne Namen/E-Mail, da der Endpoint nur über einen statischen Key geschützt ist
- Update-Endpoint (`UpdateController::actionComposerUpdate`): `Access-Control-Allow-Origin: https://flow.diff.ch` gesetzt, damit das zentrale Monitoring-Dashboard den Update-Trigger per AJAX auslösen und das Ergebnis direkt inline anzeigen kann, statt in einem neuen Tab zu landen
- `MessageWidget` now renders with a light orange background (`#ffe9d7`) to visually stand out on the dashboard
- `NewsWidget` and `TechWidget` support a configurable `offset` setting to display different entries when multiple instances are used on the dashboard
- Sites in API response now include `reachable` (bool) and `http_status` (int) fields — a HEAD request is made to each site's `base_url` to detect if the frontend is accessible independently of the plugin/backend
- `NewsWidget` and `TechWidget` now display the entry title as the widget header (parsed from remote response)

### Fixed
- `getPluginsInfo()`: 5 Felder (`edition`, `has_cp_settings`, `license_key_status`, `is_trial`, `update_available`) lieferten für jedes Plugin immer `null`/`false` wegen einer nie definierten Variable `$pluginInfo` — entfernt (die Update-Info steht bereits korrekt unter `updates.plugins`)
- `mail.transport_settings` war immer leer, weil der `switch` auf `'smtp'`/`'gmail'`/`'sendmail'` prüfte, `transportType` im Project Config aber der volle Klassenname ist (z.B. `craft\mail\transportadapters\Sendmail`) — Vergleich auf die tatsächlichen Klassennamen umgestellt
- `php`-Block: `memory_usage`/`peak_memory` entfernt (spiegelten nur den Verbrauch des kurzlebigen API-Requests selbst, kein aussagekräftiges Signal)
- Update-Endpoint (`UpdateController::actionComposerUpdate`): Ungültiger/fehlender API-Key lieferte HTTP 200 statt 401 — `asJson()` von Yii akzeptiert keinen zweiten Statuscode-Parameter, dieser wurde stillschweigend verworfen. Statuscode wird nun explizit über `Craft::$app->getResponse()->setStatusCode(401)` gesetzt
- Update-Endpoint: `success` wurde fälschlich `true` gemeldet, wenn `shell_exec()` deaktiviert war oder fehlschlug (`null`-Rückgabe führte dazu, dass `str_contains()` `false` lieferte) — auf `exec()` mit echtem Exit-Code umgestellt, `success` basiert nun auf `$exitCode === 0`
- Queue: Alle Job-Counts (`total_jobs`, `waiting_jobs`, `reserved_jobs`, `failed_jobs`) lieferten immer `0` — `method_exists()`-Checks auf Queue-Methoden ersetzt durch direkte `yii\db\Query`-Abfragen auf `{{%queue}}` (analog zu `getRecentFailedJobs`)
- Queue: `failed_jobs` lieferte immer `0`, weil `createCommand()` keine Query-Builder-Methoden hat — ersetzt durch die nativen `getTotalFailed()`, `getTotalWaiting()`, `getTotalReserved()`, `getTotalJobs()` API-Methoden von `craft\queue\Queue`
- Queue: Spaltenname `timeFailed` korrigiert zu `dateFailed` (korrekter Craft-Spaltenname) in `getRecentFailedJobs()`
- Queue: Raw-SQL in `getRecentFailedJobs()` auf `yii\db\Query` umgestellt (statt `Command`)

## 1.0.4 - 2026-04-23

### Added
- Marker.io Bug-Reporting-Tool global im Control Panel registriert
- Tool ist auf allen Backend-Seiten verfügbar für schnelles Feedback und Bug-Reports

## 1.0.3 - 2026-04-23

### Fixed
- File-Upload im SupportWidget funktioniert jetzt korrekt (`enctype="multipart/form-data"` hinzugefügt)
- Status-Meldungen (Erfolg/Fehler) werden im Widget korrekt angezeigt
- Korrekte Action-URL im Widget verwendet
- Redirect-Logik verbessert mit Fallback zum Dashboard
- File-Accept-Filter hinzugefügt für bessere UX
- Hilfetexte für Upload-Feld hinzugefügt

## 1.0.2 - 2026-04-23

### Added
- Dateigrößen-Validierung (max. 10 MB)
- Ausführliches Logging für File-Upload und E-Mail-Versand
- Anhang-Informationen werden in der E-Mail angezeigt (Name, Typ, Größe)
- Automatisches Cleanup: Temporäre Dateien werden nach dem Versand gelöscht

### Fixed
- File-Upload verbessert mit besserer Fehlerbehandlung
- Sichere Dateinamen (Sanitization) um Sicherheitsprobleme zu vermeiden
- Temp-Verzeichnis wird automatisch erstellt, falls nicht vorhanden
- Korrekte Content-Type und Dateinamen für E-Mail-Anhänge

## 1.0.1 - 2026-04-23

### Added
- Support-E-Mail-Funktion mit Dateianhang-Support
- Route für `diffbase/support/send-email` registriert (nicht `diff-base`!)
- Test-Template unter `/support-test` zum Testen des E-Mail-Versands
- System-E-Mail-Absender aus ProjectConfig verwendet

### Fixed
- 404 Fehler bei Support-Controller behoben durch Registrierung der Route und erlaubtem anonymen Zugriff
- Korrekte Action-URL ist `{{ actionUrl('diffbase/support/send-email') }}`, nicht `diff-base`
