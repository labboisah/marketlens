import { z } from 'zod';
import type { FastifyInstance } from 'fastify';
import type { Server as SocketServer } from 'socket.io';
import { socketEvents } from '../socket-events';
import type { ShopApplication } from '../../services/application/shop-application';
import { verifyAccessToken, signAccessToken } from '../../services/security/token-service';

const loginSchema = z.object({
  username: z.string().min(1),
  password: z.string().min(1),
  deviceUuid: z.string().uuid().optional(),
});

export async function registerAuthRoutes(
  server: FastifyInstance,
  app: ShopApplication,
  io: SocketServer,
): Promise<void> {
  server.post('/api/v1/auth/login', async (request, reply) => {
    const input = loginSchema.parse(request.body);
    const result = await app.services.authentication.login(input);

    if (result.authenticated) {
      io.emit(socketEvents.userLoggedIn, { username: input.username, deviceUuid: input.deviceUuid });
      return { success: true, data: { accessToken: result.accessToken, refreshToken: result.refreshToken } };
    }

    // Generic failure message
    return reply.code(401).send({ success: false, error: { code: 'AUTH_FAILED', message: 'Invalid credentials or unauthorized' } });
  });

  const refreshSchema = z.object({ refreshToken: z.string().min(8) });
  server.post('/api/v1/auth/refresh', async (request, reply) => {
    const input = refreshSchema.parse(request.body);
    const session = await app.services.sessions.findByRefreshToken(input.refreshToken);

    if (!session) {
      return reply.code(401).send({ success: false, error: { code: 'AUTH_FAILED', message: 'Invalid credentials or unauthorized' } });
    }

    // Create a new access token
    const accessToken = signAccessToken({ sub: session.userUuid, session: session.uuid }, app.environment.SHOP_JWT_SECRET, app.environment.SHOP_JWT_ACCESS_EXPIRES_SECONDS);

    return { success: true, data: { accessToken }, meta: {} };
  });

  server.post('/api/v1/auth/logout', async (request, reply) => {
    const refreshToken = (request.body && (request.body as any).refreshToken) || undefined;
    if (typeof refreshToken === 'string') {
      const session = await app.services.sessions.findByRefreshToken(refreshToken);
      if (session) {
        app.services.sessions.revoke(session.uuid);
      }
    }

    io.emit(socketEvents.userLoggedOut, {});
    return { success: true, data: {}, meta: {} };
  });

  server.get('/api/v1/auth/me', async (request, reply) => {
    const header = request.headers.authorization as string | undefined;
    if (!header || !header.startsWith('Bearer ')) {
      return reply.code(401).send({ success: false, error: { code: 'AUTH_FAILED', message: 'Invalid credentials or unauthorized' } });
    }

    const token = (header.split(' ')[1] as string);
    const payload = verifyAccessToken(token, app.environment.SHOP_JWT_SECRET as string);
    if (!payload) {
      return reply.code(401).send({ success: false, error: { code: 'AUTH_FAILED', message: 'Invalid credentials or unauthorized' } });
    }

    const user = await app.services.users.findByUuid((payload as any).sub);
    if (!user) {
      return reply.code(401).send({ success: false, error: { code: 'AUTH_FAILED', message: 'Invalid credentials or unauthorized' } });
    }

    return { success: true, data: { uuid: user.uuid, username: user.username, displayName: user.displayName }, meta: {} };
  });

  const changePasswordSchema = z.object({ oldPassword: z.string().min(1), newPassword: z.string().min(8) });
  server.post('/api/v1/auth/change-password', async (request, reply) => {
    const header = request.headers.authorization as string | undefined;
    if (!header || !header.startsWith('Bearer ')) {
      return reply.code(401).send({ success: false, error: { code: 'AUTH_FAILED', message: 'Invalid credentials or unauthorized' } });
    }

    const token = (header.split(' ')[1] as string);
    const payload = verifyAccessToken(token, app.environment.SHOP_JWT_SECRET as string);
    if (!payload) {
      return reply.code(401).send({ success: false, error: { code: 'AUTH_FAILED', message: 'Invalid credentials or unauthorized' } });
    }

    const input = changePasswordSchema.parse(request.body);
    const changed = await app.services.authentication.changePassword((payload as any).sub, input.oldPassword, input.newPassword);
    if (!changed) {
      return reply.code(400).send({ success: false, error: { code: 'AUTH_FAILED', message: 'Invalid credentials or unauthorized' } });
    }

    return { success: true, data: {}, meta: {} };
  });
}
