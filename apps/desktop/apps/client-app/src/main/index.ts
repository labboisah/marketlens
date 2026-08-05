import { app, BrowserWindow, dialog, ipcMain, shell } from 'electron';
import { hostname, networkInterfaces } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import clientPackageJson from '../../package.json' with { type: 'json' };
import { ClientPreferencesStore, type ClientPreferencesRecord } from './client-preferences-store';
import { SecureConnectionSettingsStore } from './secure-connection-settings-store';
import { SecureSessionStore, type StoredSession } from './secure-session-store';
import type { ClientConnectionSettings, ExportFileRequest, ReceiptPrintPayload } from '../preload/types';

const __dirname = dirname(fileURLToPath(import.meta.url));
let sessionStore: SecureSessionStore;
let preferencesStore: ClientPreferencesStore;
let connectionSettingsStore: SecureConnectionSettingsStore;
let defaultPrinterName: string | null = null;

function escapeHtml(value: string): string {
  return value
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

function buildReceiptHtml(payload: ReceiptPrintPayload): string {
  const isThermal = payload.format === 'thermal';
  const pageSize = isThermal ? '80mm auto' : 'A4';
  const bodyMaxWidth = isThermal ? '76mm' : '210mm';
  const itemRows = payload.receipt.items
    .map((item) => `
      <tr>
        <td>${escapeHtml(item.name)}<div class="muted">${escapeHtml(item.sku ?? '')} ${escapeHtml(item.unit ?? '')}</div></td>
        <td class="right">${escapeHtml(item.quantity)}</td>
        <td class="right">${escapeHtml(item.unitPrice)}</td>
        <td class="right">${escapeHtml(item.lineTotal)}</td>
      </tr>
    `)
    .join('');

  const paymentRows = payload.receipt.payments
    .map((payment) => `
      <tr>
        <td>${escapeHtml(payment.method)}</td>
        <td class="right">${escapeHtml(payment.amount)}</td>
        <td>${escapeHtml(payment.reference ?? '')}</td>
      </tr>
    `)
    .join('');

  return `<!doctype html>
<html>
  <head>
    <meta charset="utf-8" />
    <title>Receipt ${escapeHtml(payload.receipt.receiptNumber)}</title>
    <style>
      @page { size: ${pageSize}; margin: ${isThermal ? '3mm' : '10mm'}; }
      body { font-family: 'Segoe UI', Arial, sans-serif; padding: ${isThermal ? '8px' : '24px'}; color: #111; width: 100%; max-width: ${bodyMaxWidth}; margin: 0 auto; }
      .brand { font-size: ${isThermal ? '18px' : '24px'}; font-weight: 700; margin-bottom: 4px; }
      .muted { color: #555; font-size: 12px; }
      table { width: 100%; border-collapse: collapse; margin-top: 10px; }
      th, td { border-bottom: 1px solid #ddd; padding: 6px 4px; font-size: ${isThermal ? '11px' : '13px'}; vertical-align: top; }
      .right { text-align: right; }
      .totals { margin-top: 12px; }
      .totals div { display: flex; justify-content: space-between; font-size: ${isThermal ? '11px' : '13px'}; padding: 2px 0; }
      .strong { font-weight: 700; }
      .section-title { margin-top: 12px; font-weight: 600; }
    </style>
  </head>
  <body>
    <div class="brand">${escapeHtml(payload.receipt.businessName || 'MarketLens')}</div>
    <div class="muted">${escapeHtml(payload.receipt.branchName || 'Branch')}</div>
    <div class="muted">Sale: ${escapeHtml(payload.receipt.saleNumber)} | Receipt: ${escapeHtml(payload.receipt.receiptNumber)}</div>
    <div class="muted">Date: ${escapeHtml(new Date(payload.receipt.createdAt).toLocaleString())}</div>
    <div class="muted">Cashier: ${escapeHtml(payload.receipt.cashier)}</div>
    <div class="muted">Customer: ${escapeHtml(payload.receipt.customer)}</div>

    <div class="section-title">Items</div>
    <table>
      <thead>
        <tr><th>Item</th><th class="right">Qty</th><th class="right">Price</th><th class="right">Total</th></tr>
      </thead>
      <tbody>${itemRows}</tbody>
    </table>

    <div class="section-title">Payments</div>
    <table>
      <thead>
        <tr><th>Method</th><th class="right">Amount</th><th>Reference</th></tr>
      </thead>
      <tbody>${paymentRows}</tbody>
    </table>

    <div class="totals">
      <div><span>Subtotal</span><span>${escapeHtml(payload.receipt.subtotal)}</span></div>
      <div><span>Discount</span><span>${escapeHtml(payload.receipt.discountAmount)}</span></div>
      <div class="strong"><span>Total</span><span>${escapeHtml(payload.receipt.totalAmount)}</span></div>
      <div><span>Paid</span><span>${escapeHtml(payload.receipt.totalPaid)}</span></div>
      <div><span>Change</span><span>${escapeHtml(payload.receipt.changeDue)}</span></div>
      <div><span>Balance</span><span>${escapeHtml(payload.receipt.balance)}</span></div>
    </div>

    <div class="muted" style="margin-top: 12px;">Verification: ${escapeHtml(payload.receipt.verificationReference ?? '')}</div>
    <div class="muted">${escapeHtml(payload.receipt.thankYouMessage ?? 'Thank you for choosing MarketLens.')}</div>
  </body>
</html>`;
}

function getLanAddresses(): string[] {
  return Object.values(networkInterfaces())
    .flat()
    .filter((item): item is NonNullable<typeof item> => Boolean(item && item.family === 'IPv4' && !item.internal))
    .map((item) => item.address);
}

function registerIpcHandlers(): void {
  ipcMain.handle('client:session:load', () => sessionStore.load());
  ipcMain.handle('client:session:save', (_event, session: StoredSession) => {
    sessionStore.save(session);
    return { saved: true };
  });
  ipcMain.handle('client:session:clear', () => {
    sessionStore.clear();
    return { cleared: true };
  });
  ipcMain.handle('client:preferences:load', () => preferencesStore.load());
  ipcMain.handle('client:preferences:save', (_event, preferences: ClientPreferencesRecord) => {
    preferencesStore.save(preferences);
    return { saved: true };
  });
  ipcMain.handle('client:connection-settings:load', () => connectionSettingsStore.load());
  ipcMain.handle('client:connection-settings:save', (_event, settings: ClientConnectionSettings) => {
    connectionSettingsStore.save(settings);
    return { saved: true };
  });
  ipcMain.handle('client:connection-settings:reset', () => {
    connectionSettingsStore.reset();
    return { reset: true };
  });
  ipcMain.handle('client:printer:list', async () => {
    const existing = BrowserWindow.getAllWindows()[0];
    if (existing) {
      const printers = await existing.webContents.getPrintersAsync();
      return printers.map((printer) => ({
        name: printer.name,
        displayName: printer.displayName,
        isDefault: printer.isDefault,
      }));
    }

    const temporary = new BrowserWindow({
      show: false,
      webPreferences: {
        contextIsolation: true,
        nodeIntegration: false,
        sandbox: true,
        webSecurity: true,
      },
    });

    try {
      await temporary.loadURL('about:blank');
      const printers = await temporary.webContents.getPrintersAsync();
      return printers.map((printer) => ({
        name: printer.name,
        displayName: printer.displayName,
        isDefault: printer.isDefault,
      }));
    } finally {
      if (!temporary.isDestroyed()) {
        temporary.close();
      }
    }
  });
  ipcMain.handle('client:printer:getDefault', () => defaultPrinterName);
  ipcMain.handle('client:printer:setDefault', (_event, name: string) => {
    defaultPrinterName = name;
    return { saved: true };
  });
  ipcMain.handle('client:printer:printReceipt', async (_event, payload: ReceiptPrintPayload) => {
    const printWindow = new BrowserWindow({
      show: false,
      webPreferences: {
        contextIsolation: true,
        nodeIntegration: false,
        sandbox: true,
        webSecurity: true,
      },
    });

    try {
      await printWindow.loadURL(`data:text/html;charset=utf-8,${encodeURIComponent(buildReceiptHtml(payload))}`);

      const silent = !payload.preview;
      const result = await new Promise<boolean>((resolve) => {
        printWindow.webContents.print({
          silent,
          deviceName: payload.printerName || defaultPrinterName || undefined,
          copies: Math.max(1, Math.min(10, payload.copies ?? 1)),
          printBackground: true,
        }, (success) => resolve(success));
      });

      return {
        accepted: result,
        reason: result ? undefined : 'Printing was cancelled or failed.',
        receiptUuid: payload.receiptUuid,
      };
    } finally {
      if (!printWindow.isDestroyed()) {
        printWindow.close();
      }
    }
  });
  ipcMain.handle('client:scanner:status', () => ({
    connected: false,
    mode: 'keyboard-wedge-supported',
  }));
  ipcMain.handle('client:app-info:get', () => ({
    name: 'MarketLens Client App',
    version: clientPackageJson.version,
    electron: process.versions.electron,
    chrome: process.versions.chrome,
    node: process.versions.node,
  }));
  ipcMain.handle('client:network:get', () => ({
    hostname: hostname(),
    lanAddresses: getLanAddresses(),
    online: true,
  }));
  ipcMain.handle('client:files:choose-export-path', async (_event, input: ExportFileRequest) => {
    const result = await dialog.showSaveDialog({
      title: 'Choose export destination',
      defaultPath: input.suggestedName,
      filters: input.filters,
    });

    return {
      cancelled: result.canceled,
      filePath: result.filePath,
    };
  });
  ipcMain.handle('client:updates:check', () => ({
    channel: 'unknown',
    currentVersion: clientPackageJson.version,
    updateAvailable: false,
    checkedAt: new Date().toISOString(),
  }));
}

async function createMainWindow(): Promise<BrowserWindow> {
  const mainWindow = new BrowserWindow({
    width: 1280,
    height: 800,
    minWidth: 1024,
    minHeight: 640,
    title: 'MarketLens Client App',
    webPreferences: {
      preload: join(__dirname, '../preload/index.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: true,
      webSecurity: true,
    },
  });

  mainWindow.webContents.setWindowOpenHandler(({ url }) => {
    void shell.openExternal(url);
    return { action: 'deny' };
  });

  const devServerUrl = process.env.MAIN_WINDOW_VITE_DEV_SERVER_URL;
  const rendererName = process.env.MAIN_WINDOW_VITE_NAME ?? 'main_window';

  if (devServerUrl) {
    await mainWindow.loadURL(devServerUrl);
  } else {
    await mainWindow.loadFile(join(__dirname, `../renderer/${rendererName}/index.html`));
  }

  return mainWindow;
}

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit();
  }
});

await app.whenReady();
sessionStore = new SecureSessionStore(app.getPath('userData'));
preferencesStore = new ClientPreferencesStore(app.getPath('userData'));
connectionSettingsStore = new SecureConnectionSettingsStore(app.getPath('userData'));
registerIpcHandlers();
await createMainWindow();

app.on('activate', () => {
  if (BrowserWindow.getAllWindows().length === 0) {
    void createMainWindow();
  }
});
