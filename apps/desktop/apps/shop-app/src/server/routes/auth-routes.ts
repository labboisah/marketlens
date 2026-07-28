import { z } from 'zod';
import type { FastifyInstance } from 'fastify';
import type { Server as SocketServer } from 'socket.io';
import { socketEvents } from '../socket-events';
import type { ShopApplication } from '../../services/application/shop-application';

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
      return { success: true, data: result };
    }

    return reply.code(401).send({ success: false, error: { code: 'AUTH_FAILED', message: result.reason } });
  });
}
