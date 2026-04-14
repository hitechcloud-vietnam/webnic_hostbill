# `webnic_common` Documentation

## Purpose

`webnic_common` contains shared code used by all custom WebNIC modules in this repository.

## Current Contents

- `lib/class.webnic_api_client.php`

## `WebnicApiClient`

Responsibilities:

- authenticate to WebNIC with JWT token flow
- cache token until near expiry
- send authenticated HTTP requests
- support JSON and multipart requests
- support raw/binary download responses
- normalize errors for HostBill modules

## Public Methods

- `get($path, array $query = [])`
- `post($path, array $body = [], array $options = [])`
- `put($path, array $body = [], array $options = [])`
- `delete($path, array $query = [], array $options = [])`
- `download($path, array $options = [])`
- `getLastResponse()`
- `getLastError()`
- `isSuccessful($response)`
- `testConnection()`
- `extractError($response)`

## Environment Modes

- Live mode -> `https://api.webnic.cc/`
- Test/OTE mode -> `https://oteapi.webnic.cc/`

## Error Model

Errors are normalized from:

- top-level `message`
- nested `error.subCode`
- nested `error.message`
- `validationErrors[]`

## Notes

- This class is intentionally framework-light so it can be reused by all three modules.
- Runtime TLS/network behavior depends on the PHP cURL environment inside HostBill.