# UAT Scenarios

## Purpose

This document defines detailed user acceptance scenarios for the three WebNIC HostBill modules.

## UAT Status Model

Use one of these values for each scenario:

- `Not Run`
- `Passed`
- `Failed`
- `Blocked`

## 1. Domain Module UAT

### UAT-DOM-01 New Registration Through Admin Workflow

**Goal**

Operations staff can configure the registrar and register a new domain in staging.

**Preconditions**

- domain server config exists
- valid WebNIC OTE credentials are saved
- test domain is available

**Steps**

1. open HostBill admin registrar configuration
2. save valid WebNIC OTE credentials
3. create or select a domain order using `webnic_domains`
4. execute registration
5. open domain details view
6. confirm snapshot data loads

**Expected Result**

- registration succeeds
- no fatal PHP error occurs
- snapshot shows domain info, contacts, and status

### UAT-DOM-02 Contact Sync and Registrar Controls

**Goal**

Operations staff can maintain registrar-side controls from the admin UI.

**Steps**

1. open domain admin details
2. review contact panel
3. trigger contact sync
4. trigger lock
5. refresh and confirm lock state
6. trigger unlock
7. trigger resend verification email
8. trigger EPP reset/send flow if policy permits

**Expected Result**

- actions succeed or return clear vendor policy messages
- state remains consistent after refresh

### UAT-DOM-03 Transfer Monitoring

**Goal**

Admin can monitor a transfer-in case from the module snapshot/API layer.

**Steps**

1. prepare transfer-eligible test domain
2. submit transfer flow
3. call module API `snapshot`
4. verify transfer section contains status data

**Expected Result**

- transfer information is visible from the module API and admin view

## 2. SSL Module UAT

### UAT-SSL-01 New SSL Order

**Goal**

Operations staff can create an SSL order mapped to a WebNIC product key.

**Preconditions**

- SSL server config exists
- valid `product_key` is configured on the product
- CSR is available

**Steps**

1. open SSL product config and confirm `product_key`
2. create a service using `webnic_ssl`
3. submit certificate order
4. open admin details view
5. review CN, order ID, DCV, and SAN data

**Expected Result**

- order is created
- order ID is stored
- admin detail view renders expected fields

### UAT-SSL-02 DCV Management

**Goal**

Admin can change DCV method and retrieve updated validation instructions.

**Steps**

1. open SSL admin details
2. switch DCV to email
3. confirm approver email is accepted
4. switch DCV to DNS
5. review DNS validation values
6. switch DCV to HTTP
7. review HTTP/file validation values

**Expected Result**

- each DCV change succeeds
- returned validation instructions match the selected method

### UAT-SSL-03 Certificate Retrieval

**Goal**

Issued certificate can be synchronized and downloaded by admin.

**Steps**

1. wait until staging order is issued
2. run synchronize flow
3. confirm certificate status updates
4. download certificate bundle

**Expected Result**

- synchronized status is current
- certificate bundle is downloadable

## 3. DNS Module UAT

### UAT-DNS-01 DNS Service Setup

**Goal**

Admin can configure DNS service and verify summary metadata.

**Preconditions**

- DNS server config exists
- DNS product options are set

**Steps**

1. open DNS server config
2. save valid OTE credentials
3. open app summary page
4. confirm nameservers, record types, and zone limit are displayed

**Expected Result**

- summary page loads correctly
- metadata matches expected WebNIC response or fallback values

### UAT-DNS-02 Zone and Record Lifecycle

**Goal**

Admin can manage a DNS zone and its records through the module.

**Steps**

1. create a new test zone
2. list zones and confirm presence
3. add `A` record
4. add `TXT` record
5. edit one record
6. delete one record
7. verify final record set

**Expected Result**

- all operations complete without fatal error
- record list reflects each change correctly

### UAT-DNS-03 Client DNS View

**Goal**

Client-facing DNS page loads correctly for a provisioned service.

**Steps**

1. provision DNS-enabled service for a staging client
2. log into the client area
3. open DNS management view
4. confirm page loads and relevant controls are visible

**Expected Result**

- client page loads
- product flags such as hidden billing or hidden zone management are respected

## 4. Cross-Module UAT

### UAT-ALL-01 Release Package Deployment

**Goal**

Operations can deploy the packaged release into HostBill without hand-editing module paths.

**Steps**

1. generate the release package
2. copy `release/hostbill/*` into HostBill root
3. verify module discovery
4. open one admin page per module

**Expected Result**

- package layout is valid
- shared library is resolved correctly by all modules

### UAT-ALL-02 Error Handling Quality

**Goal**

Expected business or credential errors appear as readable operational messages.

**Steps**

1. temporarily use invalid credentials
2. run one operation in each module
3. review resulting UI/API errors

**Expected Result**

- no fatal error or blank page
- operator receives readable error context