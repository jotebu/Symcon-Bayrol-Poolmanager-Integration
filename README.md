# Symcon Bayrol PoolManager Integration

IP-Symcon Modul fuer den BAYROL PoolManager 5 / PM5.

Dieses Projekt integriert den PoolManager lokal ueber die WebGUI-JSON-Schnittstelle. Es wird kein MQTT, keine BAYROL-Cloud und kein Modbus benoetigt.

## Status

Fruehe Entwicklungsfassung / Proof of Concept.

Bereits getestet:

- Lokale HTTP-Verbindung zum PM5
- JSON-API unter `/cgi-bin/webgui.fcgi?sid=...`
- Login per JSON-POST
- Auslesen von Live-Werten in IP-Symcon

Bekannte Testwerte:

| API-Key | Bedeutung |
|---|---|
| `34.4001.value` | pH |
| `34.4022.value` | Redox in mV |
| `34.4033.value` | Wassertemperatur |
| `15.16701.value` | Status 1 |
| `15.16704.value` | Status 2 |
| `15.16705.value` | Status 3 |
| `55.17106.status` | Filterpumpe Status |
| `55.17106.value` | Filterpumpe Text |

## Installation in IP-Symcon

1. IP-Symcon Verwaltungskonsole oeffnen.
2. **Module Control** oeffnen.
3. Repository-URL hinzufuegen:

```text
https://github.com/jotebu/Symcon-Bayrol-Poolmanager-Integration.git
```

4. Neue Instanz vom Typ **Bayrol PoolManager 5** anlegen.
5. IP-Adresse, Benutzername und Passwort/PIN des PM5 eintragen.
6. Verbindung testen.

## Aktuelle Architektur

```text
Bayrol PoolManager 5
        |
        | lokale HTTP JSON API
        v
IP-Symcon Modul
        |
        v
Symcon Variablen
        |
        +-- KNX Weitergabe spaeter geplant
```

## Naechste Schritte

- Session-Handling weiter testen
- API-Discovery fuer weitere Datenpunkte entwickeln
- Automatische Variablenanlage erweitern
- Optionale KNX-Zuordnung ergaenzen
- Dokumentation der gefundenen Datenpunkte

## Hinweis

Die JSON-Schnittstelle ist bisher nicht als offizielle oeffentliche API von BAYROL dokumentiert. Das Modul nutzt die Schnittstelle, die auch die lokale Weboberflaeche verwendet.
