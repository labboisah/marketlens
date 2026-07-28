import { contextBridge, ipcRenderer } from 'electron';
import type { ClientPreloadApi, ClientSession, PrintReceiptPayload } from './types';

const api: ClientPreloadApi = {
  appName: 'MarketLens Client App',
  security: {
    contextIsolation: true,
    nodeIntegration: false,
    sandbox: true,
    webSecurity: true,
  },
  session: {
    load: () => ipcRenderer.invoke('client:session:load') as Promise<ClientSession | null>,
    save: (session) => ipcRenderer.invoke('client:session:save', session) as Promise<{ saved: true }>,
    clear: () => ipcRenderer.invoke('client:session:clear') as Promise<{ cleared: true }>,
  },
  printer: {
    printReceipt: (payload: PrintReceiptPayload) =>
      ipcRenderer.invoke('client:printer:printReceipt', payload) as Promise<{
        accepted: boolean;
        reason?: string;
        receiptUuid: string;
      }>,
  },
  scanner: {
    status: () => ipcRenderer.invoke('client:scanner:status') as Promise<{ connected: boolean; mode: string }>,
  },
};

contextBridge.exposeInMainWorld('marketlensClient', api);
