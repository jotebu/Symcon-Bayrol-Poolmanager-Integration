# Architecture

This repository contains the installable IP-Symcon module for the BAYROL PoolManager 5.

Reverse engineering tools are intentionally kept out of this repository and are maintained separately:

https://github.com/jotebu/Symcon-Bayrol-Poolmanager-ReverseEngineering

## Target architecture

```text
BayrolPoolManager module
  |
  +-- configuration
  |     host, port, timeout, update interval, debug mode
  |
  +-- PM5 API client
  |     HTTP POST, JSON payloads, SID handling
  |
  +-- poller
  |     cyclic read of known API objects
  |
  +-- parser
  |     numeric conversion, text cleanup, status interpretation
  |
  +-- variable manager
  |     creates and updates IP-Symcon variables
  |
  +-- diagnostics
        connection state, API status, last error, debug output
```

## Design rules

- The module repository must stay installable in IP-Symcon.
- Experimental discovery and learning tools are not part of this repository.
- Write operations are added only after safe API behavior is confirmed.
- Known API objects should be documented before they are used in module code.

## Initial implementation scope

Version 0.1.0 focuses on:

- installable module structure
- basic PM5 communication
- polling of first known values
- first variables
- debug and error handling
