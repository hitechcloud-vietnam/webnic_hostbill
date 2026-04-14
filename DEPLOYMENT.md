# Deployment Guide

## Purpose

This guide describes how to deploy the custom WebNIC HostBill modules from this repository into a HostBill staging or production environment.

## Modules Covered

- `webnic_common`
- `webnic_domains`
- `webnic_ssl`
- `webnic_dns`

## Recommended Deployment Order

1. Prepare HostBill staging environment
2. Back up current module directories and database
3. Copy module files to HostBill runtime paths
4. Verify file permissions and PHP extensions
5. Configure servers and products in HostBill
6. Test WebNIC OTE connectivity
7. Run end-to-end smoke tests
8. Promote to production only after staging sign-off

## Runtime Target Paths

Map repository folders into HostBill as follows:

### Domain Module

Repository source:

- `webnic_domains/`

Target runtime path:

- `includes/modules/Domain/webnic_domains/`

### SSL Module

Repository source:

- `webnic_ssl/`

Target runtime path:

- `includes/modules/Hosting/webnic_ssl/`

### DNS Module

Repository source:

- `webnic_dns/`

Target runtime path:

- `includes/modules/Hosting/webnic_dns/`

### Shared Library

Repository source:

- `webnic_common/`

Recommended target:

- keep as a shared sibling folder accessible by the deployed modules

Because the modules use relative `require_once` references, preserve the relative directory relationship between:

- `webnic_domains`
- `webnic_ssl`
- `webnic_dns`
- `webnic_common`

## Pre-Deployment Checklist

## HostBill

- HostBill instance is working normally
- Admin access is available
- Module loader can discover custom modules
- Staging environment is preferred before production

## PHP

- PHP cURL extension enabled
- JSON extension enabled
- TLS/HTTPS outbound access allowed
- `tempnam()` and temporary file creation allowed for SSL bundle download flow

## Network

- Outbound access to `api.webnic.cc`
- Outbound access to `oteapi.webnic.cc`
- Firewall permits HTTPS requests

## Backup

Before deployment, back up:

- HostBill files
- HostBill database
- existing custom modules with the same names

## Deployment Steps

## Step 1: Copy Files

Copy the module folders into the matching HostBill module directories.

Preserve the following folder contents:

- `admin/`
- `api/`
- `templates/` where present
- `user/` where present
- main `class.*.php` files

## Step 2: Verify Relative Includes

These modules depend on:

- `webnic_common/lib/class.webnic_api_client.php`

If runtime placement changes the relative path, adjust deployment structure before testing.

## Step 3: Create/Update HostBill Server Definitions

### Domain Server / Registrar Configuration

Populate:

- Username
- Password
- OTE Environment
- Registrant User ID
- Default WHOIS Privacy
- Default Proxy

### SSL Server Configuration

Populate:

- username
- password
- OTE checkbox flag

### DNS Server Configuration

Populate:

- username
- password
- OTE checkbox flag

## Step 4: Create/Update Products

### SSL Products

Set:

- `product_key`

Use a valid WebNIC product key from your WebNIC catalog.

### DNS Products

Set as needed:

- `dns_template`
- `maxdomain`
- `ns1` to `ns4`
- `hide_billing`
- `hide_zone_management`

## Step 5: Initial Connectivity Test

Use OTE first.

Validate:

- token authentication succeeds
- admin product/config pages load
- API credentials are accepted
- no fatal PHP errors occur in module controllers

## Step 6: Smoke Test Matrix

### Domain Module

- availability check
- register test domain
- renew test domain
- transfer test domain if available
- update nameservers
- update contacts
- send verification email
- lock/unlock domain
- download ownership certificate

### SSL Module

- open product config page
- create test certificate order
- verify DCV options load
- change DCV method
- resend DCV email
- synchronize order status
- download certificate after issuance

### DNS Module

- open DNS product config page
- verify app summary data loads
- create zone
- list zones
- add/edit/delete records
- open client DNS management view

## Production Promotion Checklist

- staging tests passed
- rollback package ready
- production credentials verified
- OTE mode disabled for production server records
- operations team informed of changed modules

## Rollback Plan

If deployment fails:

1. disable the affected modules in HostBill if needed
2. restore previous module directories
3. restore database backup if configuration corruption occurred
4. clear caches if your HostBill environment uses them
5. retest admin and client critical paths

## Troubleshooting

## Module Not Detected

Check:

- target directory path
- class filename
- main class name
- file permissions

## Admin Page Loads but Action Fails

Check:

- WebNIC credentials
- OTE/live mode mismatch
- API validation errors returned by WebNIC
- HostBill product/account configuration completeness

## Shared Client Include Failure

Check:

- relative placement of `webnic_common`
- Windows/Linux path separator assumptions in deployment packaging

## SSL Download Issues

Check:

- PHP temp directory permissions
- cURL upload support
- returned certificate status is actually issued

## DNS Client UI Missing

Check:

- module deployed under correct Hosting path
- `user/class.webnic_dns_controller.php` present
- HostBill DNS type/controller wiring in target installation

## Post-Deployment Recommendation

Create an internal staging record with:

- tested WebNIC account
- tested domain TLDs
- tested SSL product keys
- tested DNS scenarios
- known failures and payload examples