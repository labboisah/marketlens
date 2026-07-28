import { app, BrowserWindow, ipcMain, shell } from 'electron';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { SecureSessionStore, type StoredSession } from './secure-session-store';
import type { PrintReceiptPayload } from '../preload/types';

const __dirname = dirname(fileURLToPath(import.meta.url));
let sessionStore: SecureSessionStore;

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
  ipcMain.handle('client:printer:printReceipt', (_event, payload: PrintReceiptPayload) => ({
    accepted: false,
    reason: 'Receipt printing bridge is scaffolded but no printer adapter is configured.',
    receiptUuid: payload.receiptUuid,
  }));
  ipcMain.handle('client:scanner:status', () => ({
    connected: false,
    mode: 'keyboard-wedge-supported',
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
registerIpcHandlers();
await createMainWindow();

app.on('activate', () => {
  if (BrowserWindow.getAllWindows().length === 0) {
    void createMainWindow();
  }
});
