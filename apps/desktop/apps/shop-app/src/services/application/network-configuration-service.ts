import { networkInterfaces } from 'node:os';
import type { ShopEnvironment } from '../../shared/environment';

export class NetworkConfigurationService {
  constructor(private readonly environment: ShopEnvironment) {}

  status() {
    const lanAddresses = Object.values(networkInterfaces()).flat().filter((item): item is NonNullable<typeof item> => Boolean(item && item.family === 'IPv4' && !item.internal)).map((item) => item.address);
    const host = this.environment.SHOP_LOCAL_API_HOST === '0.0.0.0' ? lanAddresses[0] ?? 'unavailable' : this.environment.SHOP_LOCAL_API_HOST;
    return { port: this.environment.SHOP_LOCAL_API_PORT, configuredHost: this.environment.SHOP_LOCAL_API_HOST, lanAddresses, serverUrl: `http://${host}:${this.environment.SHOP_LOCAL_API_PORT}`, pairingCode: this.environment.SHOP_DEVICE_PAIRING_CODE, discovery: { serviceName: `MarketLens Shop ${this.environment.SHOP_DEVICE_NAME}`, status: 'manual_pairing_code_foundation' }, firewallGuidance: 'Allow MarketLens Shop App on Private networks only; keep Public network access blocked.' };
  }

  async testConnection() { const status = this.status(); try { const response = await fetch(`${status.serverUrl}/health`); return { reachable: response.ok, serverUrl: status.serverUrl }; } catch { return { reachable: false, serverUrl: status.serverUrl }; } }
}