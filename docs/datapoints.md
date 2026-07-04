# Datenpunkte

Diese Datei dokumentiert bestaetigte Datenpunkte der lokalen BAYROL PoolManager 5 WebGUI-API.

## Hinweis zur Bedeutung

Die konkrete semantische Bedeutung vieler Datenpunkte muss noch verifiziert werden. Aktuell ist sicher:

- Prefix `34` enthaelt numerische Mess-, Soll- oder Grenzwerte.
- `.value` liefert den eigentlichen Wert.
- Viele Werte sind ohne Login lesbar.
- Mehrere Keys koennen denselben Anzeige- oder Regelwert enthalten.

## Bekannte aktuelle Anlagenwerte

Vom Anwender gemeldete aktuelle Werte zum Zeitpunkt des Scans:

| Wert | Aktuell | Min | Max | Zusatz |
|---|---:|---:|---:|---|
| pH | `7.23` | `6.00` | `7.80` | - |
| Redox | `836` | `400` | `900` | - |
| Pooltemperatur | `23.1` bis `23.2` | `5.0` | `50.0` | Sollwert `25.0` |
| Heizung | aus | - | - | aktuell blockiert |
| Rueckspuelung | aus | - | - | Automatik ein |
| Lampen Becken | aus | - | - | Automatik ein |
| Filterpumpe | aus | - | - | Automatik ein |
| Wassernachspeisung | aus | - | - | - |
| Flockmatic-Pumpe | aus | - | - | aktuell blockiert |
| Aussentemperatur / T3 | `14.3` | - | - | aus Gauge-Text `T3 14.3°C` beobachtet |

## Prefix 34 - erster Scan 4001 bis 4050

Quelle: API Explorer, Bereich `34 => [4000, 4050]`, zweistufiger Low-Load-Scan.

| Key | Typ | Beispielwert | Zuordnung / Status |
|---|---|---:|---|
| `34.4001.value` | float-string | `7.23` | **pH Istwert**, bestaetigt |
| `34.4002.value` | float-string | `7.23` | pH-naher Wert, vermutlich redundanter/Regel-/Anzeige-Wert |
| `34.4003.value` | float-string | `7.23` | pH-naher Wert, vermutlich redundanter/Regel-/Anzeige-Wert |
| `34.4004.value` | float-string | `7.23` | pH-naher Wert, vermutlich redundanter/Regel-/Anzeige-Wert |
| `34.4005.value` | int-string | `21` | noch zu klaeren |
| `34.4006.value` | int-string | `21` | noch zu klaeren |
| `34.4007.value` | int-string | `21` | noch zu klaeren |
| `34.4008.value` | float-string | `0.00` | noch zu klaeren |
| `34.4009.value` | float-string | `0.00` | noch zu klaeren |
| `34.4010.value` | float-string | `0.00` | noch zu klaeren |
| `34.4011.value` | float-string | `0.00` | noch zu klaeren |
| `34.4012.value` | float-string | `0.45` | noch zu klaeren, evtl. Chlor/Flock/Dosierwert |
| `34.4013.value` | float-string | `0.45` | noch zu klaeren, evtl. Chlor/Flock/Dosierwert |
| `34.4014.value` | float-string | `0.00` | noch zu klaeren |
| `34.4015.value` | float-string | `0.60` | noch zu klaeren, evtl. Soll-/Grenzwert |
| `34.4016.value` | float-string | `0.65` | noch zu klaeren, evtl. Soll-/Grenzwert |
| `34.4017.value` | float-string | `0.65` | noch zu klaeren, evtl. Soll-/Grenzwert |
| `34.4018.value` | float-string | `0.00` | noch zu klaeren |
| `34.4019.value` | float-string | `0.36` | noch zu klaeren |
| `34.4020.value` | float-string | `0.36` | noch zu klaeren |
| `34.4021.value` | float-string | `0.10` | noch zu klaeren |
| `34.4022.value` | int-string | `836` | **Redox Istwert in mV**, bestaetigt |
| `34.4023.value` | int-string | `836` | Redox-naher Wert, vermutlich redundanter/Regel-/Anzeige-Wert |
| `34.4024.value` | float-string | `1.5` | noch zu klaeren |
| `34.4025.value` | float-string | `3.8` | noch zu klaeren |
| `34.4026.value` | float-string | `3.8` | noch zu klaeren |
| `34.4027.value` | float-string | `3.8` | noch zu klaeren |
| `34.4028.value` | int-string | `0` | noch zu klaeren, evtl. Status/Zaehler |
| `34.4029.value` | float-string | `0.0` | noch zu klaeren |
| `34.4030.value` | float-string | `0.0` | noch zu klaeren |
| `34.4031.value` | float-string | `0.0` | noch zu klaeren |
| `34.4032.value` | float-string | `0.0` | noch zu klaeren |
| `34.4033.value` | float-string | `23.2` | **Pooltemperatur / Wassertemperatur**, bestaetigt |
| `34.4034.value` | float-string | `23.2` | Temperatur-naher Wert, vermutlich redundanter/Regel-/Anzeige-Wert |
| `34.4035.value` | int-string | `0` | noch zu klaeren |
| `34.4036.value` | int-string | `0` | noch zu klaeren |
| `34.4037.value` | int-string | `0` | noch zu klaeren |
| `34.4038.value` | int-string | `0` | noch zu klaeren |
| `34.4039.value` | int-string | `5` | passt zu Temperatur-Minimum `5.0`, wahrscheinlich Temperatur-Minimum |
| `34.4040.value` | int-string | `1` | noch zu klaeren |
| `34.4041.value` | float-string | `0.0` | noch zu klaeren |
| `34.4042.value` | int-string | `1` | noch zu klaeren |
| `34.4043.value` | float-string | `0.0` | noch zu klaeren |
| `34.4044.value` | int-string | `1` | noch zu klaeren |
| `34.4045.value` | float-string | `0.0` | noch zu klaeren |
| `34.4046.value` | int-string | `50` | passt zu Temperatur-Maximum `50.0`, wahrscheinlich Temperatur-Maximum |
| `34.4047.value` | float-string | `3.12` | noch zu klaeren |
| `34.4048.value` | float-string | `7.35` | pH-naher Soll-/Grenzwert, noch zu klaeren |
| `34.4049.value` | float-string | `7.35` | pH-naher Soll-/Grenzwert, noch zu klaeren |
| `34.4050.value` | float-string | `7.34` | pH-naher Soll-/Grenzwert, noch zu klaeren |

## Abgeleitete Zuordnungen aus bekannten Anlagenwerten

### Sicher bestaetigt

| Bedeutung | Key | Begruendung |
|---|---|---|
| pH Istwert | `34.4001.value` | Wert entspricht pH `7.23`; bereits vorher aus WebGUI/HAR als pH bekannt |
| Redox Istwert | `34.4022.value` | Wert entspricht Redox `836`; bereits vorher aus WebGUI/HAR als Redox bekannt |
| Pooltemperatur | `34.4033.value` | Wert entspricht Pooltemperatur `23.1` bis `23.2`; bereits vorher aus WebGUI/HAR als Temperatur bekannt |

### Plausibel, aber noch nicht sicher

| Bedeutung | Key | Begruendung |
|---|---|---|
| Temperatur-Minimum | `34.4039.value` | Wert `5` entspricht gemeldetem Temperatur-Minimum `5.0` |
| Temperatur-Maximum | `34.4046.value` | Wert `50` entspricht gemeldetem Temperatur-Maximum `50.0` |
| pH-nahe Soll-/Grenzwerte | `34.4048.value`, `34.4049.value`, `34.4050.value` | Werte `7.35`/`7.34` liegen im pH-Kontext, aber entsprechen nicht gemeldetem pH-Min `6.00` oder pH-Max `7.80` |

## Noch nicht gefunden / weitere Scans erforderlich

Diese bekannten Werte konnten im Bereich `34.4001` bis `34.4050` noch nicht eindeutig zugeordnet werden:

- pH Minimum `6.00`
- pH Maximum `7.80`
- Redox Minimum `400`
- Redox Maximum `900`
- Pooltemperatur Sollwert `25.0`
- Aussentemperatur / T3 `14.3`
- Status Heizung aus / blockiert
- Status Rueckspuelung aus / Automatik ein
- Status Lampen Becken aus / Automatik ein
- Status Filterpumpe aus / Automatik ein
- Status Wassernachspeisung aus
- Status Flockmatic-Pumpe aus / blockiert

Naechste sinnvolle Scans:

- Prefix `13` fuer Anzeige-/Gauge-Texte, insbesondere T3 `14.3°C`
- Prefix `15` fuer Status-/Textmeldungen
- Prefix `55` fuer Relais/Ausgaenge wie Filterpumpe, Lampen, Rueckspuelung
- Erweiterter Prefix `34` ueber `4050` hinaus, um Soll- und Grenzwerte zu finden

## Erkenntnisse aus dem ersten stabilen Low-Load-Scan

- Bereich `34.4001` bis `34.4050` liefert fast durchgaengig echte numerische Werte.
- `34.4000.value` wurde nicht als echter Wert zurueckgegeben.
- Der zweistufige Explorer lief im Bereich 34 stabil ohne HTTP 500/503.
- Detailfelder wie `.name`, `.unit`, `.min`, `.max` lieferten in diesem Scan keine brauchbaren Metadaten, sondern offenbar keine oder nur Platzhalterdaten.
