# Known PM5 Objects

This document lists PM5 WebGUI API objects that were identified during testing.

Confidence levels:

- confirmed: repeatedly verified against known PM5 values
- observed: value was seen but meaning is not fully confirmed
- experimental: used only for research

## Measurements

| API key | Meaning | Type | Unit | Confidence |
|---|---|---|---|---|
| 34.4001.value | pH | float string | pH | confirmed |
| 34.4022.value | Redox | integer string | mV | confirmed |
| 34.4033.value | Pool temperature | float string | deg C | confirmed |
| 13.16507.text2 | Outdoor temperature T3 | text | deg C | confirmed |
| 13.16509.text1 | Conductivity | text | mS/cm | confirmed |

## Filter pump

| API key | Meaning | Type | Confidence |
|---|---|---|---|
| 55.17106.value | Filter pump display text | text/html string | confirmed |
| 55.17106.status | Filter pump activity status | integer string | confirmed |
| 55.17106.opmode | Filter pump operation mode | integer string | confirmed |

Observed display texts for `55.17106.value`:

| Text | Meaning |
|---|---|
| Filterpumpe | automatic or off state, depending on context |
| Filterpumpe (Eco-Betrieb) | eco mode |
| Filterpumpe (Normal-Betrieb) | normal mode |
| Filterpumpe (erhoeht) | high mode |

Observed interpretation:

| Key | Value | Meaning |
|---|---|---|
| 55.17106.status | 0 | active/running in manual pump modes |
| 55.17106.status | 1 | inactive or automatic state depending on text |
| 55.17106.opmode | 0 | automatic |
| 55.17106.opmode | 1 | manual |
| 55.17106.opmode | 2 | off/blocked depending on object |

## Other actuator objects observed

| Object | Display text | Confidence |
|---|---|---|
| 55.17104 | Universal 4 | observed |
| 55.17105 | Flockmatic-Pumpe (blockiert) | observed |
| 55.17107 | Spar-Betrieb | observed |
| 55.17108 | Heizung | observed |
| 55.17109 | Solar | observed |

## Notes

Object meanings may depend on PM5 configuration, connected hardware, and firmware version. Unknown or installation-specific objects should be documented in the reverse engineering repository first.
