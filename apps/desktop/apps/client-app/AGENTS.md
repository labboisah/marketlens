# Client App Agent Guide

The Client App runs on cashier, storekeeper, manager, and accountant computers.
It connects to the Shop App over the local network.

- Never open or read SQLite directly.
- All transaction writes must go through authenticated Shop App APIs.
- Renderer processes must not have unrestricted Node.js access.
- Use typed preload APIs for IPC.
- Do not store secrets in renderer local storage.
