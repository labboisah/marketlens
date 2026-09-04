# Client App Printing

This guide describes receipt printing behavior in MarketLens Client App.

## Printer Integration Model

- Renderer requests print actions through preload IPC.
- Main process validates print payloads with Zod schemas.
- Printing uses Electron print pipeline and configured workstation printer.

## Configure Default Printer

1. Open Client App.
2. Go to printer settings screen.
3. Load available printers.
4. Select and save default printer.

## Receipt Printing Flow

1. POS completes a sale (online authoritative completion).
2. Renderer sends typed print payload to main process.
3. Main process submits print job.
4. Client App reports accepted or failure reason.

## Troubleshooting

If no printers appear:
- Confirm Windows printer is installed for current user.
- Confirm printer is online and reachable.
- Restart Client App and reload printer list.

If print fails:
- Check returned reason from print operation.
- Re-confirm default printer selection.
- Validate receipt data completeness from POS response.

## Security And File Rules

- Export and file-save flows use extension allow-lists.
- Arbitrary path writes are not accepted from renderer.
- Do not bypass IPC with custom Node integrations in renderer.
