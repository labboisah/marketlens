# MarketLens System Overview

MarketLens is an offline-first retail platform built around one central rule:
businesses must continue operating locally even when the internet is unavailable,
while owners still receive secure remote visibility through synchronized cloud
data.

## Application Flow

```text
Business Server
    | Internet synchronization
    v
Platform
    | Secure cloud API
    v
Monitoring App
```

## Business App

The Business App runs on cashier, storekeeper, manager, and accountant computers.
It provides role-based interfaces for daily work and communicates with the Shop
App through the local network.

The Business App does not directly access SQLite. It sends authenticated requests
to the Business Server local API, receives validated responses, and listens for
real-time updates through the approved local communication layer.

## Business Server

The Business Server runs on the main computer in each business or branch. It is the local
source of truth for operational activity in that branch.

The Business Server owns:

- Local API server behavior.
- Local SQLite persistence.
- Operational user registration.
- Permission enforcement for local operations.
- Sales, inventory, stock movement, debt, and ledger writes.
- Synchronization with the Platform.

Stock quantities should change through stock movement records. Debt balances
should change through ledger transactions. Transactional records should remain
immutable and auditable.

## Platform

The Platform is the central cloud application. It is multi-tenant and manages
businesses, branches, subscriptions, devices, synchronized records, reporting,
remote APIs, and cloud-side authorization.

The Platform receives synchronized data from Business Servers over the internet. It
must maintain tenant and branch isolation for every stored record, API response,
background job, and report.

## Monitoring App

The Monitoring App is an Android-first mobile app for business owners and
authorized remote users. It connects only to the Platform through secure cloud
APIs.

The Monitoring App must never connect directly to a Business Server. It displays
remote business information using synchronized Platform data.

## Synchronization Principles

- Synchronized records use UUIDs.
- Local operations must remain available when the Platform is unreachable.
- Synchronization should preserve transactional history.
- Conflicts must be handled explicitly and auditable where business impact
  exists.
- Shared synchronization contracts must not change silently.

## Data Ownership

- Operational writes are owned locally by the Business Server.
- Cloud reporting and remote monitoring are owned by the Platform.
- Business Apps submit local operational requests; they do not own persistence.
- Monitoring App consumes remote reporting data; it does not own operational
  writes.

## Security Boundaries

- Business App to Business Server: local LAN or Wi-Fi, authenticated and authorized.
- Business Server to Platform: internet synchronization, authenticated and encrypted.
- Monitoring App to Platform: secure cloud API, authenticated and authorized.

Tenant isolation, branch isolation, strict validation, and role-based
authorization are required across all boundaries.

## Branch operating modes

Each branch is configured for one or more of `sales`, `production`, and
`services`. This configuration is distinct from the business industry label,
subscription entitlements, branch module settings, and user permissions.
Shareholder management remains an independent supportive capability rather than
an operating mode. The Platform returns a branch's configured modes during
device activation and status refresh; the Business Server persists that
configuration locally and enforces mode-specific operations while offline.
Business App navigation and Monitoring App branch views use the same mode list,
but the Server and Platform APIs remain the authorization boundaries.
Catalog, inventory, and procurement workflows are available for sales or
production branches; sales, production, and services workflows each require
their corresponding configured mode. Shareholder access remains independently
controlled by its module entitlement and user permissions.


## Recovery and desktop release trust

The Business Server owns local backup, restore and recovery execution. Platform restore requests require local confirmation; the Platform stores encrypted copies and authorizes tenant/branch-scoped access. Replacement recovery uses an independently stored encrypted kit and retains the original server identity, disables historical operational access and pauses synchronization pending reconciliation and device credential rotation. The original server must be retired. Operational authentication remains local.

Desktop release manifests/catalog downloads remain Platform responsibilities. Packaged desktop updates verify the artifact checksum and a valid signature against publisher certificates pinned at build time. Business Server upgrades drain local work before taking their pre-update snapshot. See [the remediation report](../audits/2026-09-07-production-remediation.md) for verification and deployment gates.

## Production operations

Production batch stock, immutable cost layers, profit-sharing rules and beneficiary ledgers remain owned by the Business Server. The Business App uses authenticated local production APIs. The Platform accepts versioned production_record snapshots and applies separate operational/financial monitoring permissions and branch scope. See [Production operations](../product/production-operations.md) for the implementation boundaries and upgrade sequence.
