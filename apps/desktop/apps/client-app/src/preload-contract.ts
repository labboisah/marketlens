export type ClientPreloadApi = {
  readonly appName: 'MarketLens Client App';
  readonly security: {
    readonly contextIsolation: true;
    readonly nodeIntegration: false;
  };
};
