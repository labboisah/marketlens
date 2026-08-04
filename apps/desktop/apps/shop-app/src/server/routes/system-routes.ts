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
    const syncStatus = app.services.sync.status();
    const healthStatus = app.services.health.status();

    const databaseVersion = app.database.sqlite
      .prepare('select sqlite_version() as version')
      .get().version as string;

    const pendingSyncCountRow = app.database.sqlite
      .prepare('select count(*) as count from sync_queue where status = ?')
      .get('pending');

    const authorizedClientAppCountRow = app.database.sqlite
      .prepare('select count(*) as count from devices where kind = ? and status = ?')
      .get('client_app', 'paired');

    const response = {
      success: true,
      data: {
        applicationVersion: app.environment.SHOP_APP_VERSION,
        databaseVersion,
        serverStatus: healthStatus.status,
        shopActivationStatus: healthStatus.activationStatus,
        platformConnectivity: syncStatus.platformConnectivity,
        lastSynchronization: syncStatus.lastSynchronization,
        pendingSyncRecordCount: typeof pendingSyncCountRow?.count === 'number' ? pendingSyncCountRow.count : 0,
        authorizedClientAppCount: typeof authorizedClientAppCountRow?.count === 'number' ? authorizedClientAppCountRow.count : 0,
      },
      meta: {},
    };

    io.emit(socketEvents.serverStatus, healthStatus);
    io.emit(socketEvents.syncStatus, syncStatus);

    return response;
  });
}
