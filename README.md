# MarketLens

**See Every Sale. Control Every Stock.**

MarketLens is an offline-first retail sales, inventory, debt tracking, and
remote-monitoring platform for businesses that need reliable operations inside
businesses and clear visibility from anywhere.

The system is designed for environments where internet connectivity may be
unstable. Each business continues selling, receiving stock, managing users, and
recording operational activity locally. When internet access is available,
approved records synchronize to the cloud Platform for reporting, device
management, subscriptions, and owner monitoring.

## Business Problems

- Sales teams need to keep working even when internet access fails.
- Owners need remote visibility across branches without disrupting local business
  operations.
- Inventory changes must be auditable instead of hidden behind direct stock
  edits.
- Customer and supplier debts must be traceable through ledger transactions.
- Businesses need role-based workflows for cashiers, storekeepers, managers,
  accountants, and owners.
- Multi-branch businesses need consistent data isolation, synchronization, and
  reporting.

## Target Businesses

MarketLens is intended for small and medium retail businesses, wholesalers,
pharmacies, supermarkets, provision stores, electronics businesses, boutiques,
hardware stores, and multi-branch merchants that need resilient sales and
inventory control.

## Four-Application Architecture

MarketLens is composed of four applications with clear ownership boundaries.

### MarketLens Platform

The Platform is the central cloud application. It is multi-tenant and manages
businesses, subscriptions, branches, devices, synchronization, cloud APIs, and
remote reporting.

### MarketLens Business Server

The Business Server is installed on the main computer in each business or branch. It is
the local source of truth, runs the local API server, stores operational records
locally, registers operational users, and synchronizes records to the Platform.

### MarketLens Business App

The Business App is installed on cashier, storekeeper, manager, and accountant
computers. It connects to the Business Server over LAN or Wi-Fi, uses role-based
interfaces, and never accesses the local SQLite database directly.

### MarketLens Monitoring App

The Monitoring App is an Android-first mobile application for business owners
and authorized remote users. It reads synchronized records from the Platform and
never connects directly to a Business Server.

## High-Level Data Flow

```text
Business App
    | Local LAN/Wi-Fi
    v
Business Server
    | Internet synchronization
    v
Platform
    | Secure cloud API
    v
Monitoring App
```

Operational writes begin at the Business Server. Business Apps submit requests to the
Business Server local API. The Business Server validates permissions, applies business rules,
persists records in SQLite, and later synchronizes eligible records to the
Platform. The Monitoring App consumes Platform APIs only.

## Technology Stack

### Platform

Laravel, PHP, Livewire, Alpine.js, Tailwind CSS, Flux UI, Vite, PostgreSQL,
Redis, Laravel Horizon, Laravel Sanctum, Laravel Reverb, Pest, Larastan,
Laravel Pint, Docker Compose, Nginx, and S3-compatible storage.

### Business Server

Electron, TypeScript, React, Vite, Tailwind CSS, shadcn/ui, Lucide Icons,
Node.js LTS, Fastify, SQLite, better-sqlite3, Drizzle ORM, Zod, Socket.IO,
Pino, Vitest, Playwright, and Electron Forge.

### Business App

Electron, TypeScript, React, Vite, Tailwind CSS, shadcn/ui, Lucide Icons,
TanStack Query, Zustand, React Hook Form, Zod, TanStack Table, TanStack
Virtual, Socket.IO Client, Vitest, Playwright, and Electron Forge.

### Monitoring App

Flutter, Dart, Material 3, Riverpod, GoRouter, Dio, Freezed,
json_serializable, Drift, flutter_secure_storage, Firebase Cloud Messaging,
Firebase Crashlytics, fl_chart, local_auth, connectivity_plus, and Flutter
Test.

## Repository Structure

```text
laragon/www/
|-- business-server/          # standalone Business Server project
|   |-- packages/
|   `-- src/
|-- business-app/             # standalone Business App project
|   |-- packages/
|   `-- src/
`-- marketlens/               # Laravel Platform root app and repository docs
    |-- app/
    |-- bootstrap/
    |-- config/
    |-- database/
    |-- public/
    |-- resources/
    |-- routes/
    |-- tests/
    |-- apps/
    |   `-- monitoring-app/
    |-- docs/
    `-- .github/
```

The desktop applications are maintained as sibling standalone projects outside
the main `marketlens` repository:

- `C:\laragon\www\business-server`: MarketLens Business Server / local server.
- `C:\laragon\www\business-app`: MarketLens Business App.

Each desktop app keeps its own copied shared packages and dependency tree so it
can be installed, tested, built, and packaged independently. Do not reintroduce
`apps/desktop` as a duplicate source tree.

## Development Prerequisites

- Git.
- Node.js LTS and npm for the standalone desktop apps.
- PHP supported stable version and Composer for the Platform.
- Docker Compose for local infrastructure.
- Flutter stable SDK for the Monitoring App.
- SQLite tooling for local Business Server inspection and development.
- PostgreSQL and Redis for Platform development.

## Local Setup Overview

The Laravel Platform app now lives at the repository root. Typical Platform setup is:

1. Clone the repository and enter `C:\laragon\www\marketlens`.
2. Run `composer install` and `npm install`.
3. Copy `.env.example` to `.env` and run `php artisan key:generate`.
4. Start required PostgreSQL and Redis services.
5. Run `php artisan migrate`.
6. Run `php artisan test` before opening a pull request.

## Environment-File Policy

- Commit example files such as `.env.example`.
- Do not commit real `.env` files.
- Do not commit generated secrets, production credentials, API keys, database
  passwords, certificates, private keys, or customer data.
- Document every required environment variable in the owning application's
  documentation.
- Keep production environment values in the deployment platform or secret
  manager, never in Git.

## Testing Overview

Every business rule must have automated tests at the layer where the rule is
owned. Transactional behavior, permission checks, stock movement calculations,
debt ledger changes, synchronization contracts, and tenant or branch isolation
require especially focused coverage.

Expected tools include Pest and Larastan for the Platform, Vitest and
Playwright for the standalone desktop applications and their copied packages,
and Flutter Test for the Monitoring App.

## Security Principles

- Enforce tenant and branch isolation at every data boundary.
- Validate all external input at application boundaries.
- Use strict typing for shared contracts and synchronization payloads.
- Protect local APIs from unauthorized LAN access.
- Treat operational records as auditable business history.
- Keep immutable transactional records; reverse or correct with new records.
- Store secrets outside Git and rotate credentials if exposure is suspected.

See [SECURITY.md](SECURITY.md) for reporting and security handling guidance.

## Contribution Workflow

1. Read this README and [AGENTS.md](AGENTS.md).
2. Identify the application or package that owns the change.
3. Read the nearest application-level `AGENTS.md` when one exists.
4. Keep changes scoped to the owning boundary.
5. Add tests for business rules and contract changes.
6. Update documentation when architecture, setup, security, or synchronization
   behavior changes.
7. Open a pull request using the project template.

See [CONTRIBUTING.md](CONTRIBUTING.md) for the full workflow.

## Documentation

- [System Overview](docs/architecture/system-overview.md)
- [Architecture Decision Record Template](docs/architecture/adr-template.md)
- [Database Documentation](docs/database/README.md)
- [API Documentation](docs/api/README.md)
- [Security Documentation](docs/security/README.md)
- [Synchronization Documentation](docs/synchronization/README.md)
- [Deployment Documentation](docs/deployment/README.md)
- [Testing Documentation](docs/testing/README.md)
- [Product Documentation](docs/product/README.md)
