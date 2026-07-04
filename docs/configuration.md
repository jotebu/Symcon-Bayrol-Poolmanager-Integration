# Configuration

The module instance will expose configuration properties for connecting to the local PM5 WebGUI API.

## Properties

| Property | Description | Default |
|---|---|---|
| Host | IP address or DNS name of the PoolManager 5 | 192.168.55.23 |
| Port | HTTP port of the PM5 WebGUI | 80 |
| Timeout | HTTP timeout in seconds | 10 |
| UpdateInterval | Polling interval in seconds | 60 |
| DebugMode | Enables extended debug output | false |

## Recommended settings

For the first test:

- Host: the local IP address of the PM5
- Port: 80
- Timeout: 10
- UpdateInterval: 60
- DebugMode: true

After stable operation, debug mode can be disabled.

## Polling interval

A polling interval of 60 seconds is recommended for initial operation. Shorter intervals should only be used during testing.

## Network assumptions

The module expects that IP-Symcon can directly reach the PM5 over HTTP. No MQTT broker, cloud access, or Modbus connection is required for version 0.1.0.
