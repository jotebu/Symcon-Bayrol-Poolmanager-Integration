# Regressionstest 0.2.0-alpha

Ziel: Vor weiteren Features sicherstellen, dass Gateway, Discovery, CSV-Storage und API-Key Browser 2.0 auf IP-Symcon 9.0 gemeinsam stabil funktionieren.

## Voraussetzungen

- IP-Symcon 9.0
- BAYROL PoolManager 5 im lokalen Netz erreichbar
- Modulbibliothek aus `main` aktualisiert
- Bestehende Discovery-CSV-Dateien nicht loeschen

## A. Modul und Storage

- [ ] Modulbibliothek aktualisiert ohne Fehler
- [ ] Discovery-Instanz laesst sich oeffnen
- [ ] Konfigurationsform laedt ohne PHP-Fatal-Error
- [ ] Instanzstatus zeigt `CSV Storage bereit`
- [ ] `CSV Storage pruefen` meldet Storage OK
- [ ] Schema-Version ist 2
- [ ] `api_keys.csv` enthaelt die Spalte `comment`
- [ ] Bestehende API-Key-Daten sind nach Schema-Migration weiterhin vorhanden

## B. PM5-Verbindung und Scan

- [ ] `Verbindung testen` meldet pH-Key empfangen
- [ ] API-Status ist 0
- [ ] Kleiner Scan kann gestartet werden
- [ ] Scan beendet sich ohne Fehler
- [ ] `LastScanId` erhoeht sich
- [ ] `LastScanFoundKeys` ist groesser 0
- [ ] `scans.csv` erhaelt genau einen neuen Eintrag
- [ ] `observations.csv` erhaelt neue Beobachtungen
- [ ] `api_keys.csv` aktualisiert `last_seen` und `last_scan_id`

## C. API-Key Browser 2.0

- [ ] `API-Key Browser laden` zeigt Datensaetze
- [ ] Spalten Fav, Vertrauen, API-Key, Name, Wert, Typ, Device, Obs, Erst gesehen, Zuletzt und Kommentar sind sichtbar
- [ ] Suche nach einem bekannten API-Key reduziert die Treffer korrekt
- [ ] Typfilter `float` funktioniert
- [ ] Mindestvertrauen 100 zeigt nur bekannte Keys
- [ ] Device-Filter `water_values` funktioniert, sofern entsprechende Keys vorhanden sind
- [ ] `Nur Favoriten anzeigen` funktioniert nach gesetztem Favoriten

## D. Zeilenauswahl - Blocker-Test

- [ ] Eine Zeile im API-Key Browser markieren
- [ ] Ohne manuelle Eingabe in `API-Key (Fallback/manuell)` auf `API-Key Details laden` klicken
- [ ] Detailausgabe zeigt exakt den API-Key der markierten Zeile
- [ ] Detailausgabe zeigt aktuellen Wert, Typ, Vertrauen und Beobachtungsanzahl
- [ ] Detailausgabe zeigt die letzten Beobachtungen
- [ ] Andere Zeile markieren und Details erneut laden
- [ ] Detailausgabe wechselt auf den neu markierten API-Key

## E. Favorit und Kommentar ueber markierte Zeile

- [ ] Zeile markieren und `Favorit umschalten` klicken
- [ ] Favoritenstatus in `api_keys.csv` aendert sich fuer genau diesen API-Key
- [ ] Browser neu laden; Favoritenanzeige stimmt
- [ ] Kommentartext eintragen
- [ ] Zeile markieren und `Kommentar speichern` klicken
- [ ] Kommentar steht danach in `api_keys.csv`
- [ ] Kommentar erscheint nach Browser-Neuladen in der Tabelle
- [ ] API-Key Details zeigen denselben Kommentar

## F. Device Layer

- [ ] Device Browser laedt ohne Fehler
- [ ] Wasserwerte werden als `water_values` erkannt, sofern Gruppe 34 gescannt wurde
- [ ] Filterpumpe wird als `filter_pump` erkannt, sofern entsprechende 55.17106-Keys gescannt wurden
- [ ] Poollicht wird als `pool_light` erkannt, sofern entsprechende 55.17102-Keys gescannt wurden
- [ ] Device-Key-Anzahl ist plausibel

## G. Gateway Regression

- [ ] Gateway-Instanz oeffnet ohne Fehler
- [ ] Verbindungstest erfolgreich
- [ ] pH wird aktualisiert
- [ ] Redox wird aktualisiert
- [ ] Pooltemperatur wird aktualisiert
- [ ] Aussentemperatur wird aktualisiert
- [ ] Leitfaehigkeit wird aktualisiert
- [ ] Filterpumpenwerte werden aktualisiert
- [ ] Zyklischer Timer funktioniert weiterhin

## Abnahmekriterium

0.2.0-alpha gilt erst als regressionsgetestet, wenn alle fuer die vorhandene PM5-Konfiguration anwendbaren Punkte erfolgreich sind und keine Daten aus bestehenden CSV-Dateien verloren gehen.

Offene Punkte werden mit exakter Fehlermeldung, betroffenem Testpunkt und Screenshot/Debug-Ausgabe dokumentiert.
