# Symcon Bayrol PoolManager Integration

IP-Symcon module library for local integration of the BAYROL PoolManager 5 (PM5).

This repository contains only the installable IP-Symcon module. Reverse engineering tools, API explorers, learning scripts, and test utilities are maintained separately in:

https://github.com/jotebu/Symcon-Bayrol-Poolmanager-ReverseEngineering

## Project status

Current phase: Sprint 0 - repository foundation.

Goal of Sprint 0:

- clean IP-Symcon library structure
- no experimental tools in this repository
- basic documentation
- clear separation between integration and reverse engineering

Goal of version 0.1.0:

- installable IP-Symcon library
- one module: BayrolPoolManager
- local HTTP JSON communication with PM5
- cyclic polling of first known values
- automatic creation of initial variables
- debug and status handling

## Supported hardware

Initial target:

- BAYROL PoolManager 5 / PM5

Known tested firmware during development:

- v240729-M1 / 9.1.1

Other BAYROL devices may use a similar WebGUI API, but are not supported yet.

## Repository layout

```text
library.json
BayrolPoolManager/
  module.json
  module.php
README.md
CHANGELOG.md
LICENSE
CONTRIBUTING.md
SECURITY.md
docs/
```

## Installation in IP-Symcon

1. Open the IP-Symcon management console.
2. Open Module Control.
3. Add this repository URL:

```text
https://github.com/jotebu/Symcon-Bayrol-Poolmanager-Integration.git
```

4. Update the module library.
5. Create a new instance of Bayrol PoolManager 5.
6. Configure the PM5 host/IP address.
7. Test the connection.

Detailed steps will be documented in `docs/installation.md`.

## First known API objects

| API key | Meaning | Status |
|---|---|---|
| 34.4001.value | pH | confirmed |
| 34.4022.value | Redox | confirmed |
| 34.4033.value | pool temperature | confirmed |
| 13.16507.text2 | outdoor temperature T3 | confirmed |
| 13.16509.text1 | conductivity | confirmed |
| 55.17106.value | filter pump text | confirmed |
| 55.17106.status | filter pump status | confirmed |
| 55.17106.opmode | filter pump operation mode | confirmed |

## Roadmap

- 0.1.0: first installable module with basic polling
- 0.2.0: extended polling and variable manager
- 0.3.0: improved API object mapping
- 0.4.0: discovery support
- 0.5.0: actuator control after safe write API confirmation
- 1.0.0: stable release

## Security note

The PM5 WebGUI API is local and currently treated as an undocumented interface. Use this module only inside a trusted local network.

## License

MIT License. See `LICENSE`.