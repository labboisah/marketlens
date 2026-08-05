import { app, BrowserWindow, dialog, ipcMain, shell } from 'electron';
import { hostname, networkInterfaces } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import clientPackageJson from '../../package.json' with { type: 'json' };
import { ClientPreferencesStore, type ClientPreferencesRecord } from './client-preferences-store';
import { SecureConnectionSettingsStore } from './secure-connection-settings-store';
import { SecureSessionStore, type StoredSession } from './secure-session-store';
import type { ClientConnectionSettings, ExportFileRequest, PrintReceiptPayload } from '../preload/types';

const __dirname = dirname(fileURLToPath(import.meta.url));
let sessionStore: SecureSessionStore;
let preferencesStore: ClientPreferencesStore;
let connectionSettingsStore: SecureConnectionSettingsStore;

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
  ipcMain.handle('client:printer:printReceipt', (_event, payload: PrintReceiptPayload) => ({
    accepted: false,
    reason: 'Receipt printing bridge is scaffolded but no printer adapter is configured.',
    receiptUuid: payload.receiptUuid,
  }));
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
