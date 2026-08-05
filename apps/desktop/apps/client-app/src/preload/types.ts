export interface ClientSession {
  shopBaseUrl: string;
  userUuid: string;
  username: string;
  deviceUuid: string;
  token: string;
  refreshToken: string;
  permissions: string[];
  roles: string[];
  rememberDevice: boolean;
  accountStatus: 'active' | 'inactive' | 'suspended' | 'disabled';
  passwordChangeRequired: boolean;
  expiresAt: string;
}

export interface ClientPreferences {
  shopBaseUrl: string;
  lastDeviceName: string;
  preferredRole: 'owner' | 'administrator' | 'manager' | 'cashier' | 'storekeeper' | 'accountant' | 'service_staff' | 'supervisor' | 'auditor';
}

export interface ShopServerIdentity {
  businessUuid?: string;
  businessName?: string;
  branchUuid?: string;
  branchName?: string;
  shopDeviceUuid?: string;
  shopDeviceName?: string;
  shopServerName?: string;
  serverIp?: string;
  serverPort?: number;
}

export interface RecentShopServer {
  id: string;
  baseUrl: string;
  identity: ShopServerIdentity;
  lastSuccessfulConnectionAt: string;
}

export interface ClientConnectionSettings {
  currentServerUrl: string;
  currentServerIdentity?: ShopServerIdentity;
  recentServers: RecentShopServer[];
  lastSuccessfulConnectionAt?: string;
  pinnedServerIdentity?: ShopServerIdentity;
  requirePinnedServerMatch: boolean;
}

export type ReceiptPrintFormat = 'thermal' | 'a4';

export interface ReceiptPrintPayload {
  receiptUuid: string;
  saleUuid: string;
  format: ReceiptPrintFormat;
  preview?: boolean;
  printerName?: string;
  copies?: number;
  receipt: {
    receiptNumber: string;
    saleNumber: string;
    createdAt: string;
    cashier: string;
    customer: string;
    businessName: string;
    branchName: string;
    items: Array<{ name: string; sku?: string; unit?: string; quantity: string; unitPrice: string; lineTotal: string }>;
    payments: Array<{ method: string; amount: string; reference?: string | null }>;
    subtotal: string;
    discountAmount: string;
    totalAmount: string;
    totalPaid: string;
    changeDue: string;
    balance: string;
    verificationReference?: string | null;
    thankYouMessage?: string;
  };
}

export interface PrinterInfo {
  name: string;
  displayName: string;
  isDefault: boolean;
}

export interface ClientAppInfo {
  name: 'MarketLens Client App';
  version: string;
  electron: string;
  chrome: string;
  node: string;
}

export interface ClientNetworkInfo {
  hostname: string;
  lanAddresses: string[];
  online: boolean;
}

export interface ExportFileRequest {
  suggestedName: string;
  defaultExtension: string;
  filters: Array<{
    name: string;
    extensions: string[];
  }>;
}

export interface ExportFileResult {
  cancelled: boolean;
  filePath?: string;
}

export interface ClientUpdateStatus {
  channel: 'stable' | 'beta' | 'unknown';
  currentVersion: string;
  updateAvailable: boolean;
  downloadUrl?: string;
  checkedAt: string;
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
  readonly preferences: {
    load(): Promise<ClientPreferences>;
    save(preferences: ClientPreferences): Promise<{ saved: true }>;
  };
  readonly connectionSettings: {
    load(): Promise<ClientConnectionSettings>;
    save(settings: ClientConnectionSettings): Promise<{ saved: true }>;
    reset(): Promise<{ reset: true }>;
  };
  readonly printer: {
    list(): Promise<PrinterInfo[]>;
    getDefault(): Promise<string | null>;
    setDefault(name: string): Promise<{ saved: true }>;
    printReceipt(payload: ReceiptPrintPayload): Promise<{
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
  readonly appInfo: {
    get(): Promise<ClientAppInfo>;
  };
  readonly network: {
    get(): Promise<ClientNetworkInfo>;
  };
  readonly files: {
    chooseExportPath(input: ExportFileRequest): Promise<ExportFileResult>;
  };
  readonly updates: {
    check(): Promise<ClientUpdateStatus>;
  };
}
