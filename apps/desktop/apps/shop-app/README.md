# MarketLens Shop App

The Shop App is the local source of truth for a branch. It owns local SQLite
persistence, the local API server, synchronization to the Platform, and
authenticated local-network access for Client Apps.

Electron implementation must preserve:

- `contextIsolation: true`
- `nodeIntegration: false`
- typed preload IPC APIs
- authenticated local API requests
- no secrets in renderer local storage

Business features are intentionally not implemented in this scaffold.
