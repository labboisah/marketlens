export interface DiscoveredServer {
  id: string;
  name: string;
  baseUrl: string;
  source: 'manual' | 'discovery';
}

const manualServerKey = 'marketlens.client.manualServerUrl';

export function loadManualServerUrl(): string {
  return localStorage.getItem(manualServerKey) ?? 'http://127.0.0.1:4090';
}

export function saveManualServerUrl(baseUrl: string): void {
  localStorage.setItem(manualServerKey, baseUrl);
}

export async function discoverShopServers(): Promise<DiscoveredServer[]> {
  const manualUrl = loadManualServerUrl();

  return [
    {
      id: 'manual-shop-app',
      name: 'Configured Shop App',
      baseUrl: manualUrl,
      source: 'manual',
    },
  ];
}
