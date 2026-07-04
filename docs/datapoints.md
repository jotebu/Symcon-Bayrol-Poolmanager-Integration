# Datenpunkte

Diese Datei dokumentiert bestaetigte Datenpunkte der lokalen BAYROL PoolManager 5 WebGUI-API.

## Hinweis zur Bedeutung

Die konkrete semantische Bedeutung vieler Datenpunkte muss noch verifiziert werden. Aktuell ist nur sicher:

- Prefix `34` enthaelt numerische Mess-, Soll- oder Grenzwerte.
- `.value` liefert den eigentlichen Wert.
- Viele Werte sind ohne Login lesbar.

## Prefix 34 - erster Scan 4001 bis 4050

Quelle: API Explorer, Bereich `34 => [4000, 4050]`, zweistufiger Low-Load-Scan.

| Key | Typ | Beispielwert | Vermutung / Status |
|---|---|---:|---|
| `34.4001.value` | float-string | `7.23` | pH Istwert, bereits durch WebGUI/HAR bestaetigt |
| `34.4002.value` | float-string | `7.23` | noch zu klaeren |
| `34.4003.value` | float-string | `7.23` | noch zu klaeren |
| `34.4004.value` | float-string | `7.23` | noch zu klaeren |
| `34.4005.value` | int-string | `21` | noch zu klaeren |
| `34.4006.value` | int-string | `21` | noch zu klaeren |
| `34.4007.value` | int-string | `21` | noch zu klaeren |
| `34.4008.value` | float-string | `0.00` | noch zu klaeren |
| `34.4009.value` | float-string | `0.00` | noch zu klaeren |
| `34.4010.value` | float-string | `0.00` | noch zu klaeren |
| `34.4011.value` | float-string | `0.00` | noch zu klaeren |
| `34.4012.value` | float-string | `0.45` | noch zu klaeren |
| `34.4013.value` | float-string | `0.45` | noch zu klaeren |
| `34.4014.value` | float-string | `0.00` | noch zu klaeren |
| `34.4015.value` | float-string | `0.60` | noch zu klaeren |
| `34.4016.value` | float-string | `0.65` | noch zu klaeren |
| `34.4017.value` | float-string | `0.65` | noch zu klaeren |
| `34.4018.value` | float-string | `0.00` | noch zu klaeren |
| `34.4019.value` | float-string | `0.36` | noch zu klaeren |
| `34.4020.value` | float-string | `0.36` | noch zu klaeren |
| `34.4021.value` | float-string | `0.10` | noch zu klaeren |
| `34.4022.value` | int-string | `836` | Redox Istwert in mV, bereits durch WebGUI/HAR bestaetigt |
| `34.4023.value` | int-string | `836` | vermutlich Redox redundanter/formatierter Wert, noch zu klaeren |
| `34.4024.value` | float-string | `1.5` | noch zu klaeren |
| `34.4025.value` | float-string | `3.8` | noch zu klaeren |
| `34.4026.value` | float-string | `3.8` | noch zu klaeren |
| `34.4027.value` | float-string | `3.8` | noch zu klaeren |
| `34.4028.value` | int-string | `0` | noch zu klaeren |
| `34.4029.value` | float-string | `0.0` | noch zu klaeren |
| `34.4030.value` | float-string | `0.0` | noch zu klaeren |
| `34.4031.value` | float-string | `0.0` | noch zu klaeren |
| `34.4032.value` | float-string | `0.0` | noch zu klaeren |
| `34.4033.value` | float-string | `23.2` | Temperatur T1 / Wasser, bereits durch WebGUI/HAR bestaetigt |
| `34.4034.value` | float-string | `23.2` | vermutlich redundanter Temperaturwert, noch zu klaeren |
| `34.4035.value` | int-string | `0` | noch zu klaeren |
| `34.4036.value` | int-string | `0` | noch zu klaeren |
| `34.4037.value` | int-string | `0` | noch zu klaeren |
| `34.4038.value` | int-string | `0` | noch zu klaeren |
| `34.4039.value` | int-string | `5` | noch zu klaeren |
| `34.4040.value` | int-string | `1` | noch zu klaeren |
| `34.4041.value` | float-string | `0.0` | noch zu klaeren |
| `34.4042.value` | int-string | `1` | noch zu klaeren |
| `34.4043.value` | float-string | `0.0` | noch zu klaeren |
| `34.4044.value` | int-string | `1` | noch zu klaeren |
| `34.4045.value` | float-string | `0.0` | noch zu klaeren |
| `34.4046.value` | int-string | `50` | noch zu klaeren |
| `34.4047.value` | float-string | `3.12` | noch zu klaeren |
| `34.4048.value` | float-string | `7.35` | vermutlich pH Soll-/Grenzwert, noch zu klaeren |
| `34.4049.value` | float-string | `7.35` | vermutlich pH Soll-/Grenzwert, noch zu klaeren |
| `34.4050.value` | float-string | `7.34` | vermutlich pH Soll-/Grenzwert, noch zu klaeren |

## Erkenntnisse aus dem ersten stabilen Low-Load-Scan

- Bereich `34.4001` bis `34.4050` liefert fast durchgaengig echte numerische Werte.
- `34.4000.value` wurde nicht als echter Wert zurueckgegeben.
- Der zweistufige Explorer lief im Bereich 34 stabil ohne HTTP 500/503.
- Detailfelder wie `.name`, `.unit`, `.min`, `.max` lieferten in diesem Scan keine brauchbaren Metadaten, sondern offenbar keine oder nur Platzhalterdaten.
