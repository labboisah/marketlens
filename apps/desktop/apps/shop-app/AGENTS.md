# MarketLens Shop App Agent Guide

The Shop App is the local source of truth for a shop or branch. It must keep the
shop operating even when internet access is unavailable.

## Responsibilities

- Registers and authenticates all operational users.
- Manages roles and permissions.
- Owns SQLite.
- Exposes authenticated local APIs to Client Apps.
- Performs validation and database transactions.
- Creates audit logs.
- Manages the synchronization queue.
- Continues operating without internet.
- Must not require the Cloud Platform for daily shop transactions.
- Should reject unregistered Client App devices.

## Business Invariants

- Use stock movements rather than direct quantity changes.
- Completed sales must not be deleted.
- Financial balances must be derived from ledger transactions.
- Cloud synchronization must be idempotent.
- Local numeric IDs must never be used as cloud synchronization identities.

## Electron Security

- Keep `contextIsolation: true`.
- Keep `nodeIntegration: false`.
- Keep `sandbox: true`.
- Keep `webSecurity: true`.
- Expose renderer capabilities only through typed preload APIs.
- Do not store secrets in renderer local storage.
