# `webnic_domains` Documentation

## Purpose

`webnic_domains` is a clean-room HostBill registrar module for WebNIC domain management.

## Implemented Features

- Register domain
- Renew domain
- Transfer domain in
- Delete domain
- Query domain availability
- WHOIS lookup
- Domain suggestions
- Read/update nameservers
- Read/update WHOIS privacy
- Read/update registrar lock
- Send EPP/auth code
- Reset EPP/auth code
- Read/update domain contacts
- Resend registrant verification email
- Download domain ownership certificate
- Sync domain status into HostBill-friendly shape

## Configuration Fields

- `Username`
- `Password`
- `OTE Environment`
- `Registrant User ID`
- `Default WHOIS Privacy`
- `Default Proxy`

## Admin Controller

Path:

- `admin/class.webnic_domains_controller.php`

Supported admin actions:

- `_default`
- `domaindetails`
- `domaincontacts`
- `domaintransfer`
- `domainstatus`
- `domainaction`
- `downloadcertificate`

## Admin Templates

- `admin/domaindetails.tpl`
- `admin/ajax.domaindetails.tpl`
- `admin/ajax.domaincontacts.tpl`
- `admin/ajax.domaintransfer.tpl`
- `admin/ajax.domainstatus.tpl`
- `admin/ajax.domainactions.tpl`
- `admin/ajax.sendverify.tpl`
- `admin/ajax.certificate.tpl`

## Module API Controller

Path:

- `api/class.webnic_domains_controller.php`

Implemented API calls:

- `snapshot`
- `actions`

Example action values for `actions`:

- `lock`
- `unlock`
- `syncContacts`
- `sendVerify`
- `resetEpp`

## Important Methods

- `Register()`
- `Renew()`
- `Transfer()`
- `Delete()`
- `lookupDomain()`
- `whoisDomain()`
- `suggestDomains()`
- `getNameServers()`
- `updateNameServers()`
- `getIDProtection()`
- `updateIDProtection()`
- `Lock()`
- `Unlock()`
- `getEppCode()`
- `ChangeEpp()`
- `SendVerify()`
- `DownloadCertificate()`
- `getContactInfo()`
- `updateContactInfo()`
- `synchInfo()`

## Notes

- Contact creation and updates are based on WebNIC contact APIs.
- Transfer status handling depends on stored transfer metadata in `extended` details.
- Certificate download is returned as PDF binary data.
- Some TLD-specific contact requirements may need further extension after staging tests.