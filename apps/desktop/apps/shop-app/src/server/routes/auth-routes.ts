import { z } from 'zod';
import type { FastifyInstance, FastifyReply } from 'fastify';
import type { Server as SocketServer } from 'socket.io';
import argon2 from 'argon2';
import { randomUUID } from 'node:crypto';
import { socketEvents } from '../socket-events';
import type { ShopApplication } from '../../services/application/shop-application';
import { createRefreshToken, verifyAccessToken, signAccessToken } from '../../services/security/token-service';

const loginSchema = z.object({
  identity: z.string().min(1).optional(),
  username: z.string().min(1).optional(),
  staffCode: z.string().min(1).optional(),
  password: z.string().min(1),
  deviceUuid: z.string().uuid().optional(),
  rememberDevice: z.boolean().optional(),
}).refine((value: { identity?: string; username?: string; staffCode?: string }) => Boolean(value.identity || value.username || value.staffCode), {
  message: 'identity is required',
});

function authError(reply: FastifyReply, statusCode = 401, code = 'AUTHENTICATION_REQUIRED') {
  return reply.code(statusCode).send({
    success: false,
    message: 'Authentication failed.',
    code,
    errors: {},
    trace_id: randomUUID(),
  });
}

function sessionSnapshot(app: ShopApplication, userUuid: string) {
  const user = app.database.sqlite
    .prepare('SELECT uuid, username, display_name AS displayName, status, force_password_change AS forcePasswordChange FROM users WHERE uuid = ? LIMIT 1')
    .get(userUuid) as { uuid: string; username: string; displayName: string; status: string; forcePasswordChange: string } | undefined;

  if (!user) {
    return null;
  }

  const permissions = app.database.sqlite
    .prepare(
      `SELECT DISTINCT p.key AS key
       FROM user_roles ur
       JOIN roles r ON r.uuid = ur.role_uuid
       LEFT JOIN role_permissions rp ON rp.role_uuid = r.uuid
       LEFT JOIN permissions p ON p.uuid = rp.permission_uuid
       WHERE ur.user_uuid = ? AND p.key IS NOT NULL`,
    )
    .all(userUuid) as Array<{ key: string }>;

  const roles = app.database.sqlite
    .prepare(
      `SELECT DISTINCT r.name AS name
       FROM user_roles ur
       JOIN roles r ON r.uuid = ur.role_uuid
       WHERE ur.user_uuid = ?`,
    )
    .all(userUuid) as Array<{ name: string }>;

  return {
    user: {
      uuid: user.uuid,
      username: user.username,
      displayName: user.displayName,
    },
    accountStatus: user.status,
    passwordChangeRequired: user.forcePasswordChange === '1',
    permissions: permissions.map((permission) => permission.key),
    roles: roles.map((role) => role.name),
  };
}

function parseBearer(header?: string): string | null {
  if (!header || !header.startsWith('Bearer ')) {
    return null;
  }

  return header.slice('Bearer '.length);
}

export async function registerAuthRoutes(
  server: FastifyInstance,
  app: ShopApplication,
  io: SocketServer,
): Promise<void> {
  server.post('/api/v1/auth/login', async (request: any, reply: FastifyReply) => {
    const input = loginSchema.parse(request.body);
    const identity = input.identity ?? input.staffCode ?? input.username;
    const result = await app.services.authentication.login({
      identity: String(identity),
      password: input.password,
      deviceUuid: input.deviceUuid,
      rememberDevice: input.rememberDevice,
    });

    if (result.authenticated) {
      io.emit(socketEvents.userLoggedIn, { identity, deviceUuid: input.deviceUuid });
      return {
        success: true,
        data: {
          authenticated: true,
          accessToken: result.accessToken,
          refreshToken: result.refreshToken,
          expiresAt: result.expiresAt,
          accountStatus: result.accountStatus ?? 'active',
          passwordChangeRequired: result.passwordChangeRequired ?? false,
        },
        meta: {},
      };
    }

    if (result.reasonCode === 'ACCOUNT_DISABLED') {
      return authError(reply, 403, 'ACCOUNT_DISABLED');
    }

    return authError(reply, 401, 'AUTHENTICATION_REQUIRED');
  });

  const refreshSchema = z.object({ refreshToken: z.string().min(8) });
  server.post('/api/v1/auth/refresh', async (request: any, reply: FastifyReply) => {
    const input = refreshSchema.parse(request.body);
    const session = await app.services.sessions.findByRefreshToken(input.refreshToken);

    if (!session) {
      return authError(reply);
    }

    if (session.expiresAt && new Date(session.expiresAt).getTime() <= Date.now()) {
      app.services.sessions.revoke(session.uuid);
      return authError(reply);
    }

    const newRefreshToken = createRefreshToken();
    const refreshHash = await argon2.hash(newRefreshToken);
    const expiresAt = new Date(Date.now() + app.environment.SHOP_JWT_REFRESH_EXPIRES_DAYS * 24 * 60 * 60 * 1000).toISOString();
    app.services.sessions.rotateRefreshToken(session.uuid, refreshHash, expiresAt);

    const accessToken = signAccessToken({ sub: session.userUuid, session: session.uuid }, app.environment.SHOP_JWT_SECRET, app.environment.SHOP_JWT_ACCESS_EXPIRES_SECONDS);

    return { success: true, data: { accessToken, refreshToken: newRefreshToken, expiresAt }, meta: {} };
  });

  server.post('/api/v1/auth/logout', async (request: any, reply: FastifyReply) => {
    const refreshToken = (request.body && (request.body as { refreshToken?: string }).refreshToken) || undefined;
    const bearer = parseBearer(request.headers.authorization as string | undefined);

    if (typeof refreshToken === 'string') {
      const session = await app.services.sessions.findByRefreshToken(refreshToken);
      if (session) {
        app.services.sessions.revoke(session.uuid);
      }
    }

    if (bearer) {
      const payload = verifyAccessToken(bearer, app.environment.SHOP_JWT_SECRET);
      const sessionUuid = typeof payload?.session === 'string' ? payload.session : null;
      if (sessionUuid) {
        app.services.sessions.revoke(sessionUuid);
      }
    }

    io.emit(socketEvents.userLoggedOut, {});
    return { success: true, data: { loggedOut: true }, meta: {} };
  });

  server.get('/api/v1/auth/me', async (request: any, reply: FastifyReply) => {
    const token = parseBearer(request.headers.authorization as string | undefined);
    if (!token) {
      return authError(reply);
    }

    const payload = verifyAccessToken(token, app.environment.SHOP_JWT_SECRET as string);
    if (!payload) {
      return authError(reply);
    }

    const sessionUuid = typeof (payload as { session?: unknown }).session === 'string' ? (payload as { session: string }).session : null;
    if (!sessionUuid) {
      return authError(reply);
    }

    const session = app.services.sessions.findByUuid(sessionUuid);
    if (!session || session.revokedAt) {
      return authError(reply);
    }

    const userUuid = typeof (payload as { sub?: unknown }).sub === 'string' ? (payload as { sub: string }).sub : null;
    if (!userUuid) {
      return authError(reply);
    }

    const snapshot = sessionSnapshot(app, userUuid);
    if (!snapshot) {
      return authError(reply);
    }

    return {
      success: true,
      data: {
        authenticated: true,
        accountStatus: snapshot.accountStatus,
        passwordChangeRequired: snapshot.passwordChangeRequired,
        user: snapshot.user,
        permissions: snapshot.permissions,
        roles: snapshot.roles,
      },
      meta: {},
    };
  });

  const changePasswordSchema = z.object({ oldPassword: z.string().min(1), newPassword: z.string().min(8) });
  server.post('/api/v1/auth/change-password', async (request: any, reply: FastifyReply) => {
    const token = parseBearer(request.headers.authorization as string | undefined);
    if (!token) {
      return authError(reply);
    }

    const payload = verifyAccessToken(token, app.environment.SHOP_JWT_SECRET as string);
    if (!payload) {
      return authError(reply);
    }

    const userUuid = typeof (payload as { sub?: unknown }).sub === 'string' ? (payload as { sub: string }).sub : null;
    if (!userUuid) {
      return authError(reply);
    }

    const input = changePasswordSchema.parse(request.body);
    const changed = await app.services.authentication.changePassword(userUuid, input.oldPassword, input.newPassword);
    if (!changed) {
      return authError(reply, 400, 'AUTHENTICATION_REQUIRED');
    }

    app.services.sessions.revokeByUser(userUuid);

    return { success: true, data: { changed: true }, meta: {} };
  });
}
