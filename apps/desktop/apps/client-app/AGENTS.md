# MarketLens Client App Agent Guide

This application is a role-based desktop client for cashier, storekeeper,
manager, and accountant workflows.

## Responsibilities

- The Client App does not own a business database.
- The Client App must never directly open SQLite.
- Connect only to an authorized MarketLens Shop App.
- Authenticate users through the connected Shop App.
- Use Shop App permissions to determine visible features and allowed actions.
- Clearly show connection status at all times.
- Never pretend a transaction succeeded until the Shop App confirms it.
- Cache only non-authoritative UI data, such as selected server address or
  layout preferences.
- Do not store completed sales independently.
- Keep keyboard-based cashier workflows fast and predictable.

## Security

- Printing and native device access must use controlled preload APIs.
- Sensitive session data must use Electron `safeStorage` through the preload
  bridge.
- Renderer processes must not have unrestricted Node.js access.
- Never store secrets, auth tokens, or device credentials in renderer local
  storage.
- All local-network requests must authenticate the user session and paired
  device.

## Application State

- Use TanStack Query for server state from the Shop App.
- Use Zustand only for UI state, such as active role, selected screen, connection
  status, and notifications.
- Do not add independent persistence for sales, payments, stock, purchases, or
  ledger data in the Client App.
