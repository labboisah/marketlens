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
};
