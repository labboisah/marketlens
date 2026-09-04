# Windows Installation

## Installer

Build the Windows Squirrel installer from the standalone server app:

```powershell
cd C:\laragon\www\business-server
npm run make
```

The output includes the setup executable, installed application executable,
uninstaller, and Start Menu shortcuts. Use a signed installer for production.

Set Windows code-signing credentials only in the secure build environment. Do
not place certificates, private keys, or signing passwords in the repository.

## First Launch

1. Start MarketLens Business Server from the Start Menu.
2. The app initializes SQLite, applies safe migrations, verifies integrity, and
   starts Fastify and Socket.IO before accepting Client App connections.
3. Sign in as an authorized administrator and complete activation.
4. Confirm the local port, LAN address, and pairing code in Deployment settings.

## Windows Startup

Set `SHOP_WINDOWS_STARTUP_ENABLED=true` before packaging or deployment to
register the application as a Windows login item. The tray menu shows server
status and opens the administration console. Shutdown from the UI requires an
authorized administrator and explicit confirmation.

## Uninstall

Use Windows Installed Apps or the generated uninstaller. Export or back up the
SQLite database before uninstalling; uninstalling the app must not be treated
as a data-retention policy.
