# Changelog

## 0.2.0-alpha - Discovery Studio

### Enthalten

- Neues Modul `BayrolDiscovery` als PM5 Reverse-Engineering-Werkzeug
- CSV-Storage ohne SQLite-/PDO-Abhaengigkeit
- Automatische CSV-Initialisierung unter `user/BayrolDiscovery`
- Scan-Historie in `scans.csv`
- API-Key-Datenbank in `api_keys.csv`
- Beobachtungshistorie in `observations.csv`
- Erste Device-Zuordnung in `devices.csv` und `device_keys.csv`
- API-Key Browser mit Suche, Typfilter, Mindestvertrauen und Favoritenfilter
- API-Key Detailansicht mit letzten Beobachtungen
- Favoriten-Umschaltung fuer API-Keys
- Device Browser mit Key-Anzahl, Status-Key und Value-Key
- Device Detailansicht

### Architekturentscheidung

- SQLite wurde verworfen, da Symcon 9 auf Raspberry in der verwendeten PHP-Umgebung kein PDO/SQLite bereitstellt.
- CSV ist ab 0.2.0-alpha der Standard-Storage fuer Discovery-Daten.

## 0.1.0-alpha - Gateway Basis

### Enthalten

- Saubere IP-Symcon-Bibliotheksstruktur
- Modul `BayrolPoolManager`
- Grundkonfiguration fuer Host, Port, Timeout und Updateintervall
- Lesender Zugriff auf erste bekannte PM5-Datenpunkte
- Automatische Variablenanlage fuer erste Mess- und Statuswerte
- Debug-Ausgaben und Statusbehandlung
- Verbindungstest
- Zyklische Aktualisierung

### Nicht enthalten

- Schreibzugriffe/Aktorsteuerung
- Automatischer Import unbekannter Datenpunkte
