import { app, BrowserWindow, ipcMain, shell } from 'electron';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createLocalServer } from '../server/local-server';
import { createShopApplication } from '../services/application/shop-application';
import { loadShopEnvironment } from '../shared/environment';
import { verifyAccessToken } from '../services/security/token-service';
import { reportTypes, type LocalReportType, type ReportFilters } from '../services/domain/local-reporting-service';

const __dirname = dirname(fileURLToPath(import.meta.url));
const environment = loadShopEnvironment();
const shopApplication = await createShopApplication(environment);
const localServer = await createLocalServer(shopApplication);

ipcMain.handle('system:ping', () => 'pong');
ipcMain.handle('authentication:login', async (_event, input: { username: string; password: string }) => {
  const result = await shopApplication.services.authentication.login(input);
  return { authenticated: result.authenticated, accessToken: result.accessToken };
});
ipcMain.handle('reporting:dashboard', (_event, input: { accessToken: string; date?: string }) => {
  authorizeReporting(input.accessToken, 'dashboard.view');
  return shopApplication.services.reporting.dashboard(input.date);
});
ipcMain.handle('reporting:staff-performance', (_event, input: { accessToken: string; filters: ReportFilters }) => {
  authorizeReporting(input.accessToken, 'staff.performance.view');
  return shopApplication.services.reporting.staffPerformance(input.filters);
});
ipcMain.handle('reporting:report', (_event, input: { accessToken: string; type: string; filters: ReportFilters }) => {
  authorizeReporting(input.accessToken, 'reports.view');
  if (!reportTypes.includes(input.type as LocalReportType)) throw new Error('Unsupported report type');
  return shopApplication.services.reporting.report(input.type as LocalReportType, input.filters);
});
ipcMain.handle('reporting:export', (_event, input: { accessToken: string; type: string; filters: ReportFilters; format: 'csv' | 'pdf' }) => {
  const userUuid = authorizeReporting(input.accessToken, 'reports.export');
  if (!reportTypes.includes(input.type as LocalReportType)) throw new Error('Unsupported report type');
  const job = shopApplication.services.reporting.createReportJob(input.type as LocalReportType, input.filters, input.format, userUuid);
  setImmediate(() => { try { shopApplication.services.reporting.processReportJob(job.uuid); } catch (error) { shopApplication.logger.error({ error, reportJobUuid: job.uuid }, 'Local report job failed'); } });
  return { uuid: job.uuid, status: 'queued' };
});

function authorizeReporting(accessToken: string, permission: string): string {
  const payload = verifyAccessToken(accessToken, environment.SHOP_JWT_SECRET);
  const userUuid = typeof payload?.sub === 'string' ? payload.sub : undefined;
  if (!userUuid) throw new Error('Authentication is required');
  const allowed = shopApplication.database.sqlite.prepare(`
    SELECT 1 FROM user_roles ur
    JOIN roles r ON r.uuid = ur.role_uuid
    LEFT JOIN role_permissions rp ON rp.role_uuid = r.uuid
    LEFT JOIN permissions p ON p.uuid = rp.permission_uuid
    WHERE ur.user_uuid = ? AND (p.key = ? OR r.name IN ('Owner', 'Administrator')) LIMIT 1
  `).get(userUuid, permission);
  if (!allowed) throw new Error('Reporting permission denied');
  return userUuid;
}

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
