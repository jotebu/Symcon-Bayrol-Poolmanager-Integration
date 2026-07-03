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

## Aktuelle Schlussfolgerung

Der PM5 akzeptiert offenbar clientseitig vorgegebene SIDs. Die SID muss fuer unsere Integration daher nicht vom PM5 angefordert werden.

Fuer das Symcon-Modul reicht voraussichtlich folgender Ablauf:

```text
1. lokale SID erzeugen
2. Login mit dieser SID ausfuehren
3. alle Folge-Requests mit derselben SID senden
4. bei Fehler erneut mit neuer SID einloggen
```

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

## Noch offen

- Sind auch kuerzere oder laengere SIDs gueltig?
- Sind Sonderzeichen erlaubt oder nur alphanumerische Zeichen?
- Wie lange bleibt eine SID gueltig?
- Wird eine SID durch Logout oder Timeout geloescht?
- Sind mehrere parallele Sessions dauerhaft stabil moeglich?
- Welche Fehlercodes werden bei falschem Login geliefert?

## Naechste Tests

Empfohlene Minimaltests:

1. Lesen ohne vorherigen Login mit neuer SID
2. Login mit falschem Passwort/PIN
3. Lesen mit SID nach laengerer Pause, z. B. 10 Minuten, 30 Minuten, 60 Minuten
4. Test mit kurzer SID, z. B. `A`
5. Test mit SID ohne Parameter, also leerer `sid=`

Diese Tests klaeren, wie viel Session-Handling das Modul wirklich benoetigt.
