# `webnic_dns` Documentation

## Purpose

`webnic_dns` is a clean-room HostBill DNS module for WebNIC managed DNS.

## Implemented Features

- Add zone
- Delete zone
- List zones
- List zone records
- Add record
- Edit record
- Delete record
- Query supported record types
- Query default nameservers
- Provide app configuration summary for admin UI and API

## Server Credentials

The module uses HostBill server connection fields:

- username
- password
- OTE checkbox flag

## Product Options

- `dns_template`
- `maxdomain`
- `ns1`
- `ns2`
- `ns3`
- `ns4`
- `hide_billing`
- `hide_zone_management`

## Admin Controller

Path:

- `admin/class.webnic_dns_controller.php`

Implemented actions:

- `beforeCall`
- `productdetails`
- `appdetails`

## User Controller

Path:

- `user/class.webnic_dns_controller.php`

Purpose:

- connect module into HostBill DNS client-area behavior
- reuse `DNSClient_Controller`

## Templates

- `templates/productconfig.tpl`
- `templates/appconfig.tpl`

## Module API Controller

Path:

- `api/class.webnic_dns_controller.php`

Implemented API calls:

- `summary`
- `zones`

## Important Methods

- `addZone()`
- `deleteZone()`
- `listZones()`
- `getZone()`
- `addRecord()`
- `editRecord()`
- `deleteRecord()`
- `getSupportedRecords()`
- `getDefaultNameservers()`
- `getDomainLimit()`
- `getAppConfigSummary()`

## Notes

- The module currently supports these fallback record types: `A`, `AAAA`, `CNAME`, `MX`, `SRV`, `TXT`.
- Real record payload expectations should be verified with WebNIC OTE responses.
- Client-area behavior relies on HostBill DNS base controllers and template conventions.