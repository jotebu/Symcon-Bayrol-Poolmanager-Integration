# Regressionstest 0.2.0-alpha

Status: **BESTANDEN**

Getesteter Stand: **0.2.0 Build 5** auf IP-Symcon 9.0 mit BAYROL PoolManager 5.

Ziel: Vor weiteren Features sicherstellen, dass Gateway, Discovery, CSV-Storage und API-Key Browser 2.0 auf IP-Symcon 9.0 gemeinsam stabil funktionieren.

## Voraussetzungen

- [x] IP-Symcon 9.0
- [x] BAYROL PoolManager 5 im lokalen Netz erreichbar
- [x] Modulbibliothek aus `main` aktualisiert
- [x] Bestehende Discovery-CSV-Dateien nicht geloescht

## A. Modul und Storage

- [x] Modulbibliothek aktualisiert ohne Fehler
- [x] Discovery-Instanz laesst sich oeffnen
- [x] Konfigurationsform laedt ohne PHP-Fatal-Error
- [x] Instanzstatus zeigt `CSV Storage bereit`
- [x] `CSV Storage pruefen` meldet Storage OK
- [x] Schema-Version ist 2
- [x] `api_keys.csv` enthaelt die Spalte `comment`
- [x] Bestehende API-Key-Daten sind nach Schema-Migration weiterhin vorhanden

Beobachteter Storage-Stand waehrend des Tests: 99 API-Keys, 1 Scan, 99 Beobachtungen, Schema v2.

## B. PM5-Verbindung und Scan

- [x] `Verbindung testen` meldet pH-Key empfangen
- [x] API-Status ist 0
- [x] Kleiner Scan kann gestartet werden
- [x] Scan beendet sich ohne Fehler
- [x] `LastScanId` erhoeht sich
- [x] `LastScanFoundKeys` ist groesser 0
- [x] `scans.csv` erhaelt genau einen neuen Eintrag
- [x] `observations.csv` erhaelt neue Beobachtungen
- [x] `api_keys.csv` aktualisiert `last_seen` und `last_scan_id`

Beispieltest: Scan-ID 2, 51 erzeugte Keys, 50 Treffer, 77 ms API-Zeit. `34.4034.value` wurde von Scan 1 auf Scan 2 aktualisiert und zeigt zwei Beobachtungen.

## C. API-Key Browser 2.0

Die urspruenglich geplanten separaten Browserfilter wurden waehrend des Regressionstests zugunsten des nativen Symcon-Schnellfilters entfernt. Der Schnellfilter ist der verbindliche Filtermechanismus ab Build 4.

- [x] `API-Key Browser laden` zeigt Datensaetze
- [x] Spalten Fav, Vertrauen, API-Key, Name, Wert, Typ, Device, Obs, Erst gesehen, Zuletzt und Kommentar sind sichtbar
- [x] Schnellfilter nach bekanntem API-Key funktioniert
- [x] Schnellfilter nach Typ `float` / `integer` funktioniert
- [x] Schnellfilter nach Vertrauen, z. B. `100`, funktioniert
- [x] Schnellfilter nach Device `water_values` funktioniert
- [x] Schnellfilter durchsucht API-Key, Name, Wert, Typ, Vertrauen, Device und Kommentar

## D. Zeilenauswahl - Blocker-Test

- [x] Eine Zeile im API-Key Browser markieren
- [x] Ohne manuelle Eingabe in `API-Key (Fallback/manuell)` auf `API-Key Details laden` klicken
- [x] Detailausgabe zeigt exakt den API-Key der markierten Zeile
- [x] Detailausgabe zeigt aktuellen Wert, Typ, Vertrauen und Beobachtungsanzahl
- [x] Detailausgabe zeigt die letzten Beobachtungen
- [x] Andere Zeile markieren und Details erneut laden
- [x] Detailausgabe wechselt auf den neu markierten API-Key

Bestaetigt u. a. mit `34.4034.value`.

## E. Favorit und Kommentar ueber markierte Zeile

- [x] Zeile markieren und `Favorit umschalten` klicken
- [x] Favoritenstatus aendert sich fuer genau diesen API-Key
- [x] Favoritenanzeige stimmt nach erneutem Laden
- [x] Kommentartext eintragen
- [x] Zeile markieren und `Kommentar speichern` klicken
- [x] Kommentar wird in `api_keys.csv` gespeichert
- [x] Kommentar erscheint in der Tabelle
- [x] API-Key Details zeigen denselben Kommentar

Bestaetigt mit dem Kommentar `Regressionstest Build 4` fuer `34.4034.value`.

## F. Device Layer

- [x] Device Browser laedt ohne Fehler
- [x] Wasserwerte werden als `water_values` erkannt
- [x] Filterpumpe wird als `filter_pump` erkannt
- [x] Poollicht wird als `pool_light` erkannt
- [x] Device-Key-Anzahl ist plausibel
- [x] Device Details verwenden direkt die markierte Device-Zeile

Gezielter 55er-Testscan: Scan-ID 4, 15 erzeugte Keys, 15 Treffer, 8 ms API-Zeit.

Bestaetigte Devices:

- `water_values` - 99 Keys
- `pool_light` - 3 Keys, Status-Key `55.17102.status`
- `filter_pump` - 3 Keys, Status-Key `55.17106.status`

Die Device-Detailauswahl wurde in Build 5 korrigiert und mit `filter_pump` erfolgreich nachgetestet.

## G. Gateway Regression

- [x] Gateway-Instanz oeffnet ohne Fehler
- [x] Verbindungstest erfolgreich
- [x] pH wird aktualisiert
- [x] Redox wird aktualisiert
- [x] Pooltemperatur wird aktualisiert
- [x] Aussentemperatur wird aktualisiert
- [x] Leitfaehigkeit wird aktualisiert
- [x] Filterpumpenwerte werden aktualisiert
- [x] Poollichtwerte werden aktualisiert
- [x] Zyklischer Timer funktioniert weiterhin

Der automatische Gateway-Abruf wurde mit einem Aktualisierungsintervall von 60 Sekunden bestaetigt.

## Build-5-Nachtest

- [x] Device Details laden direkt aus markierter Device-Zeile
- [x] `Scan Zusammenfassung` wird automatisch befuellt

Bestaetigter Stand der Scan-Zusammenfassung nach dem Nachtest:

`Scans: 4, API-Keys: 114, Beobachtungen: 263, Devices: 3`

## Ergebnis

Der 0.2.0-alpha Regressionstest ist abgeschlossen und bestanden. Es gingen keine bestehenden Discovery-Daten verloren.

Als naechster Entwicklungsschritt ist der device-orientierte Import bestaetigter Discovery-Ergebnisse in das produktive Gateway vorgesehen. Schreibzugriffe/Aktorsteuerung sind weiterhin nicht Bestandteil dieses Stands.
