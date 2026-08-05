import { existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { safeStorage } from 'electron';

export interface StoredSession {
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

export class SecureSessionStore {
  private readonly path: string;

  constructor(userDataPath: string) {
    this.path = join(userDataPath, 'secure-session.bin');
  }

  load(): StoredSession | null {
    if (!existsSync(this.path) || !safeStorage.isEncryptionAvailable()) {
      return null;
    }

    const encrypted = readFileSync(this.path);
    const decrypted = safeStorage.decryptString(encrypted);
    return JSON.parse(decrypted) as StoredSession;
  }

  save(session: StoredSession): void {
    if (!safeStorage.isEncryptionAvailable()) {
      throw new Error('Electron safeStorage encryption is not available on this device.');
    }

    mkdirSync(dirname(this.path), { recursive: true });
    const encrypted = safeStorage.encryptString(JSON.stringify(session));
    writeFileSync(this.path, encrypted);
  }

  clear(): void {
    if (existsSync(this.path)) {
      rmSync(this.path);
    }
  }
}
