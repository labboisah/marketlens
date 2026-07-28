# MarketLens Client App

The MarketLens Client App is a role-based Electron desktop client used by
cashiers, storekeepers, managers, and accountants. It connects to an authorized
Shop App on the local network and sends all operational reads and writes through
authenticated Shop App APIs.

The Client App never opens SQLite and never owns the shop business database.

## Purpose

- Provide fast role-based workstations for day-to-day shop users.
- Keep cashier workflows responsive for keyboard-heavy point-of-sale work.
- Show clear connection, pairing, session, and permission state.
- Defer all authoritative transaction outcomes to the Shop App.

## Supported Roles

- Cashier: POS, cart, customer selection, payment dialog, receipt, shift opening,
  and shift closing shells.
- Storekeeper: products, stock, purchases, stock counts, and damaged goods
  shells.
- Manager: approvals, returns, expenses, cashier settlement, and reports shells.
- Accountant: customer debts, supplier balances, expenses, reconciliation, and
  profit reports shells.

## Shop App Connection

The Client App connects to the Shop App local API. The connection status
indicator must remain visible so users know whether they are online, connecting,
or disconnected from the local server.

Transactions must remain pending in the UI until the Shop App returns a
confirmed success response.

## Automatic Discovery

The scaffold includes a server-discovery service shell. Production discovery can
use approved local-network discovery methods, but discovered servers must still
be paired and authorized by the Shop App before use.

## Manual IP Configuration

Users may manually enter a Shop App URL, for example:

```text
http://192.168.1.20:4090
```

Manual server configuration is non-authoritative UI data. It may be cached by
the renderer, but session secrets must not be stored in local storage.

## Device Pairing

Unpaired devices must request authorization from the Shop App before login. The
pairing screen collects a device name and pairing code, then calls the Shop App
pairing endpoint.

Pairing is only complete when the Shop App confirms it.

## Login

Users authenticate through the Shop App. The Client App uses the returned
permissions to decide which navigation items and screens are visible.

## Secure Session Storage

Sensitive session values must be stored through Electron `safeStorage` exposed
by a typed preload API. Renderer code must not store auth tokens, device secrets,
or session credentials in local storage.

## Receipt Printing

Receipt printing must use controlled preload APIs. Renderer code should pass a
validated print payload to the preload bridge and must not access native printers
directly.

## Barcode Scanners

USB barcode scanners that act as keyboards can be handled by focused inputs.
Native scanner integrations must go through controlled preload APIs.

## Testing

Use the desktop workspace commands from `apps/desktop`:

```bash
pnpm --filter @marketlens/client-app test
pnpm --filter @marketlens/client-app typecheck
pnpm --filter @marketlens/client-app lint
pnpm --filter @marketlens/client-app test:e2e
```

## Packaging

Electron Forge owns packaging:

```bash
pnpm --filter @marketlens/client-app package
pnpm --filter @marketlens/client-app make
```

## Troubleshooting

- Cannot discover Shop App: verify both machines are on the same network and the
  Shop App local API is listening on the configured host and port.
- Manual IP fails: confirm the URL includes the scheme, IP address, and port.
- Pairing rejected: check that the Shop App has approved the client device.
- Login rejected: verify credentials and that the user is registered in the Shop
  App.
- Missing features: confirm the Shop App returned the required permissions.
- Transactions stuck pending: inspect the connection indicator and Shop App
  local API logs.
- Printing fails: confirm the printer is available to the OS and that printing
  is routed through the preload API.
- Windows firewall blocks the server: allow the Shop App through Windows
  Defender Firewall on the private network profile.
