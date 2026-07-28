# MarketLens Desktop Agent Guide

This workspace contains the MarketLens Shop App, Client App, and shared desktop
packages.

## Application Boundaries

- Shop App and Client App share contracts but have different responsibilities.
- The Shop App owns the local SQLite database.
- The Client App never opens SQLite.
- All transaction writes go through Shop App APIs.
- Shared packages must remain application-independent.
- Business calculations should live in shared calculation packages where
  appropriate.
- API contracts must be versioned.
- Breaking contract changes require documentation and coordinated updates.

## Electron Security

- Electron security defaults must be preserved.
- Renderer processes must not have unrestricted Node.js access.
- Use IPC through typed preload APIs.
- Do not store secrets in renderer local storage.
- All local-network requests must authenticate users and devices.

## Package Rules

- `packages/api-contracts` owns versioned local API contracts.
- `packages/shared-types` owns portable TypeScript types.
- `packages/validation` owns shared Zod schemas.
- `packages/permissions` owns shared permission names and checks.
- `packages/calculations` owns deterministic business calculations.
- `packages/sync-contracts` owns synchronization payload contracts.
- `packages/ui` owns shared desktop design tokens and reusable UI components.

Do not implement sales, stock, payment, or purchase business features in this
scaffold layer.
