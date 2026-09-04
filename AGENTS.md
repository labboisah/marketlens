# MarketLens Agent Guide

This file defines root-level instructions for coding agents working in this
repository. Read and follow these rules before changing code or documentation.

## Required Reading

- Read the root [README.md](README.md) before changing code.
- Read the nearest application-level `AGENTS.md` before changing files inside an
  application or package, when that file exists.
- Use [docs/architecture/system-overview.md](docs/architecture/system-overview.md)
  as the baseline system architecture reference.
- The Laravel Platform application lives at the repository root. The desktop applications are maintained as standalone sibling projects:
  `C:\laragon\www\business-server` for the Business Server/local server and
  `C:\laragon\www\business-app` for the Business App. Do not recreate or maintain
  duplicate desktop sources under `apps/desktop`.

## Platform Root App

The repository root is the MarketLens cloud Platform Laravel app. It is multi-tenant and owns cloud-side administration, reporting, subscription, device, synchronization, and Monitoring App API concerns.

- The Platform does not operate business sales directly.
- The Platform receives synchronized records from Business Servers.
- The Platform authenticates Monitoring App users.
- The Platform manages subscriptions, businesses, branches, and Business Server devices.
- The Platform must not become the authentication authority for operational Business Server users.
- Only limited operational-user profiles are synchronized for reporting.
## Architecture Rules

- Preserve the four-application architecture: Platform, Business Server, Business App,
  and Monitoring App.
- Keep the Business Server as the local source of truth for business operations.
- Never let Business Apps write directly to SQLite.
- Never let Business Apps bypass the Business Server local API for operational writes.
- Never let the Monitoring App connect directly to a Business Server.
- Keep the Monitoring App dependent on synchronized Platform data only.
- Maintain tenant and branch data isolation in storage, APIs, background jobs,
  synchronization, reporting, and authorization checks.
- Use UUIDs for synchronized records.
- Preserve immutable transactional records.
- Create stock movements instead of directly changing stock quantities.
- Create ledger transactions instead of directly changing debt balances.
- Avoid silently changing shared API contracts, synchronization contracts, or
  shared validation behavior.

## Engineering Rules

- Add tests for every business rule.
- Use strict typing and validation at application boundaries.
- Update related documentation when architecture changes.
- Never commit secrets, generated credentials, production environment values,
  customer data, private keys, or local database files.
- Prefer simple, maintainable solutions over premature complexity.
- Keep shared packages stable and explicit; contract changes should be reviewed
  as product and architecture changes, not incidental refactors.
- Follow the approved technology stack unless the user explicitly approves a
  change.

## Approved Technology Stack

### Platform

- Laravel, latest stable version.
- PHP, supported stable version.
- Livewire.
- Alpine.js.
- Tailwind CSS.
- Flux UI.
- Vite.
- PostgreSQL.
- Redis.
- Laravel Horizon.
- Laravel Sanctum.
- Laravel Reverb.
- Pest.
- Larastan.
- Laravel Pint.
- Docker Compose.
- Nginx.
- S3-compatible storage.

### Business Server

- Electron.
- TypeScript.
- React.
- Vite.
- Tailwind CSS.
- shadcn/ui.
- Lucide Icons.
- Node.js LTS.
- Fastify.
- SQLite.
- better-sqlite3.
- Drizzle ORM.
- Zod.
- Socket.IO.
- Pino.
- Vitest.
- Playwright.
- Electron Forge.

### Business App

- Electron.
- TypeScript.
- React.
- Vite.
- Tailwind CSS.
- shadcn/ui.
- Lucide Icons.
- TanStack Query.
- Zustand.
- React Hook Form.
- Zod.
- TanStack Table.
- TanStack Virtual.
- Socket.IO Client.
- Vitest.
- Playwright.
- Electron Forge.

### Monitoring App

- Flutter.
- Dart.
- Material 3.
- Riverpod.
- GoRouter.
- Dio.
- Freezed.
- json_serializable.
- Drift.
- flutter_secure_storage.
- Firebase Cloud Messaging.
- Firebase Crashlytics.
- fl_chart.
- local_auth.
- connectivity_plus.
- Flutter Test.
