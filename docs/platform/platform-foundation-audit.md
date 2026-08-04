# MarketLens Platform Foundation Audit

Audit date: 2026-07-28

This audit inspected `apps/platform` before implementing product features. It
checks installed dependencies, versions, configuration status, missing
components, security risks, architectural conflicts, required corrections, and
test results.

## Corrections Applied

- Published config files for Horizon, Sanctum, and Reverb:
  - `config/horizon.php`
  - `config/sanctum.php`
  - `config/reverb.php`
- Added `apps/platform/compose.yaml` for local PostgreSQL, Redis, and Mailpit.
- Aligned `apps/platform/.env.example` with the local Compose database and
  Mailpit ports.
- Updated Platform README setup commands from `pnpm` to `npm`, matching
  `apps/platform/package.json`.
- Added scheduler foundation for `horizon:snapshot`.
- Made Horizon middleware and Reverb allowed origins environment-driven.
- Added Platform currency convention keys to `config/marketlens.php`.

## Installed Dependencies

Composer direct dependencies currently installed:

| Dependency | Version | Status |
| --- | ---: | --- |
| PHP | 8.3.16 | Installed locally |
| Laravel Framework | 13.23.0 | Installed |
| Laravel Horizon | 5.48.1 | Installed |
| Laravel Reverb | 1.11.0 | Installed |
| Laravel Sanctum | 4.3.3 | Installed |
| Laravel Pint | 1.29.3 | Installed |
| Larastan | 3.10.0 | Installed |
| Pest | 4.7.5 | Installed |
| Livewire | 4.3.3 | Installed |
| Flux UI | 2.15.0 | Installed |
| PHPUnit | 12.5.30 | Installed |

Node dependencies declared in `package.json`:

| Dependency | Declared Version | Runtime Status |
| --- | ---: | --- |
| `@tailwindcss/vite` | `^4.0.0` | Not installed locally |
| `tailwindcss` | `^4.0.0` | Not installed locally |
| `vite` | `^7.0.7` | Not installed locally |
| `laravel-vite-plugin` | `^2.0.0` | Not installed locally |
| `alpinejs` | `^3.14.0` | Not installed locally |
| `axios` | `^1.11.0` | Not installed locally |
| `concurrently` | `^9.0.1` | Not installed locally |

`npm.cmd ls --depth=0` reports these frontend dependencies as unmet because
`npm install` has not been run in this workspace.

## Configuration Status

| Component | Status |
| --- | --- |
| Laravel | Installed and responds to `artisan --version`; version 13.23.0. |
| Livewire | Installed; `artisan about` reports Livewire v4.3.3. |
| Alpine.js | Declared in `package.json`; not installed locally yet. |
| Tailwind CSS | Declared in `package.json`; not installed locally yet. |
| Flux UI | Installed through Composer; Flux routes are registered. |
| Lucide icons | Not installed as a Platform dependency; only documented as intended icon strategy. |
| PostgreSQL | Configured as default DB in `.env.example`; local connection failed because no PostgreSQL server was listening on `127.0.0.1:5432`. |
| Redis | Configured for cache and queues; local Redis check failed because PHP `redis` extension is missing. |
| Horizon | Installed, routes registered, config published; dashboard exists at `/horizon`; `horizon:status` fails locally without PHP Redis extension. |
| Sanctum | Installed, CSRF route registered, config published. |
| Reverb | Installed, broadcasting default is `reverb`, config published; app credentials are placeholders in `.env.example`. |
| Pest | Installed; current tests pass. |
| Pint | Installed; `pint --test` passes after correction. |
| Larastan | Installed; `phpstan analyse --memory-limit=1G` passes. |
| Vite | Declared in `package.json`; not installed locally yet. |
| Docker Compose | Platform `compose.yaml` now exists; local Docker CLI is not installed, so execution was not verified. |

## Service Verification

### PostgreSQL Connection

Status: failed locally.

`php artisan migrate:status` failed with connection refused on
`127.0.0.1:5432`. The configuration is present, and `compose.yaml` now provides
a local PostgreSQL service, but it was not started because Docker is unavailable
on this machine.

### Redis Connection

Status: failed locally.

`php artisan horizon:status` and `php artisan schedule:list` failed with
`Class "Redis" not found`. The local PHP runtime has no `redis` extension
loaded. `compose.yaml` now provides a Redis service, but the PHP extension is
still required for the configured `phpredis` client.

### Queue Worker Configuration

Status: configured, not executable locally.

`QUEUE_CONNECTION=redis` is set in `.env.example`, and `config/queue.php` has a
Redis queue connection. Horizon config is published and defaults to Redis.
Execution is blocked locally by the missing PHP Redis extension.

### Horizon Dashboard

Status: route registered, runtime blocked locally.

`php artisan route:list` shows Horizon routes under `/horizon`. Access control
still needs product-specific authorization before production.

### Scheduler

Status: foundation configured.

`routes/console.php` schedules `horizon:snapshot` every five minutes. Listing
the scheduler is blocked locally by the missing Redis extension because Horizon
initializes Redis.

### Reverb Configuration

Status: configured.

Broadcasting defaults to Reverb. `config/reverb.php` is published. Allowed
origins are now environment-driven through `REVERB_ALLOWED_ORIGINS`. Real app
ID, key, and secret remain unset in `.env.example`, as expected.

### Sanctum Configuration

Status: configured.

`config/sanctum.php` is published, `/sanctum/csrf-cookie` is registered, and API
routes use `auth:sanctum` where expected.

### Mail Configuration

Status: configured for local capture/logging.

`MAIL_MAILER=log` remains the default. `MAIL_PORT=1025` now matches the Mailpit
service in `compose.yaml`.

### File Storage Configuration

Status: configured.

Local, public, and S3 disks exist. `artisan about` reports
`public/storage` is not linked, so `php artisan storage:link` is still needed
when public local files are required.

### Application Timezone

Status: configured.

Application timezone is UTC.

### Currency Conventions

Status: configured.

`config/marketlens.php` now defines:

- default currency: `NGN`
- money scale: `2`
- quantity scale: `6`

Money and quantity casts exist.

### UUID Conventions

Status: foundation present.

`HasUuid` exists and is used by foundation models. Sync request validation uses
UUID fields. Database docs still require synchronized records to use UUIDs.

### API Response Conventions

Status: corrected foundation.

`SuccessResource`, `ErrorResource`, and API exception responses now align with
the documented envelope shape:

- success responses include `success`, `message`, `data`, and `meta`
- error responses include `success`, `message`, `code`, `errors`, and
  `trace_id`

### Exception Handling

Status: foundation present.

API exceptions render JSON with the standard error fields. More specific error
code mapping and validation-error formatting should be added during API
implementation.

### Audit-Log Foundation

Status: present.

`audit_logs` migration and `AuditLog` model exist. No audit writer service or
policy integration is implemented yet.

### Tenant Scope Foundation

Status: present, needs tests.

`BusinessContext`, `BusinessTenantScope`, and `BelongsToBusinessTenant` exist.
Models such as Branch, Device, SyncBatch, OperationalUserProfile, and
Subscription use business scoping where expected.

### Branch Scope Foundation

Status: present, needs tests.

`BranchContext`, `BranchScope`, and `BelongsToBranchTenant` exist. Branch-owned
models use branch scope in the scaffold.

## Missing Components

- Lucide icon package or chosen Blade icon integration for Platform.
- Installed frontend dependencies and lockfile for Platform `package.json`.
- Running local PostgreSQL service.
- Running local Redis service.
- PHP Redis extension for the current Windows PHP runtime.
- Production Horizon authorization policy/gate.
- Real Reverb app credentials.
- Public storage symlink, if public file serving is needed.
- Platform-specific Docker documentation beyond the new minimal Compose file.
- Audit writer service.
- Tenant-isolation and branch-isolation test coverage.
- API response envelope tests.
- Sync ingestion compatibility tests.

## Security Risks

- Horizon is route-registered and config-published, but production access
  control still needs explicit authorization.
- `REVERB_ALLOWED_ORIGINS` defaults to local origins in `.env.example`, but
  production must set strict trusted origins.
- `APP_DEBUG=true` in local example is correct for development only.
- Reverb credentials are blank placeholders and must be provided through
  secrets outside Git.
- Redis currently uses no password in local development; production must use
  network isolation and credentials where supported.
- Platform frontend dependencies are not installed/locked, so dependency audit
  cannot fully verify JavaScript supply-chain state yet.

## Architectural Conflicts

- Documentation says Docker Compose is part of the development foundation; this
  was missing before the audit and has now been added for Platform.
- Platform README previously used `pnpm` while `apps/platform/package.json` uses
  npm. This was corrected.
- API docs required the standard envelope, while Platform resource scaffolds
  previously used an older response shape. This was corrected.
- Queue/Horizon docs assume Redis works, but the current local PHP runtime lacks
  the Redis extension.

## Required Corrections

1. Install or enable the PHP Redis extension for the Platform PHP runtime, or
   intentionally add and configure a Predis fallback.
2. Start PostgreSQL and Redis through Docker Compose, WSL, Laragon services, or
   another documented local service path.
3. Run `npm install` in `apps/platform` and commit a frontend lockfile.
4. Add the approved Lucide/Blade icon package or document the final Platform
   icon strategy.
5. Add Horizon production authorization.
6. Add API response envelope tests.
7. Add tenant and branch isolation tests.
8. Add audit writer service and tests.
9. Add sync ingestion compatibility tests before implementing production sync.

## Test Results

| Check | Result |
| --- | --- |
| `php artisan --version` | Passed: Laravel Framework 13.23.0 |
| `php artisan about` | Passed; showed PostgreSQL, Redis, Reverb, database sessions, UTC timezone |
| `composer show --direct` | Passed; dependency versions captured |
| `npm.cmd ls --depth=0` | Failed; frontend dependencies are declared but not installed |
| `php artisan route:list` | Passed; API, Livewire, Flux, Horizon, Sanctum routes registered |
| `php artisan migrate:status` | Failed; PostgreSQL refused connection on `127.0.0.1:5432` |
| `php artisan horizon:status` | Failed; PHP Redis extension missing |
| `php artisan schedule:list` | Failed; PHP Redis extension missing through Horizon initialization |
| `php artisan test` | Passed: 3 tests, 7 assertions |
| `vendor/bin/pint --test` | Passed after correction |
| `vendor/bin/phpstan analyse --memory-limit=1G` | Passed |
| PHP syntax checks for touched config/route/bootstrap files | Passed |
| `docker compose version` | Failed; Docker CLI is not installed on this machine |

## Implementation Readiness

The Platform foundation is ready for the next implementation step only after
local infrastructure is made executable. Laravel, core PHP dependencies,
Livewire, Flux, Sanctum, Horizon, Reverb, Pest, Pint, Larastan, and API route
scaffolds are present. The main blockers are local PostgreSQL/Redis availability,
PHP Redis extension availability, missing frontend install/lockfile, missing
Lucide icon integration, and missing isolation/API tests.

Do not proceed to public pages, registration, or monitoring features until the
required corrections above are addressed.
