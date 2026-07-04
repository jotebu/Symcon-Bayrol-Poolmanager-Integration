# Session und SID

Diese Datei dokumentiert die bisher beobachtete Session-Logik der lokalen BAYROL PoolManager 5 WebGUI.

## Beobachteter Endpunkt

Alle WebGUI-Requests laufen ueber denselben FastCGI-Endpunkt:

```text
/cgi-bin/webgui.fcgi?sid=<sid>
```

Optionale Navigation erfolgt per `cmd`:

```text
/cgi-bin/webgui.fcgi?sid=<sid>&cmd=<page-id>
```

## Beobachtete SID-Eigenschaften

Aus den bisher analysierten HAR-Dateien:

| Eigenschaft | Beobachtung |
|---|---|
| Laenge in WebGUI | 32 Zeichen |
| Zeichensatz in WebGUI | vermutlich `A-Z`, `a-z`, `0-9` |
| Beispiel 1 | `dkJ5eSCEKcaMew65x6uQga4hSITXGppJ` |
| Beispiel 2 | `U4ygEYBAZ62mMVPSopjC0ybREYLLhQb1` |
| Cookie | bisher nicht beobachtet |
| Set-Cookie | bisher nicht beobachtet |
| Redirect zur SID | bisher nicht beobachtet |

## Ergebnis SID-Akzeptanztest

Ein Symcon-Testskript hat mehrere bewusst gewaehlte SIDs getestet:

```text
AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA
BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB
12345678901234567890123456789012
<random-base62-32>
<random-base62-32>
```

Alle getesteten SIDs konnten erfolgreich verwendet werden:

1. Login per `set` auf `9.17401.user` und `9.17401.pass`
2. anschliessendes Lesen per `get`
3. Rueckgabe von pH, Redox und Temperatur

Beispielwerte aus dem Test:

| Key | Wert |
|---|---:|
| `34.4001.value` | `7.24` |
| `34.4022.value` | `838` |
| `34.4033.value` | `23.5` |

## Lesen ohne Login

Ein weiterer Symcon-Test hat gezeigt, dass reine Lesezugriffe auf Messwerte auch ohne vorherigen Login funktionieren.

Getestete SIDs:

```text
NOLOGINTEST00000000000000000001
NOLOGINTEST00000000000000000002
```

Anfrage:

```json
{
  "get": [
    "34.4001.value",
    "34.4022.value",
    "34.4033.value"
  ]
}
```

Antwort jeweils erfolgreich:

```json
{
  "data": {
    "34.4001.value": "7.24",
    "34.4022.value": "838",
    "34.4033.value": "23.4"
  },
  "status": {
    "code": 0
  },
  "event": {
    "type": 1,
    "data": "48.30000.0"
  }
}
```

Schlussfolgerung:

- Fuer reine Monitoring-Werte ist kein Login erforderlich.
- Das Symcon-Modul kann Messwerte zunaechst ohne Benutzername/PIN lesen.
- Login sollte nur fuer geschuetzte oder schreibende Funktionen erforderlich sein.

## Login-Ablauf

Der Login erfolgt per JSON-POST an denselben Endpunkt:

```json
{
  "set": {
    "9.17401.user": "<username>",
    "9.17401.pass": "<password-or-pin>"
  }
}
```

Erfolgreiche Antwort:

```json
{
  "data": {},
  "status": {
    "code": 0
  },
  "event": {
    "type": 1,
    "data": "3.16901.0"
  }
}
```

Interpretation:

| Feld | Bedeutung |
|---|---|
| `status.code = 0` | Login erfolgreich |
| `event.type = 1` | Navigation / Seitenwechsel |
| `event.data = 3.16901.0` | Zielseite nach Login, vermutlich Home |

## Login mit falschem Passwort/PIN

Ein Test mit falschem Passwort/PIN lieferte:

```json
{
  "data": {},
  "status": {
    "code": 3
  },
  "event": {
    "type": 1,
    "data": "7.12558.0"
  }
}
```

Interpretation:

| Feld | Bedeutung |
|---|---|
| `status.code = 3` | Login fehlgeschlagen / falsche Zugangsdaten |
| `event.data = 7.12558.0` | Fehler-/Login-Seite oder Hinweisdialog |

Wichtig: Direkt nach einem fehlgeschlagenen Login konnten Messwerte mit derselben SID weiterhin gelesen werden. Das bestaetigt, dass die getesteten Messwert-Keys nicht authentifizierungspflichtig sind.

## Aktuelle Modul-Strategie

Fuer die erste Modulversion sollte gelten:

```text
1. lokale SID erzeugen oder feste Modul-SID verwenden
2. Messwerte per get ohne Login lesen
3. Login nur optional fuer spaetere geschuetzte Funktionen verwenden
4. bei API-Fehler nicht automatisch Login erzwingen, sondern Fehlercode auswerten
```

## Noch offen

- Welche API-Keys sind ohne Login lesbar?
- Welche API-Keys benoetigen Login?
- Sind auch kuerzere oder laengere SIDs gueltig?
- Sind Sonderzeichen erlaubt oder nur alphanumerische Zeichen?
- Wie lange bleibt eine SID gueltig?
- Wird eine SID durch Logout oder Timeout geloescht?
- Sind mehrere parallele Sessions dauerhaft stabil moeglich?
- Gibt es weitere Fehlercodes ausser `0` und `3`?

## Naechste Tests

Empfohlene Minimaltests:

1. Test mit kurzer SID, z. B. `A`
2. Test mit leerem `sid=`
3. Test mit API-Keys aus weiteren WebGUI-Seiten ohne Login
4. Test mit mutmasslich schreibenden `set`-Operationen nur nach ausdruecklicher Freigabe
