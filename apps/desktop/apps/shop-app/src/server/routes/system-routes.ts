import type { FastifyInstance } from 'fastify';
import type { Server as SocketServer } from 'socket.io';
import { socketEvents } from '../socket-events';
import type { ShopApplication } from '../../services/application/shop-application';

export async function registerSystemRoutes(
  server: FastifyInstance,
  app: ShopApplication,
  io: SocketServer,
): Promise<void> {
  server.get('/api/v1/system/status', async () => {
    const status = {
      ...app.services.health.status(),
      sync: app.services.sync.status(),
    };

    io.emit(socketEvents.serverStatus, status);
    io.emit(socketEvents.syncStatus, status.sync);

    return status;
  });
}
