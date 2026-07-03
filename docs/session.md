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
| Laenge | 32 Zeichen |
| Zeichensatz | vermutlich `A-Z`, `a-z`, `0-9` |
| Beispiel 1 | `dkJ5eSCEKcaMew65x6uQga4hSITXGppJ` |
| Beispiel 2 | `U4ygEYBAZ62mMVPSopjC0ybREYLLhQb1` |
| Cookie | bisher nicht beobachtet |
| Set-Cookie | bisher nicht beobachtet |
| Redirect zur SID | bisher nicht beobachtet |

## Aktuelle Hypothese

Die SID wird wahrscheinlich clientseitig durch JavaScript erzeugt und anschliessend beim ersten API-Request an den PM5 uebergeben.

Die bisherige HAR-Datei beginnt bereits mit Requests, die eine SID enthalten. Deshalb ist die eigentliche SID-Erzeugung noch nicht nachgewiesen.

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

- Wird die SID vom Browser-JavaScript erzeugt oder vom PM5 geliefert?
- Akzeptiert der PM5 beliebige 32-stellige alphanumerische SIDs?
- Wie lange bleibt eine SID gueltig?
- Wird eine SID durch Logout oder Timeout geloescht?
- Sind mehrere parallele Sessions moeglich?
- Welche Fehlercodes werden bei ungueltiger SID oder falschem Login geliefert?

## Naechster Test

Eine neue HAR-Aufzeichnung muss vor dem ersten Aufruf der PM5-Weboberflaeche starten:

1. Chrome DevTools oeffnen
2. Network aktivieren
3. Preserve log aktivieren
4. Disable cache aktivieren
5. Browser-Tab komplett leer starten
6. `http://<pm5-ip>/` aufrufen
7. Login durchfuehren
8. HAR mit Inhalt speichern

Wichtig ist der allererste Request auf `/` oder `/cgi-bin/webgui.fcgi` ohne bekannte SID.
