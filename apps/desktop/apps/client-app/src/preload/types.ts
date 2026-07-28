export interface ClientSession {
  shopBaseUrl: string;
  userUuid: string;
  deviceUuid: string;
  token: string;
  permissions: string[];
  expiresAt: string;
}

export interface PrintReceiptPayload {
  receiptUuid: string;
  saleUuid: string;
}

export interface ClientPreloadApi {
  readonly appName: 'MarketLens Client App';
  readonly security: {
    readonly contextIsolation: true;
    readonly nodeIntegration: false;
    readonly sandbox: true;
    readonly webSecurity: true;
  };
  readonly session: {
    load(): Promise<ClientSession | null>;
    save(session: ClientSession): Promise<{ saved: true }>;
    clear(): Promise<{ cleared: true }>;
  };
  readonly printer: {
    printReceipt(payload: PrintReceiptPayload): Promise<{
      accepted: boolean;
      reason?: string;
      receiptUuid: string;
    }>;
  };
  readonly scanner: {
    status(): Promise<{
      connected: boolean;
      mode: string;
    }>;
  };
}
