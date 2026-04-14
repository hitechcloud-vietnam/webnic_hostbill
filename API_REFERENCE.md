# API Reference

## Scope

This document summarizes the internal module API controllers added for the WebNIC HostBill modules.

These are HostBill module API controller actions, not direct public WebNIC endpoints.

## Notes

- Final invocation depends on HostBill API routing conventions.
- Parameters shown here are implementation-level references for development and integration.
- Return payloads are shaped through HostBill module API dispatch.

---

## 1. Domain Module API

Controller:

- `webnic_domains/api/class.webnic_domains_controller.php`

### `snapshot`

Returns a combined administrative snapshot for one domain.

#### Input

- `domain_id` or `id` - HostBill domain ID

#### Output

- `snapshot.domain`
- `snapshot.info`
- `snapshot.contacts`
- `snapshot.transfer`
- `snapshot.status`

### `actions`

Executes one supported domain action.

#### Input

- `domain_id` or `id` - HostBill domain ID
- `ac` - action name

#### Supported `ac` Values

- `lock`
- `unlock`
- `syncContacts`
- `sendVerify`
- `resetEpp`

#### Output

- `result`

---

## 2. SSL Module API

Controller:

- `webnic_ssl/api/class.webnic_ssl_controller.php`

### `details`

Returns current SSL certificate details for a HostBill account/service.

#### Input

- `account_id` or `id` - HostBill service/account ID

#### Output

- `certificate.cn`
- `certificate.order_id`
- `certificate.status`
- `certificate.csr`
- `certificate.san`
- `certificate.dcv`
- `certificate.dcv_details`
- `certificate.dcv_status`

### `dcv`

Changes the current DCV method.

#### Input

- `account_id` or `id` - HostBill service/account ID
- `dcv` - one of `email`, `http`, `dns`
- `dcv_email` - required when using email DCV

#### Output

- `result`

---

## 3. DNS Module API

Controller:

- `webnic_dns/api/class.webnic_dns_controller.php`

### `summary`

Returns DNS application summary from the configured WebNIC server.

#### Input

- `server_id` - HostBill server ID

#### Output

- `summary.nameservers`
- `summary.supported_records`
- `summary.zone_limit`

### `zones`

Returns available zones from the configured WebNIC server.

#### Input

- `server_id` - HostBill server ID

#### Output

- `zones[]`
  - `id`
  - `domain`
  - `type`
  - `subscription`

---

## Underlying WebNIC Endpoint Usage Summary

### Domain Module

Examples used by current implementation:

- `POST /domain/v2/register`
- `POST /domain/v2/renew`
- `POST /domain/v2/transfer-in`
- `DELETE /domain/v2/delete`
- `GET /domain/v2/query`
- `GET /domain/v2/whois`
- `GET /domain/v2/top-domain-available-list`
- `GET /domain/v2/info`
- `POST /domain/v2/update`
- `POST /domain/v2/contact/create`
- `POST /domain/v2/contact/modify`
- `GET /domain/v2/contact/query`
- `POST /domain/v2/auth-info/send`
- `POST /domain/v2/auth-info/reset`
- `POST /domain/v2/resend-verification-email`
- `GET /domain/v2/download/certificate`
- `GET /domain/v2/transfer-in/status`

### SSL Module

Examples used by current implementation:

- `POST /ssl/v2/orders/new`
- `POST /ssl/v2/orders/{orderId}/renew`
- `POST /ssl/v2/orders/{orderId}/reissue`
- `POST /ssl/v2/orders/{orderId}/cancel`
- `GET /ssl/v2/orders/info`
- `GET /ssl/v2/orders/{orderId}/auth/info`
- `POST /ssl/v2/orders/{orderId}/auth`
- `GET /ssl/v2/domainValidations/approver-list`
- `POST /ssl/v2/contact/create`
- `POST /ssl/v2/orders/{orderId}/download/format/{format}`

### DNS Module

Examples used by current implementation:

- `POST /dns/v2/zone/{domain}/nameserver-subscription`
- `DELETE /dns/v2/zone/{domain}`
- `GET /dns/v2/zones`
- `GET /dns/v2/zone/{domain}/records`
- `POST /dns/v2/zone/{domain}/record`
- `DELETE /dns/v2/zone/{domain}/record`
- `GET /dns/v2/zone/record-types`
- `GET /dns/v2/zone/subscription/record/nameservers`
- `GET /dns/v2/zone/basic/record/nameservers`

## Integration Warning

Field shapes may vary depending on:

- TLD policy
- WebNIC account profile
- SSL product type
- DNS subscription type

Use staging verification before relying on exact payload shapes in production.