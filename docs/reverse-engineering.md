# Reverse Engineering

This repository does not contain reverse engineering tools.

All experimental tools, API explorers, delta scanners, learning scripts, snapshots, and correlation logic are maintained in the separate repository:

https://github.com/jotebu/Symcon-Bayrol-Poolmanager-ReverseEngineering

## Separation of responsibilities

## Integration repository

This repository contains:

- installable IP-Symcon module
- stable source code
- known and documented API objects
- user-facing documentation
- releases and changelog

It should remain installable in IP-Symcon at all times.

## Reverse engineering repository

The reverse engineering repository contains:

- API explorer scripts
- delta scanner
- snapshot tools
- learning engine
- correlator
- test data
- experimental documentation

## Promotion process

New PM5 objects should follow this path:

1. Discover object in the reverse engineering repository.
2. Validate the object with real PM5 values.
3. Document object meaning and confidence level.
4. Add the object to `docs/known-objects.md` in this repository.
5. Use the object in module code only after it is sufficiently verified.

## Safety rule

Write operations must never be added based on assumptions. They require explicit confirmation of:

- target object
- accepted values
- side effects
- safe fallback behavior
- PM5 response behavior
