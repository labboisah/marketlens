import { contextBridge, ipcRenderer } from 'electron';
import type { ShopPreloadApi } from './types';

const api: ShopPreloadApi = {
  appName: 'MarketLens Shop App',
  security: {
    contextIsolation: true,
    nodeIntegration: false,
    sandbox: true,
    webSecurity: true,
  },
  system: {
    ping: () => ipcRenderer.invoke('system:ping') as Promise<string>,
  },
  authentication: {
    login: (username, password) => ipcRenderer.invoke('authentication:login', { username, password }) as Promise<{ accessToken?: string; authenticated: boolean }>,
  },
  reporting: {
    dashboard: (accessToken, date) => ipcRenderer.invoke('reporting:dashboard', { accessToken, date }) as Promise<Record<string, unknown>>,
    staffPerformance: (accessToken, filters) => ipcRenderer.invoke('reporting:staff-performance', { accessToken, filters }) as Promise<Array<Record<string, unknown>>>,
    report: (accessToken, type, filters) => ipcRenderer.invoke('reporting:report', { accessToken, type, filters }) as Promise<Array<Record<string, unknown>>>,
    export: (accessToken, type, filters, format) => ipcRenderer.invoke('reporting:export', { accessToken, type, filters, format }) as Promise<{ uuid: string; status: string }>,
  },
};

contextBridge.exposeInMainWorld('marketlensShop', api);
