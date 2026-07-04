# Roadmap

This roadmap describes the planned development of the installable IP-Symcon module.

Reverse engineering and experimental tooling are maintained separately in:

https://github.com/jotebu/Symcon-Bayrol-Poolmanager-ReverseEngineering

## Sprint 0 - Repository foundation

Goal:

- clean repository structure
- separate integration from reverse engineering
- add project documentation
- prepare version 0.1.0

Status: in progress

## Version 0.1.0 - First installable module

Goal:

- installable IP-Symcon library
- one module: BayrolPoolManager
- configuration for host, port, timeout, update interval, debug mode
- local HTTP JSON communication with PM5
- cyclic polling of first known API objects
- automatic creation of first variables
- basic status and error handling

No actuator writes in this version.

## Version 0.2.0 - Extended polling

Goal:

- improved variable manager
- more known PM5 values
- cleaner parsing of text and HTML values
- improved diagnostics

## Version 0.3.0 - Object map

Goal:

- structured PM5 object map
- confidence levels for known objects
- easier extension of supported values

## Version 0.4.0 - Discovery support

Goal:

- safe read-only object discovery
- detection of new or unknown objects
- reporting unknown objects without creating unsafe actions

## Version 0.5.0 - Actuator control

Goal:

- add write operations only after safe confirmation
- filter pump modes
- pool lighting
- selected additional actuators if verified

## Version 1.0.0 - Stable release

Goal:

- stable polling
- documented object map
- safe configuration
- robust error handling
- tested with real PM5 installations
