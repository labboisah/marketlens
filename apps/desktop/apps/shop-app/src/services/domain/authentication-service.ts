import { randomUUID, scryptSync, timingSafeEqual } from 'node:crypto';
import type { AuditLogRepository } from '../../repositories/audit-log-repository';
import type { DeviceRepository } from '../../repositories/device-repository';
import type { UserRepository } from '../../repositories/user-repository';

export type LoginInput = {
  username: string;
  password: string;
  deviceUuid?: string;
};

export type LoginResult = {
  authenticated: boolean;
  reason?: string;
  sessionUuid?: string;
  userUuid?: string;
  permissions?: string[];
};

const PASSWORD_HASH_SEPARATOR = '$';

export function hashPassword(password: string): string {
  const salt = randomUUID();
  const derived = scryptSync(password, salt, 64).toString('hex');
  return `${salt}${PASSWORD_HASH_SEPARATOR}${derived}`;
}

export function verifyPassword(password: string, storedHash: string): boolean {
  const [salt, derivedHash] = storedHash.split(PASSWORD_HASH_SEPARATOR);

  if (!salt || !derivedHash) {
    return false;
  }

  const attempted = scryptSync(password, salt, 64).toString('hex');
  const attemptedBuffer = Buffer.from(attempted, 'hex');
  const storedBuffer = Buffer.from(derivedHash, 'hex');

  if (attemptedBuffer.length !== storedBuffer.length) {
    return false;
  }

  return timingSafeEqual(attemptedBuffer, storedBuffer);
}

export class AuthenticationService {
  constructor(
    private readonly users: UserRepository,
    private readonly devices: DeviceRepository,
    private readonly auditLogs: AuditLogRepository,
  ) {}

  async login(input: LoginInput): Promise<LoginResult> {
    if (!input.username || !input.password) {
      return { authenticated: false, reason: 'Missing credentials.' };
    }

    const normalizedUsername = input.username.trim().toLowerCase();
    const user = await this.users.findByUsername(normalizedUsername);

    if (!user) {
      return { authenticated: false, reason: 'Invalid username or password.' };
    }

    if (user.status !== 'active') {
      return { authenticated: false, reason: 'User is inactive.' };
    }

    if (!verifyPassword(input.password, user.passwordHash)) {
      return { authenticated: false, reason: 'Invalid username or password.' };
    }

    if (input.deviceUuid) {
      const device = await this.devices.findByUuid(input.deviceUuid);
      if (!device || device.status !== 'paired') {
        return { authenticated: false, reason: 'Device is not authorized.' };
      }
    }

    const sessionUuid = randomUUID();

    this.auditLogs.record({
      actorUserUuid: user.uuid,
      action: 'user.login',
      subjectType: 'user',
      subjectUuid: user.uuid,
      metadata: {
        deviceUuid: input.deviceUuid ?? null,
      },
    });

    return {
      authenticated: true,
      sessionUuid,
      userUuid: user.uuid,
      permissions: ['pos.access'],
    };
  }
}
