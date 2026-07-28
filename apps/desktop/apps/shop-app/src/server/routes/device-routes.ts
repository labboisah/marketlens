import { z } from 'zod';
import type { FastifyInstance } from 'fastify';
import type { Server as SocketServer } from 'socket.io';
import { socketEvents } from '../socket-events';
import type { ShopApplication } from '../../services/application/shop-application';

const pairDeviceSchema = z.object({
  deviceName: z.string().min(1).max(120),
  pairingCode: z.string().min(1).max(128),
});

export async function registerDeviceRoutes(
  server: FastifyInstance,
  app: ShopApplication,
  io: SocketServer,
): Promise<void> {
  server.post('/api/v1/devices/pair', async (request, reply) => {
    const input = pairDeviceSchema.parse(request.body);
    const result = await app.services.devices.pair(input);

    if (result.paired) {
      io.emit(socketEvents.deviceConnected, { deviceUuid: result.deviceUuid, deviceName: input.deviceName });
      return { success: true, data: result };
    }

    return reply.code(202).send({ success: true, data: result });
  });
}
