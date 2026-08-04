export type ShopPreloadApi = {
  readonly appName: 'MarketLens Shop App';
  readonly security: {
    readonly contextIsolation: true;
    readonly nodeIntegration: false;
    readonly sandbox: true;
    readonly webSecurity: true;
  };
  readonly system: {
    readonly ping: () => Promise<string>;
  };
  readonly authentication: {
    readonly login: (username: string, password: string) => Promise<{ accessToken?: string; authenticated: boolean }>;
  };
  readonly reporting: {
    readonly dashboard: (accessToken: string, date?: string) => Promise<Record<string, unknown>>;
    readonly staffPerformance: (accessToken: string, filters: { from: string; to: string }) => Promise<Array<Record<string, unknown>>>;
    readonly report: (accessToken: string, type: string, filters: { from: string; to: string }) => Promise<Array<Record<string, unknown>>>;
    readonly export: (accessToken: string, type: string, filters: { from: string; to: string }, format: 'csv' | 'pdf') => Promise<{ uuid: string; status: string }>;
  };
  readonly synchronization: {
    readonly status: (accessToken: string) => Promise<Record<string, unknown>>;
    readonly retry: (accessToken: string, queueUuid?: string) => Promise<void>;
  };
  readonly backup: {
    readonly create: (accessToken: string, type?: 'manual' | 'external_drive', destinationPath?: string) => Promise<Record<string, unknown>>;
    readonly maintain: (accessToken: string) => Promise<Record<string, unknown>>;
    readonly restore: (accessToken: string, backupUuid: string, confirmation: 'RESTORE') => Promise<void>;
  };
  readonly deployment: {
    readonly networkStatus: (accessToken: string) => Promise<Record<string, unknown>>;
    readonly testNetwork: (accessToken: string) => Promise<Record<string, unknown>>;
    readonly checkUpdates: (accessToken: string) => Promise<Record<string, unknown>>;
    readonly shutdown: (accessToken: string, confirmation: 'SHUTDOWN') => Promise<void>;
  };
};
