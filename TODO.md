# TODO - Bayrol PoolManager IP-Symcon Modul

Arbeitsprinzip: Erst Lauffaehigkeit, dann Struktur, dann Komfortfunktionen.

## 0.1.0-alpha - Basis lauffaehig machen

- [x] Repository ueber Module Control installierbar machen
- [x] IP-Symcon Library-Metadaten bereitstellen
- [x] Instanzklasse korrekt laden
- [x] Verbindungstest bereitstellen
- [x] Zyklische Aktualisierung bereitstellen
- [x] Debugmodus bereitstellen
- [x] Statusmeldungen bereitstellen
- [x] Erste Messwerte anzeigen
- [x] Filterpumpenstatus anzeigen
- [x] Poollichtstatus anzeigen
- [ ] Zusatzinformationen sicher auslesen
- [ ] Installation mit sauberem Testprotokoll dokumentieren
- [ ] Changelog auf finalen Alpha-Stand bringen

## 0.1.1-alpha - Stabilitaet und Diagnose

- [ ] API-Antwortzeit anzeigen
- [ ] Anzahl empfangener Datenpunkte anzeigen
- [ ] Letzte erfolgreiche Aktualisierung separat anzeigen
- [ ] Rohdaten-Ausgabe fuer konfigurierbare Zusatz-API-Keys bereitstellen
- [ ] Fehlermeldungen fuer Anwender verstaendlicher machen
- [ ] Debug-Ausgaben strukturieren
- [ ] Verhalten bei nicht erreichbarem PM5 testen
- [ ] Verhalten bei leerer oder falscher IP testen

## 0.2.0-alpha - Datenpunkte erweitern

- [ ] Weitere bestaetigte Messwerte aufnehmen
- [ ] Firmware-/Versionsinformationen aufnehmen, sobald API-Keys bestaetigt sind
- [ ] Seriennummer/Geraeteinformationen aufnehmen, sobald API-Keys bestaetigt sind
- [ ] Betriebszustaende und Warnungen erfassen
- [ ] Alle bekannten Aktoren lesend abbilden
- [ ] Datentypen und Einheiten pruefen

## 0.3.0-alpha - Bedienbarkeit verbessern

- [ ] Variablenprofile verfeinern
- [ ] Objektbaum sinnvoll gruppieren
- [ ] Konfigurationsformular verbessern
- [ ] Dokumentation fuer Anwender erweitern
- [ ] Screenshots fuer README erstellen

## 0.4.0-alpha - Discovery / Explorer

- [ ] Discovery-Konzept aus Reverse-Engineering-Repository uebernehmen
- [ ] Sicheren Explorer-Modus fuer unbekannte Keys planen
- [ ] Gefundene Keys als Rohdaten anzeigen
- [ ] Keine Schreiboperationen im Explorer ausfuehren

## 0.5.0-alpha - Aktorsteuerung vorbereiten

- [ ] Schreib-API vollstaendig verifizieren
- [ ] Sicherheitskonzept fuer Schreibzugriffe definieren
- [ ] Aktorsteuerung nur fuer bestaetigte Befehle aktivieren
- [ ] Schreibzugriffe standardmaessig deaktiviert lassen

## 1.0.0 - Stabile Version

- [ ] Vollstaendige Dokumentation
- [ ] Saubere Release-Notes
- [ ] Getestete PM5-Firmwareversionen dokumentieren
- [ ] Bekannte Einschraenkungen dokumentieren
- [ ] Modulstruktur ggf. refactoren, wenn 0.x stabil laeuft
