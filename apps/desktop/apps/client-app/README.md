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

## Application States

The Client App resolves and visibly presents these central workstation states:

- `not_configured`
- `searching_for_shop_server`
- `awaiting_pairing`
- `paired`
- `disconnected`
- `session_expired`
- `authenticated`
- `shop_suspended`
- `subscription_expired`
- `module_disabled`
- `update_required`
- `ready`

These states are derived from Shop App connectivity, device authorization,
session validity, subscription and module status, application version, update
requirements, and the current role or permission context.

## Starting Roles

The Client App ships as one executable with a role-based shell. It does not
split roles into separate desktop executables.

Supported starting role presets:

- Owner
- Administrator
- Manager
- Cashier
- Storekeeper
- Accountant
- Service Staff
- Supervisor
- Read-Only Auditor

Role presets influence shell emphasis only. Authorization remains permission
driven and enforced by Shop App APIs.

## Main Navigation Groups

The shared workstation shell includes these primary groups:

- Dashboard
- Sales
- Products
- Inventory
- Purchases
- Customers
- Suppliers
- Expenses
- Services
- Staff Earnings
- Cashier Shifts
- Approvals
- Reports
- Settings
- Connection

Module visibility and action visibility are derived from:

- User permissions
- Business settings
- Branch settings
- Subscription plan state
- Shop App module state

The Shop App remains the final authorization boundary for every direct API
request.

## Shop App Connection

The Client App connects to the Shop App local API. The connection status
indicator must remain visible so users know whether they are online, connecting,
or disconnected from the local server.

Transactions must remain pending in the UI until the Shop App returns a
confirmed success response.

The connection workflow supports:

- Automatic LAN discovery where available.
- Manual IP and port entry.
- Server-name input and server identity details.
- Recent verified server list.
- Connection test before transition.
- Pairing-code and QR entry foundation.
- Secure reconnection using encrypted connection settings.
- Optional pinned server identity verification when configured.

Changing the connected Shop App requires explicit confirmation and may require
device re-pairing, especially when the new server belongs to a different
business identity.

## Connection Screens

The current connection workflow includes explicit screens for:

- Find Shop App.
- Manual connection.
- Connection test.
- Server found.
- Server unavailable.
- Connection lost.
- Retry.
- Change server.

The application status banner displays business name, branch name, Shop App
device name, server IP, server port, connection status, and last successful
connection timestamp when available.

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
the application, but session secrets must not be stored in local storage.

## Device Pairing

Unpaired devices must request authorization from the Shop App before login. The
pairing screen collects a device name and pairing code, then calls the Shop App
pairing endpoint.

Pairing is only complete when the Shop App confirms it.

The Client App must not advance from pairing to login when the Shop App only
accepts a request for later approval.

## Login

Users authenticate through the Shop App. The Client App uses the returned
permissions to decide which navigation items and screens are visible.

Authentication supports:

- Username or staff code identity input.
- Password-based login through the Shop App.
- Remember-device mode where policy allows.
- Token refresh with secure session updates.
- Manual logout with explicit confirmation.
- Automatic lock screen after inactivity.
- Automatic logout after prolonged inactivity.
- Password-change-required enforcement.
- Account-suspended and account-disabled handling.
- Permission refresh through authenticated state checks.

Authentication failures are displayed with generic failure messaging.

On startup, the Client App restores any encrypted session through the preload
bridge and re-validates it against the Shop App before showing workspace
screens.

## Secure Session Storage

Sensitive session values must be stored through Electron `safeStorage` exposed
by a typed preload API. Renderer code must not store auth tokens, device secrets,
or session credentials in local storage.

Non-sensitive workstation preferences such as the last Shop App URL, preferred
role shell, and last device name are stored through a typed preload preferences
API rather than direct renderer storage.

Connection settings and verified recent servers are stored in an encrypted
main-process connection-settings store exposed through typed preload APIs.

Session token material is persisted only through Electron `safeStorage` in the
main process. Plaintext passwords are never persisted.

## Authentication Screens

The Client App includes dedicated screens for:

- Login page.
- Session-expired page.
- Account-disabled page.
- Password-change page.
- Lock screen.
- Logout confirmation.

## Receipt Printing

Receipt printing must use controlled preload APIs. Renderer code should pass a
validated print payload to the preload bridge and must not access native printers
directly.

## Barcode Scanners

USB barcode scanners that act as keyboards can be handled by focused inputs.
Native scanner integrations must go through controlled preload APIs.

## Preload API Surface

The typed preload bridge currently exposes controlled APIs for:

- Encrypted session storage.
- Non-sensitive workstation preferences.
- Receipt printing.
- Scanner status.
- Application version and runtime information.
- Local network information.
- Controlled export-path selection.
- Update status checks.

## Shell Layout

The workstation shell includes:

- Sidebar navigation grouped by module.
- Top navigation with business and branch display.
- User menu.
- Shift status indicator.
- Server connection status.
- Synchronization indicator.
- Application update indicator.
- Theme selector.
- Notification center.
- Breadcrumb foundation.
- Global search foundation.

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
