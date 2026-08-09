# Symcon Bayrol PoolManager Integration

IP-Symcon Modulbibliothek fuer die lokale Integration des BAYROL PoolManager 5 (PM5).

Die Bibliothek enthaelt zwei klar getrennte Module:

- `BayrolPoolManager` - produktives Gateway fuer den laufenden Betrieb
- `BayrolDiscovery` - lesendes Analyse- und Reverse-Engineering-Werkzeug fuer API-Keys und Device-Zuordnungen

## Projektstatus

Aktueller Entwicklungsstand: **0.2.0**.

Der Stand wurde auf IP-Symcon 9.0 mit einem BAYROL PoolManager 5 regressionsgetestet. Der produktive Gateway-Betrieb und das Discovery-Modul arbeiten getrennt, verwenden aber dieselbe bekannte PM5-WebGUI-API.

### Gateway

Das Modul `BayrolPoolManager` bietet aktuell:

- lokale HTTP/JSON-Kommunikation mit dem PM5
- Verbindungstest
- zyklische Aktualisierung
- pH
- Redox
- Pooltemperatur
- Aussentemperatur T3
- Leitfaehigkeit
- Poollicht-Status und Text
- Filterpumpen-Status, Betriebsart und Text
- Status-, Fehler- und Antwortzeit-Diagnose

Die frueher im Gateway enthaltenen experimentellen Discovery-/Explorer-Funktionen wurden entfernt. Reverse Engineering findet ausschliesslich im separaten `BayrolDiscovery`-Modul statt.

### Discovery

Das Modul `BayrolDiscovery` bietet aktuell:

- read-only Zugriff auf die PM5-WebGUI-API
- konfigurierbare API-Key-Scans
- CSV-Storage unter `user/BayrolDiscovery`
- Schema-Version 2 mit automatischer Header-Migration
- Scan-Historie und Beobachtungshistorie
- API-Key Browser 2.0 mit Symcon-Schnellfilter
- Suche ueber API-Key, Name, Wert, Typ, Vertrauen, Device und Kommentar
- API-Key Detailansicht mit Historie
- Favoriten und Kommentare pro API-Key
- Device Browser und Device-Detailansicht
- automatische Erstklassifizierung fuer `water_values`, `filter_pump`, `pool_light` und `system_values`
- automatisch gepflegte Scan-Zusammenfassung

SQLite/PDO wird nicht verwendet, da die getestete eingebettete Symcon-PHP-Umgebung diese Erweiterungen nicht bereitstellt.

## Getestete Umgebung

- IP-Symcon 9.0
- Raspberry Pi
- BAYROL PoolManager 5 / PM5
- bekannte getestete Firmware: `v240729-M1 / 9.1.1`

Andere BAYROL-Geraete koennen eine aehnliche WebGUI-API verwenden, sind derzeit aber nicht freigegeben.

## Repository-Struktur

```text
library.json
BayrolPoolManager/
  module.json
  module.php
BayrolDiscovery/
  module.json
  module.php
README.md
CHANGELOG.md
LICENSE
CONTRIBUTING.md
SECURITY.md
docs/
  regression-0.2.0-alpha.md
```

## Installation in IP-Symcon

1. IP-Symcon Management Console oeffnen.
2. Module Control oeffnen.
3. Folgende Repository-URL hinzufuegen:

```text
https://github.com/jotebu/Symcon-Bayrol-Poolmanager-Integration.git
```

4. Modulbibliothek aktualisieren.
5. Fuer den produktiven Betrieb eine Instanz `Bayrol PoolManager 5` anlegen.
6. PM5 Host/IP konfigurieren und Verbindung testen.
7. Fuer Analysezwecke optional eine separate Instanz `BayrolDiscovery` anlegen.

## Bestaetigte API-Keys

| API-Key | Bedeutung | Status |
|---|---|---|
| `34.4001.value` | pH | bestaetigt |
| `34.4022.value` | Redox | bestaetigt |
| `34.4033.value` | Pooltemperatur | bestaetigt |
| `13.16507.text2` | Aussentemperatur T3 | bestaetigt |
| `13.16509.text1` | Leitfaehigkeit | bestaetigt |
| `55.17102.status` | Poollicht Status | bestaetigt |
| `55.17102.value` | Poollicht Text | bestaetigt |
| `55.17106.status` | Filterpumpe Status | bestaetigt |
| `55.17106.opmode` | Filterpumpe Betriebsart | bestaetigt |
| `55.17106.value` | Filterpumpe Text | bestaetigt |

## Discovery-CSV-Dateien

Das Discovery-Modul verwendet semikolon-getrennte CSV-Dateien im Symcon-User-Verzeichnis:

- `meta.csv`
- `scans.csv`
- `api_keys.csv`
- `observations.csv`
- `devices.csv`
- `device_keys.csv`
- `tags.csv`
- `key_tags.csv`

Die Dateien liegen bewusst ausserhalb des Modul-Quellverzeichnisses und bleiben bei Modulupdates erhalten.

## Naechster Entwicklungsschritt

Nach dem abgeschlossenen 0.2.0-Regressionstest ist als naechste Ausbaustufe der **device-orientierte Import in das produktive Gateway** vorgesehen. Ziel ist, bestaetigte Discovery-Ergebnisse nicht als rohe API-Keys, sondern als fachliche Objekte wie Wasserwerte, Filterpumpe und Poollicht zu uebernehmen.

Schreibzugriffe auf PM5-Aktoren bleiben bis zur getrennten sicheren Analyse der Write-API ausserhalb des produktiven Funktionsumfangs.

## Regressionstest

Der feste Regressionstest fuer 0.2.0 ist unter `docs/regression-0.2.0-alpha.md` dokumentiert. Der Test wurde erfolgreich mit Gateway, CSV-Storage, API-Key Browser 2.0, Device Layer und zyklischem Gateway-Polling durchgefuehrt.

## Sicherheit

Die verwendete PM5-WebGUI-API ist eine undokumentierte lokale Schnittstelle. Das Modul sollte nur in einem vertrauenswuerdigen lokalen Netzwerk eingesetzt werden. Discovery fuehrt ausschliesslich lesende PM5-Abfragen aus.

## Lizenz

MIT License. Siehe `LICENSE`.
