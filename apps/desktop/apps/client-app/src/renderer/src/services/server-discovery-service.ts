import { checkShopStatus } from './shop-api-client';

export interface DiscoveredServer {
  id: string;
  name: string;
  baseUrl: string;
  source: 'manual' | 'discovery';
  businessName?: string;
  branchName?: string;
  deviceName?: string;
  serverIp?: string;
  serverPort?: number;
  status: 'available' | 'unavailable';
  lastSuccessfulConnectionAt?: string;
}

export interface ConnectionTestResult {
  ok: boolean;
  baseUrl: string;
  reason?: string;
  status?: Awaited<ReturnType<typeof checkShopStatus>>['status'];
}

const defaultServerUrl = 'http://127.0.0.1:4090';

function normalizeBaseUrl(baseUrl: string): string {
  return baseUrl.trim().replace(/\/+$/, '');
}

function candidateName(baseUrl: string): string {
  try {
    const url = new URL(baseUrl);
    return `Shop at ${url.hostname}:${url.port || '80'}`;
  } catch {
    return 'Configured Shop App';
  }
}

function identityFromStatus(status: NonNullable<ConnectionTestResult['status']>) {
  return {
    businessUuid: status.businessUuid ?? undefined,
    businessName: status.businessName ?? undefined,
    branchUuid: status.branchUuid ?? undefined,
    branchName: status.branchName ?? undefined,
    shopDeviceUuid: status.shopDeviceUuid ?? undefined,
    shopDeviceName: status.shopDeviceName,
    shopServerName: status.shopServerName,
    serverIp: status.serverIp,
    serverPort: status.serverPort,
  };
}

function dedupe<T extends { baseUrl: string }>(servers: T[]): T[] {
  const seen = new Set<string>();
  return servers.filter((server) => {
    const key = normalizeBaseUrl(server.baseUrl);
    if (seen.has(key)) {
      return false;
    }

    seen.add(key);
    return true;
  });
}

function lanCandidatesFromNetwork(lanAddresses: string[]): string[] {
  const candidates = new Set<string>();
  for (const ip of lanAddresses) {
    const [a, b, c] = ip.split('.');
    if (!a || !b || !c) {
      continue;
    }

    [1, 2, 10, 20, 30, 40, 50, Number(ip.split('.')[3])].forEach((hostPart) => {
      if (Number.isFinite(hostPart) && hostPart > 0 && hostPart < 255) {
        candidates.add(`http://${a}.${b}.${c}.${hostPart}:4090`);
      }
    });
  }

  return [...candidates];
}

async function withTimeout<T>(task: Promise<T>, timeoutMs: number): Promise<T> {
  return new Promise((resolve, reject) => {
    const timeout = window.setTimeout(() => reject(new Error('Connection timed out.')), timeoutMs);
    task.then((value) => {
      window.clearTimeout(timeout);
      resolve(value);
    }).catch((error) => {
      window.clearTimeout(timeout);
      reject(error);
    });
  });
}

export async function loadConnectionSettings() {
  const stored = await window.marketlensClient.connectionSettings.load();
  return {
    ...stored,
    currentServerUrl: stored.currentServerUrl || defaultServerUrl,
  };
}

export async function saveConnectionSettings(settings: Awaited<ReturnType<typeof loadConnectionSettings>>) {
  await window.marketlensClient.connectionSettings.save(settings);
}

export async function loadManualServerUrl(): Promise<string> {
  const settings = await loadConnectionSettings();
  return settings.currentServerUrl;
}

export async function saveManualServerUrl(baseUrl: string): Promise<void> {
  const settings = await loadConnectionSettings();
  await saveConnectionSettings({
    ...settings,
    currentServerUrl: normalizeBaseUrl(baseUrl),
  });
}

export async function testShopServerConnection(baseUrl: string): Promise<ConnectionTestResult> {
  const normalized = normalizeBaseUrl(baseUrl);

  try {
    const result = await withTimeout(checkShopStatus(normalized), 1500);
    return {
      ok: result.reachable,
      baseUrl: normalized,
      status: result.status,
    };
  } catch (error) {
    return {
      ok: false,
      baseUrl: normalized,
      reason: error instanceof Error ? error.message : 'Connection failed.',
    };
  }
}

export function requiresPinnedIdentityViolation(
  settings: Awaited<ReturnType<typeof loadConnectionSettings>>,
  status: NonNullable<ConnectionTestResult['status']>,
): boolean {
  if (!settings.requirePinnedServerMatch || !settings.pinnedServerIdentity) {
    return false;
  }

  const pinned = settings.pinnedServerIdentity;
  return (
    (pinned.businessUuid && pinned.businessUuid !== (status.businessUuid ?? undefined)) ||
    (pinned.branchUuid && pinned.branchUuid !== (status.branchUuid ?? undefined)) ||
    (pinned.shopDeviceUuid && pinned.shopDeviceUuid !== (status.shopDeviceUuid ?? undefined))
  );
}

export function isBusinessChange(
  currentIdentity: Awaited<ReturnType<typeof loadConnectionSettings>>['currentServerIdentity'] | undefined,
  status: NonNullable<ConnectionTestResult['status']>,
): boolean {
  return Boolean(
    currentIdentity?.businessUuid &&
      status.businessUuid &&
      currentIdentity.businessUuid !== status.businessUuid,
  );
}

export async function recordSuccessfulConnection(baseUrl: string, status: NonNullable<ConnectionTestResult['status']>): Promise<void> {
  const settings = await loadConnectionSettings();
  const now = new Date().toISOString();
  const normalized = normalizeBaseUrl(baseUrl);
  const identity = identityFromStatus(status);
  const nextRecent = dedupe([
    {
      id: `${identity.shopDeviceUuid ?? status.serverIp ?? 'server'}:${status.serverPort}`,
      baseUrl: normalized,
      identity,
      lastSuccessfulConnectionAt: now,
    },
    ...settings.recentServers,
  ]).slice(0, 8);

  await saveConnectionSettings({
    ...settings,
    currentServerUrl: normalized,
    currentServerIdentity: identity,
    recentServers: nextRecent,
    lastSuccessfulConnectionAt: now,
  });
}

export async function clearConnectionSettings(): Promise<void> {
  await window.marketlensClient.connectionSettings.reset();
}

export async function discoverShopServers(): Promise<DiscoveredServer[]> {
  const settings = await loadConnectionSettings();
  const network = await window.marketlensClient.network.get();
  const candidates = dedupe([
    { baseUrl: settings.currentServerUrl || defaultServerUrl },
    ...settings.recentServers.map((server) => ({ baseUrl: server.baseUrl })),
    ...lanCandidatesFromNetwork(network.lanAddresses).map((baseUrl) => ({ baseUrl })),
  ]).slice(0, 20);

  const tests = await Promise.all(candidates.map(async (candidate) => {
    const result = await testShopServerConnection(candidate.baseUrl);
    const recent = settings.recentServers.find((item) => normalizeBaseUrl(item.baseUrl) === normalizeBaseUrl(candidate.baseUrl));
    if (result.ok && result.status) {
      return {
        id: `${result.status.shopDeviceUuid ?? result.status.serverIp}:${result.status.serverPort}`,
        name: result.status.shopServerName || candidateName(candidate.baseUrl),
        baseUrl: result.baseUrl,
        source: recent ? 'manual' as const : 'discovery' as const,
        businessName: result.status.businessName ?? undefined,
        branchName: result.status.branchName ?? undefined,
        deviceName: result.status.shopDeviceName,
        serverIp: result.status.serverIp,
        serverPort: result.status.serverPort,
        status: 'available' as const,
        lastSuccessfulConnectionAt: recent?.lastSuccessfulConnectionAt,
      };
    }

    return {
      id: `unavailable:${result.baseUrl}`,
      name: candidateName(candidate.baseUrl),
      baseUrl: result.baseUrl,
      source: recent ? 'manual' as const : 'discovery' as const,
      status: 'unavailable' as const,
      lastSuccessfulConnectionAt: recent?.lastSuccessfulConnectionAt,
    };
  }));

  return tests;
}
