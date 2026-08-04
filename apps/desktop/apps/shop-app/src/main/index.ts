import { app, BrowserWindow, ipcMain, shell } from 'electron';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createLocalServer } from '../server/local-server';
import { createShopApplication } from '../services/application/shop-application';
import { loadShopEnvironment } from '../shared/environment';

const __dirname = dirname(fileURLToPath(import.meta.url));
const environment = loadShopEnvironment();
const shopApplication = await createShopApplication(environment);
const localServer = await createLocalServer(shopApplication);

ipcMain.handle('system:ping', () => 'pong');

async function createMainWindow(): Promise<BrowserWindow> {
  const mainWindow = new BrowserWindow({
    width: 1280,
    height: 800,
    minWidth: 1024,
    minHeight: 640,
    title: 'MarketLens Shop App',
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

app.on('before-quit', () => {
  void localServer.stop();
});

await app.whenReady();
await localServer.start();
await createMainWindow();

app.on('activate', () => {
  if (BrowserWindow.getAllWindows().length === 0) {
    void createMainWindow();
  }
});
