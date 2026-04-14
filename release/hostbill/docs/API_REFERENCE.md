# API Reference

## Scope

This document summarizes the internal module API controllers added for the WebNIC HostBill modules.

These are HostBill module API controller actions, not direct public WebNIC endpoints.

## Notes

- Final invocation depends on HostBill API routing conventions.
- Parameters shown here are implementation-level references for development and integration.
- Return payloads are shaped through HostBill module API dispatch.
- Examples below are representative and sanitized, based on the implemented controller contract.

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

#### Example Request

```text
GET /?cmd=moduleapi&module=webnic_domains&call=snapshot&domain_id=1042
```

#### Example Response

```json
{
  "success": true,
  "snapshot": {
    "domain": "example.com",
    "info": {
      "domainName": "example.com",
      "domainStatus": "ACTIVE",
      "expiryDate": "2027-04-14",
      "registrarLock": true,
      "whoisPrivacy": false
    },
    "contacts": {
      "registrant": {
        "first_name": "Jane",
        "last_name": "Doe",
        "email": "jane@example.com",
        "contact_id": "C10001"
      },
      "administrator": {
        "first_name": "Ops",
        "last_name": "Team",
        "email": "ops@example.com",
        "contact_id": "C10002"
      }
    },
    "transfer": {
      "transferId": "TRF-99881",
      "status": "PENDING"
    },
    "status": {
      "status": "Active",
      "expires": "2027-04-14"
    }
  }
}
```

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

#### Example Request

```text
POST /?cmd=moduleapi&module=webnic_domains&call=actions
domain_id=1042
ac=lock
```

#### Example Response

```json
{
  "success": true,
  "result": true
}
```

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

#### Example Request

```text
GET /?cmd=moduleapi&module=webnic_ssl&call=details&account_id=5501
```

#### Example Response

```json
{
  "success": true,
  "certificate": {
    "cn": "www.example.com",
    "order_id": "SSL-20260415-001",
    "status": "Processing",
    "csr": "-----BEGIN CERTIFICATE REQUEST-----...",
    "san": [
      "example.com",
      "api.example.com"
    ],
    "dcv": "email",
    "dcv_details": [
      {
        "type": "email",
        "domain": "www.example.com",
        "value": "admin@example.com"
      }
    ],
    "dcv_status": "PENDING"
  }
}
```

### `dcv`

Changes the current DCV method.

#### Input

- `account_id` or `id` - HostBill service/account ID
- `dcv` - one of `email`, `http`, `dns`
- `dcv_email` - required when using email DCV

#### Output

- `result`

#### Example Request

```text
POST /?cmd=moduleapi&module=webnic_ssl&call=dcv
account_id=5501
dcv=email
dcv_email=admin@example.com
```

#### Example Response

```json
{
  "success": true,
  "result": true
}
```

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

#### Example Request

```text
GET /?cmd=moduleapi&module=webnic_dns&call=summary&server_id=23
```

#### Example Response

```json
{
  "success": true,
  "summary": {
    "nameservers": [
      "ns1.webnic.test",
      "ns2.webnic.test"
    ],
    "supported_records": [
      "A",
      "AAAA",
      "CNAME",
      "MX",
      "SRV",
      "TXT"
    ],
    "zone_limit": 100
  }
}
```

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

#### Example Request

```text
GET /?cmd=moduleapi&module=webnic_dns&call=zones&server_id=23
```

#### Example Response

```json
{
  "success": true,
  "zones": [
    {
      "id": "SUB-1001",
      "domain": "example.com",
      "type": "primary",
      "subscription": true
    },
    {
      "id": "SUB-1002",
      "domain": "example.net",
      "type": "primary",
      "subscription": true
    }
  ]
}
```

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

Representative upstream payloads:

```http
POST /domain/v2/register
Content-Type: application/json
Authorization: Bearer <jwt>

{
  "domainName": "example.com",
  "period": 1,
  "nameserver": ["ns1.example.net", "ns2.example.net"],
  "registrantContactId": "C10001",
  "administratorContactId": "C10002",
  "technicalContactId": "C10003",
  "billingContactId": "C10004"
}
```

```json
{
  "status": "SUCCESS",
  "data": {
    "domainName": "example.com",
    "orderId": "DOM-20260415-1001",
    "expiryDate": "2027-04-14"
  }
}
```

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

Representative upstream payloads:

```http
POST /ssl/v2/orders/{orderId}/auth
Content-Type: application/json
Authorization: Bearer <jwt>

{
  "authType": "email",
  "approverEmail": "admin@example.com"
}
```

```json
{
  "status": "SUCCESS",
  "data": {
    "type": "email",
    "domain": "www.example.com",
    "value": "admin@example.com"
  }
}
```

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

Representative upstream payloads:

```http
POST /dns/v2/zone/example.com/record
Content-Type: application/json
Authorization: Bearer <jwt>

{
  "name": "@",
  "type": "A",
  "ttl": 3600,
  "rdatas": ["203.0.113.10"]
}
```

```json
{
  "status": "SUCCESS",
  "data": {
    "zone": "example.com",
    "name": "@",
    "type": "A"
  }
}
```

## Integration Warning

Field shapes may vary depending on:

- TLD policy
- WebNIC account profile
- SSL product type
- DNS subscription type

Use staging verification before relying on exact payload shapes in production.