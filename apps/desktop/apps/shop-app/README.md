# MarketLens Shop App

The MarketLens Shop App runs on the main computer in each shop or branch. It is
the local source of truth for shop operations, local users, device pairing,
SQLite persistence, local APIs, synchronization queues, backup, and system
health.

It must not require the Cloud Platform for daily shop transactions.

## Architecture

```text
Electron main process
  | starts
Fastify local API + Socket.IO
  | owns transactions
SQLite + Drizzle repositories
  | supports
React renderer through typed preload API
```

Layers:

- Electron main process.
- Electron preload layer.
- React renderer.
- Fastify local server.
- Application services.
- Domain services.
- Drizzle repositories.
- SQLite database.
- Socket.IO server.
- Synchronization worker.
- Backup service.
- Logging service.

## Setup

From `apps/desktop`:

```bash
corepack prepare pnpm@10.14.0 --activate
pnpm install
pnpm --filter @marketlens/shop-app dev
```

If Corepack fails because of signing-key metadata, update Node.js or Corepack.

## Environment Variables

Copy `.env.example` to `.env` for local development. Do not commit real `.env`
files.

Important values:

- `SHOP_LOCAL_API_HOST`: bind host for the local Fastify server.
- `SHOP_LOCAL_API_PORT`: local API port.
- `SHOP_LOCAL_API_ALLOWED_ORIGINS`: Client App origins allowed in development.
- `SHOP_SOCKET_IO_PATH`: Socket.IO path.
- `SHOP_SQLITE_PATH`: local SQLite database path.
- `LOCAL_BACKUP_PATH`: backup output directory.
- `PLATFORM_SYNC_ENABLED`: enables cloud synchronization worker when implemented.

## Fastify Server

The local server exposes authenticated APIs for Client Apps. Initial scaffolded
routes:

- `GET /health`
- `GET /api/v1/system/status`
- `POST /api/v1/auth/login`
- `POST /api/v1/devices/pair`
- `GET /api/v1/session/me`

Full sales, stock, purchase, debt, and ledger behavior is intentionally not
implemented yet.

## SQLite Location

SQLite path is controlled by `SHOP_SQLITE_PATH`. The app initializes SQLite
with:

```sql
PRAGMA journal_mode = WAL;
PRAGMA synchronous = NORMAL;
PRAGMA foreign_keys = ON;
PRAGMA busy_timeout = 10000;
```

Client Apps must never open this database.

## Database Migrations

Drizzle migration wiring is scaffolded. Future migrations must preserve:

- UUIDs for synchronized records.
- Immutable completed transactions.
- Stock movement records instead of direct stock edits.
- Ledger transactions instead of direct balance edits.

## LAN Configuration

Use `SHOP_LOCAL_API_HOST=0.0.0.0` only on trusted shop networks. Client Apps
must authenticate users and devices before accessing local APIs.

## Windows Firewall

Allow the packaged Shop App or development server through Windows Defender
Firewall on private networks only. Keep public-network access blocked unless a
deployment policy explicitly allows it.

## Device Pairing

Unregistered Client App devices must be rejected. Pairing is scaffolded through
`POST /api/v1/devices/pair`; real pairing tokens and approval workflows are not
implemented yet.

## Synchronization

Synchronization must be idempotent and queued. Local numeric IDs must never be
sent as cloud identities. Use UUIDs in synchronization contracts.

## Backup and Recovery

Backup service scaffolding is present. Future backup logic should copy SQLite
using a safe snapshot strategy and include WAL files or checkpoint first.

## Packaging

Electron Forge owns packaging:

```bash
pnpm --filter @marketlens/shop-app package
pnpm --filter @marketlens/shop-app make
```

## Automatic Startup

Automatic startup should be configured by installer or operating-system startup
registration once deployment policy is defined.

## Testing

```bash
pnpm --filter @marketlens/shop-app test
pnpm --filter @marketlens/shop-app test:e2e
pnpm --filter @marketlens/shop-app typecheck
```

Tests should cover API contracts, validation, permissions, local transaction
rules, synchronization idempotency, backup safety, and Electron security as the
app grows.
