# MarketLens Client App

The Client App connects to a Shop App over LAN or Wi-Fi for cashier,
storekeeper, manager, and accountant workflows.

The Client App must never open SQLite directly. All operational writes go
through authenticated Shop App APIs.

Electron implementation must preserve:

- `contextIsolation: true`
- `nodeIntegration: false`
- typed preload IPC APIs
- authenticated local-network requests
- no secrets in renderer local storage

Business features are intentionally not implemented in this scaffold.
