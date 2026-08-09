# Changelog

## 0.2.0 - Discovery Studio und Gateway-Bereinigung

### Gateway

- Produktives Modul `BayrolPoolManager` fuer den laufenden PM5-Betrieb
- Stabile lokale HTTP/JSON-Kommunikation mit dem PM5
- Zyklische Aktualisierung der bekannten Mess- und Statuswerte
- Bestaetigte Werte fuer pH, Redox, Pooltemperatur, Aussentemperatur und Leitfaehigkeit
- Bestaetigte Status-/Textwerte fuer Poollicht und Filterpumpe
- Verbindungstest, API-Status, Antwortzeit und Fehlerdiagnose
- Alte experimentelle Discovery-/Explorer-Funktionen aus dem Gateway entfernt

### Discovery

- Separates Modul `BayrolDiscovery` als lesendes PM5 Reverse-Engineering-Werkzeug
- CSV-Storage ohne SQLite-/PDO-Abhaengigkeit
- Automatische CSV-Initialisierung unter `user/BayrolDiscovery`
- CSV-Schema-Version 2 mit Header-Migration
- Scan-Historie in `scans.csv`
- API-Key-Datenbank in `api_keys.csv`
- Beobachtungshistorie in `observations.csv`
- Device-Zuordnung in `devices.csv` und `device_keys.csv`
- API-Key Browser 2.0 mit Symcon-Schnellfilter
- Schnellfilter ueber API-Key, Name, Wert, Typ, Vertrauen, Device und Kommentar
- API-Key Detailansicht mit Beobachtungshistorie
- Favoriten-Umschaltung fuer markierte API-Key-Zeilen
- Kommentare fuer markierte API-Key-Zeilen
- Device Browser mit Key-Anzahl, Status-Key und Value-Key
- Device Details direkt ueber markierte Device-Zeile
- Erste Device-Klassifizierung fuer `water_values`, `filter_pump`, `pool_light` und `system_values`
- Scan-Zusammenfassung wird automatisch aktualisiert

### Architekturentscheidungen

- Gateway und Discovery sind strikt getrennt.
- Discovery ist read-only gegenueber dem PM5.
- SQLite wurde verworfen, da Symcon 9 auf der getesteten Raspberry-Pi-Installation in der eingebetteten PHP-Umgebung kein PDO/SQLite bereitstellt.
- CSV ist der Standard-Storage fuer Discovery-Daten.
- Discovery-Daten werden ausserhalb des Modul-Quellverzeichnisses gespeichert und bleiben bei Modulupdates erhalten.

### Regression

0.2.0 wurde auf IP-Symcon 9.0 mit BAYROL PoolManager 5 regressionsgetestet. Bestaetigt wurden:

- Modul-/Storage-Initialisierung und Schema v2
- PM5-Verbindung und API-Status 0
- Scan-Historie und Beobachtungspersistenz
- API-Key Browser 2.0 inklusive Schnellfilter
- API-Key-Zeilenauswahl, Favoriten und Kommentare
- Device Layer fuer Wasserwerte, Poollicht und Filterpumpe
- Device-Detailauswahl ueber markierte Zeile
- automatische Scan-Zusammenfassung
- produktiver Gateway-Abruf aller bekannten Werte
- zyklischer Gateway-Timer

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
