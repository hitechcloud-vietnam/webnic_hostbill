# WebNIC HostBill Modules

Clean-room WebNIC integration modules for HostBill.

This repository contains three custom modules built outside `core_dev/` and `postman_webnic/`:

- `webnic_domains` - domain registrar module
- `webnic_ssl` - SSL provisioning module
- `webnic_dns` - DNS management module

## Goals

- Keep implementation isolated from vendor/reference sources
- Rebuild WebNIC integration against HostBill module contracts
- Provide admin UI, DNS client UI, and module API endpoints
- Use `postman_webnic/Default module.openapi.json` as the API contract reference

## Repository Layout

```text
webnic_domains/
  admin/
  api/
  class.webnic_domains.php
webnic_ssl/
  admin/
  api/
  class.webnic_ssl.php
webnic_dns/
  admin/
  api/
  templates/
  user/
  class.webnic_dns.php
core_dev/
postman_webnic/
```

## Embedded API Clients

Each module contains its own embedded WebNIC API client implementation to avoid runtime coupling between modules and to simplify HostBill deployment.

Embedded clients provide:

- JWT authentication
- automatic token reuse
- live and OTE environment switching
- JSON request helpers: `get`, `post`, `put`, `delete`
- binary download helper
- normalized error extraction

### Base URLs

- Live: `https://api.webnic.cc/`
- OTE: `https://oteapi.webnic.cc/`

### Authentication

Token authentication uses:

- `POST /reseller/v2/api-user/token`

## Modules Overview

### 1. `webnic_domains`

HostBill domain registrar module for:

- domain registration
- renewal
- transfer-in
- deletion
- nameserver management
- registrar lock management
- WHOIS privacy management
- EPP/auth-code actions
- contact synchronization
- verification email resend
- ownership certificate download

Admin UI includes:

- domain snapshot panel
- registry/status panel
- contacts panel
- transfer status panel
- action panel for lock, unlock, EPP, sync, resend verify

Module API endpoints include:

- `snapshot`
- `actions`

### 2. `webnic_ssl`

HostBill SSL module for:

- certificate order creation
- renewal
- reissue
- cancellation
- DCV email lookup
- DCV method update
- certificate synchronization
- certificate download

Admin UI includes:

- product-to-WebNIC product key mapping
- account certificate detail view
- CSR display
- DCV detail display
- change DCV workflow
- resend DCV email action

Module API endpoints include:

- `details`
- `dcv`

### 3. `webnic_dns`

HostBill DNS module for:

- zone creation
- zone deletion
- zone listing
- zone record listing
- add/edit/delete record
- nameserver summary
- supported record summary

UI includes:

- admin product config page
- admin app summary page
- DNS client controller integration

Module API endpoints include:

- `summary`
- `zones`

## Installation

Copy these module folders into the appropriate HostBill module directories in your target installation.

Expected HostBill runtime mapping:

- `webnic_domains` -> `includes/modules/Domain/webnic_domains`
- `webnic_ssl` -> `includes/modules/Hosting/webnic_ssl`
- `webnic_dns` -> `includes/modules/Hosting/webnic_dns`

> Important: this repository is a development workspace. Final runtime placement must match your HostBill module loader expectations.

## Configuration

### Domain Module

Configure in HostBill:

- `Username`
- `Password`
- `OTE Environment`
- `Registrant User ID`
- `Default WHOIS Privacy`
- `Default Proxy`

### SSL Module

Configure server credentials:

- WebNIC API username
- WebNIC API password
- OTE environment flag

Configure product option:

- `product_key`

### DNS Module

Configure server credentials:

- WebNIC API username
- WebNIC API password
- OTE environment flag

Configure product options:

- `dns_template`
- `maxdomain`
- `ns1` .. `ns4`
- `hide_billing`
- `hide_zone_management`

## Validation Performed

The following validation has been completed in this workspace:

- PHP syntax validation with `php -l`
- editor diagnostics check for touched PHP files

## Known Limitations

- Domain custom client-area controller was not added because a reliable HostBill reference pattern was not confirmed.
- SSL custom client-area controller was not added for the same reason.
- Final runtime behavior should be tested inside a real HostBill installation because controller discovery and template paths are environment-dependent.
- Some WebNIC response fields may vary by product/TLD/profile and may require final adjustment after integration testing.

## Recommended Next Steps

1. Deploy modules into a HostBill staging instance.
2. Verify controller routing for admin, user, and api scopes.
3. Test live actions against WebNIC OTE.
4. Refine field mappings per TLD and SSL product.
5. Add screenshots or internal runbooks if this repository will be handed to operations teams.

## Reference Sources Used

- `core_dev/` for HostBill module patterns
- `postman_webnic/Default module.openapi.json` for WebNIC endpoints and payload structures

## License / Attribution

- License: `GPL-3.0-or-later`
- Attribution: `Nguyen Thanh An by Pho Tue SoftWare Solutions JSC`