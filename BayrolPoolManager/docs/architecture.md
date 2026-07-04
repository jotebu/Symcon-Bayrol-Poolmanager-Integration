# Architecture

This directory contains documentation for the BayrolPoolManager IP-Symcon module.

The repository root should contain only files and actual module directories so that IP-Symcon can import the library reliably.

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
