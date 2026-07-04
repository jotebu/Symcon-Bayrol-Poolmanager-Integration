# Installation

## Requirements

- IP-Symcon 6.0 or newer
- BAYROL PoolManager 5 reachable in the local network
- HTTP access to the PM5 WebGUI

## Add the module library

1. Open the IP-Symcon management console.
2. Open Module Control.
3. Add the repository URL:

```text
https://github.com/jotebu/Symcon-Bayrol-Poolmanager-Integration.git
```

4. Update the module library.
5. Create a new instance of `Bayrol PoolManager 5`.

## Configure the instance

Set at least:

- Host: IP address or DNS name of the PM5
- Port: normally 80
- Timeout: HTTP timeout in seconds
- Update interval: polling interval in seconds

## First test

After creating the instance:

1. Enable debug mode if needed.
2. Click `Verbindung testen`.
3. Click `Werte jetzt aktualisieren`.
4. Check whether the variables are created and updated.

## Troubleshooting

If the module does not connect:

- verify that the PM5 WebGUI is reachable in a browser
- verify that IP-Symcon can reach the PM5 IP address
- check the configured port
- enable debug mode and inspect the debug output

If IP-Symcon reports an invalid module directory, check that the repository contains only one module directory with a valid `module.json`.
