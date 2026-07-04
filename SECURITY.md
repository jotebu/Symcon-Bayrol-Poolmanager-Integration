# Security Policy

## Scope

This repository contains an IP-Symcon module for local integration of the BAYROL PoolManager 5.

The module communicates with the local PM5 WebGUI API. This interface is currently treated as undocumented and should only be used in trusted local networks.

## Reporting a vulnerability

Please do not publish security issues publicly before there is a reasonable chance to assess and fix them.

When reporting a security issue, include:

- affected module version or commit SHA
- IP-Symcon version
- PM5 firmware version if known
- clear reproduction steps
- relevant logs without secrets

Do not include passwords, PINs, cookies, tokens, or private credentials.

## Security assumptions

- The PM5 is reachable only inside a trusted local network.
- The IP-Symcon server is trusted.
- No BAYROL cloud access is required by this module.
- No MQTT broker is required by this module.

## Known risks

- The PM5 WebGUI API is not officially documented.
- Firmware updates may change API behavior.
- Write operations will not be implemented until their behavior is safely confirmed.
