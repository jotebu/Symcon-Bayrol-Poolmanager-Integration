# Contributing

Thanks for your interest in this project.

This repository contains the installable IP-Symcon module only. Experimental scripts, API explorers, delta scanners, learning tools, and reverse engineering work belong in the separate repository:

https://github.com/jotebu/Symcon-Bayrol-Poolmanager-ReverseEngineering

## Development principles

- Keep the module repository installable at all times.
- Do not add experimental tools to this repository.
- Prefer small, focused changes.
- Document new API objects before using them in production code.
- Do not add write access to PM5 functions unless the API behavior is confirmed.

## Branch and commit style

Suggested branch names:

- `feature/short-description`
- `fix/short-description`
- `docs/short-description`

Suggested commit style:

- `Add PM5 polling timer`
- `Fix API error handling`
- `Document filter pump objects`

## Pull requests

A pull request should include:

- a short explanation of the change
- affected files/modules
- test notes from IP-Symcon if applicable
- references to reverse engineering results if new PM5 objects are added

## Bug reports

Please include:

- IP-Symcon version
- PM5 firmware version
- module version or commit SHA
- relevant debug output
- expected behavior
- actual behavior

Do not include passwords, PINs, cookies, or private network credentials.
