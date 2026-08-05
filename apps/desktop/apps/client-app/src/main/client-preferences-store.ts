import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';

export interface ClientPreferencesRecord {
  shopBaseUrl: string;
  lastDeviceName: string;
  preferredRole: 'cashier' | 'storekeeper' | 'manager' | 'accountant';
}

const defaultPreferences: ClientPreferencesRecord = {
  shopBaseUrl: 'http://127.0.0.1:4090',
  lastDeviceName: 'Client workstation',
  preferredRole: 'cashier',
};

export class ClientPreferencesStore {
  private readonly path: string;

  constructor(userDataPath: string) {
    this.path = join(userDataPath, 'client-preferences.json');
  }

  load(): ClientPreferencesRecord {
    if (!existsSync(this.path)) {
      return defaultPreferences;
    }

    const parsed = JSON.parse(readFileSync(this.path, 'utf8')) as Partial<ClientPreferencesRecord>;

    return {
      shopBaseUrl: parsed.shopBaseUrl ?? defaultPreferences.shopBaseUrl,
      lastDeviceName: parsed.lastDeviceName ?? defaultPreferences.lastDeviceName,
      preferredRole: parsed.preferredRole ?? defaultPreferences.preferredRole,
    };
  }

  save(preferences: ClientPreferencesRecord): void {
    mkdirSync(dirname(this.path), { recursive: true });
    writeFileSync(this.path, JSON.stringify(preferences, null, 2));
  }
}