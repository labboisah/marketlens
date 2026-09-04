# MarketLens Platform Agent Guide

This application is the MarketLens cloud Platform. It is multi-tenant and owns
cloud-side administration, reporting, subscription, device, synchronization, and
Monitoring App API concerns.

## Platform Boundaries

- The Platform does not operate business sales directly.
- The Platform receives synchronized records from Business Servers.
- The Platform authenticates Monitoring App users.
- The Platform manages subscriptions, businesses, branches, and Business Server
  devices.
- The Platform must not become the authentication authority for operational Shop
  App users.
- Only limited operational-user profiles are synchronized for reporting.
- Desktop source lives outside this repository: `C:\laragon\www\business-server`
  owns the Business Server/local server and `C:\laragon\www\business-app` owns the
  Client App. Platform work must not recreate `apps/desktop` or keep duplicate
  desktop packages under the Platform tree.

## Data Isolation

- Tenant data must always be scoped by `business_id`.
- Branch data must always be scoped by `branch_id`.
- Policies, validation, and rate limits are required for API endpoints.
- Never bypass tenant or branch scoping in reports, background jobs, API
  resources, or administrative screens.

## Processing Rules

- Synchronization processing should use queued jobs.
- Expensive reports should use summaries, caches, or materialized structures.
- Preserve immutable synchronized transactional records.
- Create corrective records instead of mutating business history.

## Security

- Secrets must remain outside the repository.
- Do not commit `.env`, credentials, production tokens, private keys, customer
  data, generated exports, or local databases.
- Keep production configuration in deployment secrets or a secret manager.
