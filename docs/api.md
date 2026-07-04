# PM5 API

This document describes the currently known local WebGUI JSON API behavior of the BAYROL PoolManager 5.

The API is not officially documented by BAYROL. All information is based on observed behavior during development and must be validated against real devices and firmware versions.

## Endpoint

The local WebGUI uses an HTTP endpoint similar to:

```text
http://<host>:<port>/cgi-bin/webgui.fcgi?sid=<sid>
```

Typical port:

```text
80
```

## Request format

Known read requests use HTTP POST with a JSON body:

```json
{
  "get": [
    "34.4001.value",
    "34.4022.value",
    "34.4033.value"
  ]
}
```

## Response format

A successful response usually contains:

```json
{
  "data": {
    "34.4001.value": "7.23"
  },
  "status": {
    "code": 0
  },
  "event": {
    "type": 1,
    "data": "48.30000.0"
  }
}
```

## SID behavior

During testing, arbitrary SID values were accepted for read operations. The module therefore creates a generated SID for each request.

This behavior may change with firmware updates and must be treated as an implementation detail.

## API status

Observed status code:

| Code | Meaning |
|---|---|
| 0 | successful request |
| 3 | failed login attempt in login tests |

## Object naming pattern

Observed object keys use the structure:

```text
<group>.<object>.<suffix>
```

Examples:

```text
34.4001.value
55.17106.status
55.17106.opmode
13.16507.text2
```

Common suffixes observed during testing:

| Suffix | Meaning |
|---|---|
| value | primary value or display text |
| status | status value |
| opmode | operation mode |
| text1 | text field |
| text2 | text field |

## Write operations

Write operations are not part of version 0.1.0. They will only be implemented after safe behavior has been verified in the reverse engineering repository.
