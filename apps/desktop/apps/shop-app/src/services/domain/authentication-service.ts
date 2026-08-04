import { randomUUID } from 'node:crypto';
import argon2 from 'argon2';
import type { AuditLogRepository } from '../../repositories/audit-log-repository';
import type { DeviceRepository } from '../../repositories/device-repository';
import type { UserRepository } from '../../repositories/user-repository';
import type { SessionRepository } from '../../repositories/session-repository';
import { signAccessToken, createRefreshToken } from '../security/token-service';
import type { ShopEnvironment } from '../../shared/environment';

export type LoginInput = {
  username: string;
  password: string;
  deviceUuid?: string;
};

export type LoginResult = {
  authenticated: boolean;
  reason?: string;
  accessToken?: string;
  refreshToken?: string;
  sessionUuid?: string;
  userUuid?: string;
  permissions?: string[];
};

export async function hashPassword(password: string): Promise<string> {
  return argon2.hash(password);
}

export async function verifyPassword(password: string, storedHash: string): Promise<boolean> {
  try {
    return await argon2.verify(storedHash, password);
  } catch (err) {
    return false;
  }
}

export class AuthenticationService {
  constructor(
    private readonly users: UserRepository,
    private readonly devices: DeviceRepository,
    private readonly sessions: SessionRepository,
    private readonly auditLogs: AuditLogRepository,
    private readonly environment: ShopEnvironment,
  ) {}

  async login(input: LoginInput): Promise<LoginResult> {
    if (!input.username || !input.password) {
      this.auditLogs.record({ action: 'user.login_failed', metadata: { reason: 'missing_credentials' } });
      return { authenticated: false, reason: 'Invalid username or password.' };
    }
    const normalizedUsername = input.username.trim().toLowerCase();
    const user = await this.users.findByUsername(normalizedUsername);

    if (!user) {
      this.auditLogs.record({ action: 'user.login_failed', metadata: { username: normalizedUsername, reason: 'unknown_user' } });
      return { authenticated: false, reason: 'Invalid username or password.' };
    }

    if (user.status !== 'active') {
      this.auditLogs.record({ actorUserUuid: user.uuid, action: 'user.login_failed', subjectType: 'user', subjectUuid: user.uuid, metadata: { reason: 'inactive' } });
      return { authenticated: false, reason: 'User is inactive.' };
    }

    const passwordOk = await verifyPassword(input.password, user.passwordHash);
    if (!passwordOk) {
      this.auditLogs.record({ actorUserUuid: user.uuid, action: 'user.login_failed', subjectType: 'user', subjectUuid: user.uuid, metadata: { reason: 'invalid_password' } });
      return { authenticated: false, reason: 'Invalid username or password.' };
    }

    if (input.deviceUuid) {
      const device = await this.devices.findByUuid(input.deviceUuid);
      if (!device || device.status !== 'authorized' && device.status !== 'paired') {
        this.auditLogs.record({ actorUserUuid: user.uuid, action: 'user.login_failed', subjectType: 'user', subjectUuid: user.uuid, metadata: { reason: 'device_not_authorized', deviceUuid: input.deviceUuid } });
        return { authenticated: false, reason: 'Device is not authorized.' };
      }
    }

    const sessionUuid = randomUUID();

    // Create tokens
    const accessToken = signAccessToken({ sub: user.uuid, session: sessionUuid }, this.environment.SHOP_JWT_SECRET, this.environment.SHOP_JWT_ACCESS_EXPIRES_SECONDS);
    const refreshToken = createRefreshToken();

    // Hash refresh token for storage
    const refreshHash = await argon2.hash(refreshToken);
    const accessHash = await argon2.hash(accessToken);

    const expiresAt = new Date(Date.now() + this.environment.SHOP_JWT_REFRESH_EXPIRES_DAYS * 24 * 60 * 60 * 1000).toISOString();

    this.sessions.create(user.uuid, input.deviceUuid ?? null, accessHash, refreshHash, expiresAt);

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
      accessToken,
      refreshToken,
      sessionUuid,
      userUuid: user.uuid,
      permissions: ['pos.access'],
    };
  }

  async changePassword(userUuid: string, oldPassword: string, newPassword: string) {
    const user = await this.users.findByUuid(userUuid);
    if (!user) return false;

    const ok = await verifyPassword(oldPassword, user.passwordHash);
    if (!ok) return false;

    const newHash = await hashPassword(newPassword);
    this.users.updatePassword(userUuid, newHash);
    this.auditLogs.record({ action: 'user.change_password', subjectType: 'user', subjectUuid: userUuid });
    return true;
  }
}
