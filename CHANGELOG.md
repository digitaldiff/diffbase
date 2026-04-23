# Changelog

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
