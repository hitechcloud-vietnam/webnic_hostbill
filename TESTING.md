# Testing Guide

## Purpose

This document defines the detailed testing checklist, staging execution plan, and evidence requirements for the custom WebNIC HostBill modules:

- `webnic_domains`
- `webnic_ssl`
- `webnic_dns`

## Current Runtime Status

Workspace-level validation completed:

- PHP syntax validation passed for module classes and controllers
- editor diagnostics for documentation files are clean

Runtime testing inside a real HostBill staging environment is still **pending** because this repository does not include:

- a runnable HostBill staging instance
- staging database access
- WebNIC OTE credentials bound to a live HostBill server record
- staging products, accounts, domains, and DNS services

Use this guide as the execution checklist for staging sign-off.

## Evidence Required Per Test

For every executed test case, capture:

1. test case ID
2. executor
3. execution date/time
4. HostBill environment URL
5. module name
6. input data used
7. expected result
8. actual result
9. screenshot or API response excerpt
10. pass/fail/block status

## Global Preconditions

Before running any module-specific test:

- HostBill staging is reachable
- PHP outbound HTTPS works
- WebNIC OTE credentials are valid
- target modules are copied into HostBill runtime paths
- at least one staging admin user is available
- mail delivery method for test notifications is known
- rollback backup is prepared

## Environment Checklist

### HostBill

- [ ] HostBill admin login works
- [ ] module directories are readable by PHP
- [ ] no fatal errors on module discovery
- [ ] server records can be edited
- [ ] product records can be edited
- [ ] domain/account detail pages load normally

### WebNIC OTE

- [ ] domain reseller access confirmed
- [ ] SSL ordering access confirmed
- [ ] DNS API access confirmed
- [ ] token generation succeeds
- [ ] rate limiting or IP restrictions reviewed

### Test Data

- [ ] one available domain for registration testing
- [ ] one renewable domain
- [ ] one transfer candidate if transfer flow is enabled
- [ ] one SSL-capable hostname and CSR
- [ ] one DNS zone test domain

## 1. Smoke Test Matrix

### 1.1 Domain Module Smoke Tests

| ID | Test | Expected Result |
|---|---|---|
| DOM-SM-01 | Open registrar configuration | Fields load without PHP error |
| DOM-SM-02 | Save valid credentials | Save succeeds |
| DOM-SM-03 | Run connection validation path | Authentication succeeds |
| DOM-SM-04 | Open domain admin details page | Snapshot panels render |
| DOM-SM-05 | Call API `snapshot` | Structured payload returned |
| DOM-SM-06 | Execute lock action | Domain becomes locked or action succeeds |
| DOM-SM-07 | Execute unlock action | Domain becomes unlocked or action succeeds |
| DOM-SM-08 | Execute send verify action | API accepts resend request |
| DOM-SM-09 | Execute reset EPP action | Action returns success or expected policy error |
| DOM-SM-10 | Download certificate | Download flow returns file or expected vendor response |

### 1.2 SSL Module Smoke Tests

| ID | Test | Expected Result |
|---|---|---|
| SSL-SM-01 | Open SSL server config | Fields render correctly |
| SSL-SM-02 | Open product config | `product_key` field renders |
| SSL-SM-03 | Save valid SSL credentials | Save succeeds |
| SSL-SM-04 | Open service details page | Certificate detail panel renders |
| SSL-SM-05 | Call API `details` | Structured certificate payload returned |
| SSL-SM-06 | Change DCV to email | Action succeeds |
| SSL-SM-07 | Change DCV to DNS | Action succeeds |
| SSL-SM-08 | Change DCV to HTTP | Action succeeds |
| SSL-SM-09 | Resend DCV email | Action succeeds or returns vendor-side business rule |
| SSL-SM-10 | Download certificate after issuance | Certificate bundle downloads |

### 1.3 DNS Module Smoke Tests

| ID | Test | Expected Result |
|---|---|---|
| DNS-SM-01 | Open DNS server config | Fields render correctly |
| DNS-SM-02 | Open DNS product config | Option fields render |
| DNS-SM-03 | Open DNS app summary page | Nameservers and record types load |
| DNS-SM-04 | Call API `summary` | Summary payload returned |
| DNS-SM-05 | Call API `zones` | Zones list returned |
| DNS-SM-06 | Create zone | Zone is created in WebNIC |
| DNS-SM-07 | List zone records | Records load correctly |
| DNS-SM-08 | Add record | Record appears in zone |
| DNS-SM-09 | Edit record | Updated record is visible |
| DNS-SM-10 | Delete record | Record disappears |
| DNS-SM-11 | Open client DNS UI | Page loads without fatal errors |

## 2. Detailed Integration Checklist

### 2.1 Domain Registration Flow

- [ ] availability lookup returns sane output
- [ ] premium/non-premium handling does not break UI
- [ ] register request sends correct contacts
- [ ] nameservers are stored correctly
- [ ] privacy default follows configuration
- [ ] proxy default follows configuration
- [ ] returned order/reference identifiers are preserved
- [ ] sync after registration reflects correct expiry and status

### 2.2 Domain Contact and Control Flow

- [ ] contact query loads all expected roles
- [ ] contact update persists correctly
- [ ] lock state change is visible after refresh
- [ ] unlock state change is visible after refresh
- [ ] EPP/auth info can be reset or sent according to policy
- [ ] verification resend handles vendor policy errors gracefully
- [ ] transfer status query works with and without stored transfer ID

### 2.3 SSL Provisioning Flow

- [ ] `product_key` maps to correct WebNIC product
- [ ] initial order creation stores returned order ID
- [ ] CSR is retained for admin view
- [ ] SAN list is preserved
- [ ] approver email list loads for each hostname
- [ ] DCV details are updated after DCV change
- [ ] synchronization updates order and certificate status fields
- [ ] download flow returns usable bundle after issuance

### 2.4 DNS Lifecycle Flow

- [ ] zone creation works for subscribed DNS service
- [ ] nameserver defaults display correctly
- [ ] supported record types match API or fallback list
- [ ] record normalization preserves TTL/type/name/value
- [ ] delete flow only removes targeted record
- [ ] zone deletion succeeds and disappears from list
- [ ] client DNS page respects product visibility flags

## 3. Negative Test Checklist

### Common

- [ ] invalid username/password shows readable error
- [ ] OTE/live mismatch shows readable error
- [ ] network timeout is logged and surfaced safely
- [ ] empty required config field blocks action cleanly

### Domain

- [ ] unsupported TLD policy returns readable message
- [ ] missing registrant user ID blocks connection validation
- [ ] invalid contact payload fails without fatal error
- [ ] duplicate transfer attempt returns controlled error

### SSL

- [ ] missing `product_key` blocks create flow
- [ ] invalid CSR returns readable error
- [ ] unsupported DCV choice returns readable error
- [ ] resend DCV without email context fails safely

### DNS

- [ ] deleting record without zone fails safely
- [ ] editing record with incomplete data fails safely
- [ ] unsupported record type does not corrupt UI
- [ ] zone lookup failure returns empty-safe response

## 4. Regression Checklist

- [ ] admin pages still load after saving credentials
- [ ] API controllers still return arrays in expected shape
- [ ] no syntax errors after packaging
- [ ] release package contains only the three target modules
- [ ] documentation paths still match actual release layout

## 5. Suggested Staging Execution Order

1. deploy release package
2. validate module discovery
3. configure domain server
4. configure SSL server
5. configure DNS server
6. configure SSL and DNS products
7. execute smoke tests
8. execute module-specific integration tests
9. execute negative tests
10. execute UAT scenarios
11. collect sign-off evidence

## 6. Pass/Fail Gates

### Minimum Gate for Staging Sign-Off

- all smoke tests passed
- no fatal PHP errors
- all API controller actions return deterministic payloads
- one successful end-to-end scenario per module completed

### Minimum Gate for Production Promotion

- staging sign-off approved
- rollback tested
- operations handover completed
- OTE flags disabled for production server records

## 7. Execution Log Template

```text
Test ID:
Module:
Environment:
Executor:
Date:
Input:
Expected:
Actual:
Evidence:
Status: PASS / FAIL / BLOCKED
Notes:
```