# BAYROL PoolManager 5 WebGUI API

Diese Dokumentation beschreibt den bisher beobachteten lokalen API-Ablauf des BAYROL PoolManager 5.

Die Schnittstelle ist bisher nicht als offizielle oeffentliche API dokumentiert. Sie wird von der lokalen Weboberflaeche des PoolManager verwendet.

## Basis-Endpunkt

```text
POST http://<pm5-ip>/cgi-bin/webgui.fcgi?sid=<session-id>
```

## Content-Type

```http
Content-Type: application/json;charset=UTF-8
Accept: application/json
```

## Grundprinzip

Die WebGUI sendet JSON-Objekte mit `set` und `get` an denselben FastCGI-Endpunkt.

### Lesen von Werten

```json
{
  "get": [
    "34.4001.value",
    "34.4022.value",
    "34.4033.value"
  ]
}
```

### Beispielantwort

```json
{
  "status": {
    "code": 0
  },
  "data": {
    "34.4001.value": "7.24",
    "34.4022.value": "840",
    "34.4033.value": "23.7"
  }
}
```

## Statuscode

Bisher beobachtet:

| Code | Bedeutung |
|---:|---|
| 0 | Anfrage erfolgreich |

Weitere Fehlercodes muessen noch systematisch dokumentiert werden.

## Bekannte Key-Struktur

Ein API-Key folgt offenbar diesem Muster:

```text
<bereich>.<objekt>.<suffix>
```

Beispiel:

```text
34.4001.value
```

Bekannte Suffixe:

| Suffix | Bedeutung |
|---|---|
| `value` | Wert / Textwert |
| `status` | Statuscode / Betriebsstatus |
| `pointer` | Zeigerposition in der WebGUI, vermutlich nur Visualisierung |

Weitere Suffixe wie `name`, `unit`, `min`, `max` sind noch zu pruefen.

## Naechste Schritte

- Login und Session-ID-Erzeugung vollstaendig dokumentieren
- Fehlercodes sammeln
- API-Key-Ranges systematisch analysieren
- Datentypen und Einheiten ableiten
