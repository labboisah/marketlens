import { contextBridge, ipcRenderer } from 'electron';
import type {
  ClientConnectionSettings,
  ClientPreloadApi,
  ClientPreferences,
  ClientSession,
  ExportFileRequest,
  ExportFileResult,
  ReceiptPrintPayload,
} from './types';

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
  preferences: {
    load: () => ipcRenderer.invoke('client:preferences:load') as Promise<ClientPreferences>,
    save: (preferences) => ipcRenderer.invoke('client:preferences:save', preferences) as Promise<{ saved: true }>,
  },
  connectionSettings: {
    load: () => ipcRenderer.invoke('client:connection-settings:load') as Promise<ClientConnectionSettings>,
    save: (settings) => ipcRenderer.invoke('client:connection-settings:save', settings) as Promise<{ saved: true }>,
    reset: () => ipcRenderer.invoke('client:connection-settings:reset') as Promise<{ reset: true }>,
  },
  printer: {
    list: () => ipcRenderer.invoke('client:printer:list') as ReturnType<ClientPreloadApi['printer']['list']>,
    getDefault: () => ipcRenderer.invoke('client:printer:getDefault') as ReturnType<ClientPreloadApi['printer']['getDefault']>,
    setDefault: (name: string) => ipcRenderer.invoke('client:printer:setDefault', name) as ReturnType<ClientPreloadApi['printer']['setDefault']>,
    printReceipt: (payload: ReceiptPrintPayload) =>
      ipcRenderer.invoke('client:printer:printReceipt', payload) as Promise<{
        accepted: boolean;
        reason?: string;
        receiptUuid: string;
      }>,
  },
  scanner: {
    status: () => ipcRenderer.invoke('client:scanner:status') as Promise<{ connected: boolean; mode: string }>,
  },
  appInfo: {
    get: () => ipcRenderer.invoke('client:app-info:get') as ReturnType<ClientPreloadApi['appInfo']['get']>,
  },
  network: {
    get: () => ipcRenderer.invoke('client:network:get') as ReturnType<ClientPreloadApi['network']['get']>,
  },
  files: {
    chooseExportPath: (input: ExportFileRequest) =>
      ipcRenderer.invoke('client:files:choose-export-path', input) as Promise<ExportFileResult>,
  },
  updates: {
    check: () => ipcRenderer.invoke('client:updates:check') as ReturnType<ClientPreloadApi['updates']['check']>,
  },
};

contextBridge.exposeInMainWorld('marketlensClient', api);
