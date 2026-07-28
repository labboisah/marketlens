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
};

contextBridge.exposeInMainWorld('marketlensShop', api);
