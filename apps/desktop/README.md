# MarketLens Desktop Monorepo

This workspace contains the Electron-based MarketLens Shop App, Client App, and
shared desktop packages.

## Structure

```text
apps/desktop/
|-- apps/
|   |-- shop-app/
|   `-- client-app/
|-- packages/
|   |-- api-contracts/
|   |-- shared-types/
|   |-- validation/
|   |-- permissions/
|   |-- calculations/
|   |-- sync-contracts/
|   `-- ui/
|-- package.json
|-- pnpm-workspace.yaml
|-- turbo.json
|-- tsconfig.base.json
|-- eslint.config.js
`-- README.md
```

## Installation

Use Node.js from the repository `.nvmrc`, then install with pnpm:

```bash
cd apps/desktop
corepack enable
corepack prepare pnpm@10.14.0 --activate
pnpm install
```

If Corepack fails because of stale package-manager signing keys, update Node.js
or Corepack before installing dependencies.

## Development Commands

```bash
pnpm dev
pnpm build
pnpm test
pnpm test:e2e
pnpm lint
pnpm format
pnpm typecheck
pnpm clean
```

Target one app or package:

```bash
pnpm --filter @marketlens/shop-app dev
pnpm --filter @marketlens/client-app dev
pnpm --filter @marketlens/ui build
```

## Shared-Package Rules

- Shared packages must not import from application folders.
- `api-contracts` and `sync-contracts` must version public contracts.
- Breaking contract changes require documentation and coordinated Shop App and
  Client App updates.
- Business calculations belong in `packages/calculations` when they are shared
  or need deterministic reuse.
- Validation schemas belong in `packages/validation`.
- UI components and design tokens belong in `packages/ui`.

## Testing

Vitest is configured for unit and package tests. Playwright is configured for
future Electron and renderer end-to-end tests.

```bash
pnpm test
pnpm test:e2e
```

Tests should cover contracts, validation behavior, permission decisions,
calculation rules, and Electron security boundaries as they are implemented.

## Building Installers

Electron Forge is the installer boundary for Shop App and Client App.

```bash
pnpm --filter @marketlens/shop-app package
pnpm --filter @marketlens/shop-app make
pnpm --filter @marketlens/client-app package
pnpm --filter @marketlens/client-app make
```

Installer generation is scaffolded but business screens are intentionally not
implemented yet.

## Native Module Rebuilding

The Shop App will use native modules such as `better-sqlite3`. Rebuild native
modules whenever Node.js, Electron, or platform architecture changes:

```bash
pnpm --filter @marketlens/shop-app rebuild:native
```

Windows developers need Python and Visual Studio Build Tools with the C++
workload. Ubuntu developers need `build-essential`, Python, `make`, and `g++`.

## Troubleshooting

- If `pnpm` is unavailable, activate it with Corepack or update Node/Corepack.
- If `better-sqlite3` fails to build, verify native build tools and Python.
- If Electron fails to start, verify `contextIsolation`, preload paths, and Vite
  dev-server ports.
- If Client App cannot connect to Shop App, verify local API host, port,
  authentication, device authorization, and Windows firewall rules.
- Never work around Client App connection issues by reading SQLite directly.
