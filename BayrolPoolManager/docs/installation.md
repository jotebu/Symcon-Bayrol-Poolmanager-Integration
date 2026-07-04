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

1. Enable debug mode if needed.
2. Click `Verbindung testen`.
3. Click `Werte jetzt aktualisieren`.
4. Check whether the variables are created and updated.
