# `webnic_ssl` Documentation

## Purpose

`webnic_ssl` is a clean-room HostBill SSL provisioning module for WebNIC SSL products.

## Implemented Features

- Create certificate order
- Renew certificate order
- Reissue certificate order
- Cancel certificate order
- Resolve DCV options
- Fetch approver email candidates
- Synchronize order and certificate status
- Download issued certificate bundle
- Change DCV method
- Resend DCV email

## Server Credentials

The module expects standard HostBill server connection data:

- username
- password
- OTE checkbox flag

## Product Options

- `product_key`

This value should match a valid WebNIC SSL product key.

## Admin Controller

Path:

- `admin/class.webnic_ssl_controller.php`

Implemented actions:

- `productdetails`
- `accountdetails`

## Admin Templates

- `admin/myproductconfig.tpl`
- `admin/details.tpl`

## Module API Controller

Path:

- `api/class.webnic_ssl_controller.php`

Implemented API calls:

- `details`
- `dcv`

## Important Methods

- `Create()`
- `Renewal()`
- `Reissue()`
- `Terminate()`
- `CertOptions()`
- `CertContacts()`
- `CertDCVEmail()`
- `CertSynchronize()`
- `getSynchInfo()`
- `downloadCertificate()`
- `changeDCV()`
- `ResendDCVEmail()`
- `CertDcvDns()`
- `CertDcvHttp()`

## DCV Handling

Supported DCV modes in the current implementation:

- Email
- DNS
- HTTP

Internal mapping note:

- WebNIC `file` validation is exposed in UI as `http`

## Notes

- Product catalog driven behavior such as wildcard/SAN support depends on the catalog response.
- Organization validation workflows may need extra fields for OV/EV products depending on real WebNIC account requirements.
- Certificate download format handling should be validated with real issued orders.