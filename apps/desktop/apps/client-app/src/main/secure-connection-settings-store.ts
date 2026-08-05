import { existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { safeStorage } from 'electron';
import type { ClientConnectionSettings } from '../preload/types';

const defaultConnectionSettings: ClientConnectionSettings = {
  currentServerUrl: 'http://127.0.0.1:4090',
  recentServers: [],
  requirePinnedServerMatch: false,
};

export class SecureConnectionSettingsStore {
  private readonly path: string;

  constructor(userDataPath: string) {
    this.path = join(userDataPath, 'secure-connection-settings.bin');
  }

  load(): ClientConnectionSettings {
    if (!existsSync(this.path) || !safeStorage.isEncryptionAvailable()) {
      return defaultConnectionSettings;
    }

    const encrypted = readFileSync(this.path);
    const decrypted = safeStorage.decryptString(encrypted);
    const parsed = JSON.parse(decrypted) as Partial<ClientConnectionSettings>;

    return {
      currentServerUrl: parsed.currentServerUrl ?? defaultConnectionSettings.currentServerUrl,
      currentServerIdentity: parsed.currentServerIdentity,
      recentServers: parsed.recentServers ?? [],
      lastSuccessfulConnectionAt: parsed.lastSuccessfulConnectionAt,
      pinnedServerIdentity: parsed.pinnedServerIdentity,
      requirePinnedServerMatch: parsed.requirePinnedServerMatch ?? defaultConnectionSettings.requirePinnedServerMatch,
    };
  }

  save(settings: ClientConnectionSettings): void {
    if (!safeStorage.isEncryptionAvailable()) {
      throw new Error('Electron safeStorage encryption is not available on this device.');
    }

    mkdirSync(dirname(this.path), { recursive: true });
    const encrypted = safeStorage.encryptString(JSON.stringify(settings));
    writeFileSync(this.path, encrypted);
  }

  reset(): void {
    if (existsSync(this.path)) {
      rmSync(this.path);
    }
  }
}