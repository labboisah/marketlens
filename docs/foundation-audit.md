# MarketLens Foundation Audit

Audit date: 2026-07-28

This audit inspected the repository after the foundational scaffolding and
documentation work. It focuses on structure, boundaries, stack consistency,
contracts, environment templates, security, tests, CI, Docker readiness, and
whether documentation matches the current implementation.

## Corrections Applied During Audit

- Updated the root `Makefile` so Monitoring App commands use Flutter now that
  the app exists.
- Updated the root `Makefile` so Platform frontend build uses `npm run build`,
  matching `apps/platform/package.json`.
- Updated `platform-install` to install Platform frontend dependencies with
  `npm install`.
- Aligned Platform API success and error resource scaffolds with the documented
  standard response envelope.
- Added `trace_id`, `message`, `code`, and `errors` to Platform API exception
  JSON responses.
- Tightened the Platform sync batch request scaffold to require
  `protocol_version`, `created_at`, and canonical record fields.

## Passed Checks

### Repository Structure

- Root documentation, contribution files, security policy, Makefile, and
  application folders exist.
- The four-application structure is present:
  - `apps/platform`
  - `apps/desktop/apps/shop-app`
  - `apps/desktop/apps/client-app`
  - `apps/monitoring-app`
- Desktop shared packages exist:
  - `api-contracts`
  - `sync-contracts`
  - `shared-types`
  - `validation`
  - `permissions`
  - `calculations`
  - `ui`

### README and AGENTS Files

- Root `README.md` and root `AGENTS.md` exist and define the four-application
  architecture.
- Application-level `AGENTS.md` files exist for Platform, Desktop workspace,
  Shop App, Client App, and Monitoring App.
- Application-level README files exist for Platform, Desktop workspace, Shop
  App, Client App, and Monitoring App.
- Shared package README files exist for all desktop packages.

### Stack Consistency

- Platform stack matches the approved direction: Laravel, Livewire, Flux,
  Tailwind, PostgreSQL/Redis configuration, Horizon, Sanctum, Reverb, Pest,
  Pint, Larastan, and Vite.
- Shop App stack matches the approved direction: Electron, TypeScript, React,
  Vite, Tailwind, shadcn config, Fastify, SQLite, better-sqlite3, Drizzle, Zod,
  Socket.IO, Pino, Vitest, Playwright, and Electron Forge.
- Client App stack matches the approved direction: Electron, TypeScript, React,
  Vite, Tailwind, shadcn config, TanStack Query, Zustand, React Hook Form, Zod,
  TanStack Table/Virtual, Socket.IO Client, safeStorage, Vitest, Playwright, and
  Electron Forge.
- Monitoring App stack matches the approved direction: Flutter, Dart, Material
  3, Riverpod, GoRouter, Dio, Drift, secure storage, Firebase Messaging,
  Crashlytics, fl_chart, local_auth, connectivity_plus, and Flutter Test.

### Environment Templates

- Environment templates exist for:
  - `apps/platform/.env.example`
  - `apps/desktop/apps/shop-app/.env.example`
  - `apps/desktop/apps/client-app/.env.example`
  - `apps/monitoring-app/.env.example`
- Secret scan found no committed `.env`, SQLite database, private key, PEM, or
  Firebase credential files.
- `.gitignore` excludes environment files, keys, local databases, logs,
  generated artifacts, Node modules, PHP vendor files, and Flutter build output.

### Database Documentation

- Database documentation exists under `docs/database`.
- Data ownership is documented for Shop App authoritative data, Platform
  authoritative data, Platform replicated data, and Monitoring App cached data.
- Shop and Platform schema table groups are documented.
- Indexing, migration order, retention, archiving, and Mermaid ER diagrams are
  documented.

### API Contracts

- API standards exist under `docs/api`.
- Standard success and error response formats are documented.
- Versioning, pagination, authentication, validation, date/time, money,
  quantity, filtering, sorting, idempotency, correlation IDs, rate limits, error
  codes, and deprecation policy are documented.
- `@marketlens/api-contracts` exports Zod schemas and inferred TypeScript types.
- `@marketlens/sync-contracts` exports Zod schemas and inferred TypeScript types.
- Platform OpenAPI foundation exists at `apps/platform/docs/openapi.yaml`.

### Synchronization Documentation

- Synchronization protocol docs exist under `docs/synchronization`.
- Canonical envelope and response format are documented.
- Batch UUIDs, record UUIDs, record versions, idempotency keys, signed device
  credentials, partial success, retry, replay safety, compression, conflict
  handling, security, and failure recovery are documented.
- Mermaid sequence diagrams exist for success, partial failure, authentication
  failure, conflict handling, and interruption/retry.

### Security Rules

- Security documentation covers tenant isolation, branch isolation, password
  hashing, local and Platform authentication, device authentication, sessions,
  RBAC, audit logs, approvals, Electron security, preload APIs, SQL injection
  prevention, validation, rate limits, backup encryption, secret management,
  dependency scanning, log redaction, update distribution, installer signing,
  secure storage, biometric re-authentication, lost-device revocation, and data
  export/deletion procedures.
- Pull request template includes gates for testing, API versioning,
  synchronization compatibility, migration rollback review, and passing CI.

### Styling System

- Shared design system exists at `docs/product/design-system.md`.
- Platform UI README exists.
- Desktop UI package contains shared tokens and reusable components.
- Monitoring App Material 3 theme exists and matches the MarketLens semantic
  color direction.
- Shop App and Client App import shared desktop UI tokens.

### Testing Setup

- Testing strategy exists under `docs/testing`.
- Test expectations are defined for Platform, Shop App, Client App, Monitoring
  App, and shared packages.
- Scaffold/boundary tests exist in Platform, Desktop packages, Shop App, Client
  App, and Monitoring App.

### CI Workflows

- CI workflow files exist for:
  - Platform lint and tests.
  - Desktop lint, type-check, and tests.
  - Monitoring lint and tests.
  - Dependency audit.
  - Documentation-link validation.

### Application Boundaries

- Shop App documentation and scaffold state it is the local source of truth.
- Shop App owns SQLite and initializes required SQLite PRAGMAs.
- Client App documentation and code avoid SQLite, Drizzle, and better-sqlite3.
- Monitoring App documentation and screens state that it connects only to the
  Platform and never directly to Shop Apps.
- Platform AGENTS and docs state that only limited operational-user profiles are
  synchronized for reporting.
- Electron security settings are enabled in Shop App and Client App:
  `contextIsolation: true`, `nodeIntegration: false`, `sandbox: true`, and
  `webSecurity: true`.

## Failed Checks

### Docker Setup Missing

- No `docker-compose.yml`, Dockerfile, or `.dockerignore` files were found.
- Development docs mention Docker Desktop/Engine and Docker Compose, but the
  repository does not yet provide runnable Docker infrastructure.

### Dependency Lockfiles Missing

- No desktop `pnpm-lock.yaml` was found.
- No Monitoring App `pubspec.lock` was found.
- Platform has `composer.lock`, but Platform frontend dependencies do not have a
  committed npm lockfile.
- CI has been made tolerant for desktop installs, but release-grade dependency
  reproducibility is not yet established across all stacks.

### Platform API Implementation Was Behind API Docs

- Platform API resources originally returned an older response shape. This was
  corrected during the audit.
- Controllers are still placeholders and do not yet fully implement response
  semantics for each domain endpoint.

### Sync Implementation Is Much Thinner Than Sync Documentation

- Sync protocol documentation is complete, but Shop App and Platform sync code
  remain placeholder-level.
- HMAC signatures, replay nonce storage, compression handling, partial success
  processing, conflict persistence, and exponential backoff are not yet
  implemented.

### Docker and Deployment Documentation Still Thin

- `docs/deployment/README.md` is still a placeholder.
- No deployment runbooks, backup runbooks, release signing steps, or environment
  promotion procedures exist yet.

## Risks

- Many desktop dependencies use `"latest"`, which is convenient for scaffolding
  but risky for reproducible builds.
- CI workflows have not been executed in GitHub Actions yet.
- Local machine cannot currently run `pnpm` or `flutter`, so desktop and
  monitoring verification has been static rather than executable.
- Platform uses internal numeric `business_id` and `branch_id` scoping while
  synchronization/docs use UUIDs. This is acceptable if API boundaries map
  consistently, but it needs explicit implementation discipline.
- Shop App foundational SQLite tables are minimal and do not yet include the
  full documented schema or tenant/branch columns on all future business
  records.
- Monitoring App includes Firebase dependencies and bootstrap shell, but no real
  Firebase project configuration yet.
- Documentation is ahead of implementation in database schema, synchronization,
  reporting, security controls, and deployment.
- Root Makefile now calls `flutter`, `pnpm`, and npm commands that may fail on
  machines without those tools installed.

## Missing Files

- `docker-compose.yml`
- Platform Dockerfile
- Shop App Docker/development helper files, if containerized local development
  is desired
- `.dockerignore`
- `apps/desktop/pnpm-lock.yaml`
- Platform frontend npm lockfile
- `apps/monitoring-app/pubspec.lock`
- Deployment runbooks
- Production backup and restore runbooks
- Release signing documentation for desktop installers
- Secure update distribution documentation

## Conflicting Decisions

- Development documentation expects Docker Compose support, but Docker files are
  not present.
- Root Makefile originally treated the Monitoring App as unscaffolded even
  though it now exists. This was corrected.
- Root Makefile originally used `pnpm build` for Platform frontend assets while
  Platform uses npm scripts. This was corrected.
- API documentation defined a standard envelope that Platform resource classes
  did not yet return. This was corrected.
- API/sync docs are production-grade while implementation remains scaffold-level.
  This is acceptable at foundation stage, but the gap must remain visible.

## Recommended Corrections

1. Add Docker Compose infrastructure for Platform PostgreSQL, Redis, mail/test
   tools, and local development services.
2. Generate and commit lockfiles for desktop, Platform frontend, and Monitoring
   App dependencies.
3. Replace `"latest"` desktop dependency versions with intentional pinned or
   ranged versions before pilot release.
4. Expand Platform API resources and exception handling tests to lock the
   documented success/error formats.
5. Add sync compatibility tests using `@marketlens/sync-contracts`.
6. Implement sync queue columns and migrations according to the database and
   sync documentation.
7. Add Docker/deployment documentation and operational runbooks.
8. Add branch/tenant isolation tests before implementing real reporting.
9. Add tests that prove Client App cannot import SQLite/better-sqlite3/Drizzle.
10. Add release signing and secure update documentation before installer work.

## Ordered Next Steps

1. Create Docker Compose and development infrastructure.
2. Install dependencies in a clean environment and generate lockfiles.
3. Run CI locally or in GitHub Actions and fix setup failures.
4. Add API response format tests on Platform.
5. Add contract compatibility tests for API and sync packages.
6. Implement foundational database migrations for Shop App and Platform in the
   documented order.
7. Implement real sync queue processing behind the documented protocol.
8. Add tenant and branch isolation tests for Platform APIs and reports.
9. Add Shop App SQLite integration tests and local API tests.
10. Expand deployment, backup, restore, release signing, and secure update docs.

## Audit Conclusion

The MarketLens foundation is structurally strong and the architecture boundaries
are consistently documented. The largest gap is that documentation is now ahead
of executable infrastructure in Docker, synchronization processing, full schema
migrations, and dependency reproducibility. That is acceptable for a foundation
phase as long as the next milestone turns the documented contracts into tested,
versioned, executable behavior without expanding into business modules too
early.
