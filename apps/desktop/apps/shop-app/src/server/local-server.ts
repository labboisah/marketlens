import Fastify from 'fastify';
import { randomUUID } from 'node:crypto';
import { Server as SocketServer } from 'socket.io';
import { registerActivationRoutes } from './routes/activation-routes';
import { registerAuthRoutes } from './routes/auth-routes';
import { registerDatabaseRoutes } from './routes/database-routes';
import { registerDeviceRoutes } from './routes/device-routes';
import { registerHealthRoutes } from './routes/health-routes';
import { registerInventoryRoutes } from './routes/inventory-routes';
import { registerProductRoutes } from './routes/product-routes';
import { registerPurchaseRoutes } from './routes/purchase-routes';
import { registerCustomerRoutes } from './routes/customer-routes';
import { registerCashierRoutes } from './routes/cashier-routes';
import { registerSalesRoutes } from './routes/sales-routes';
import { registerSessionRoutes } from './routes/session-routes';
import { registerSystemRoutes } from './routes/system-routes';
import { socketEvents } from './socket-events';
import type { ShopApplication } from '../services/application/shop-application';

export async function createLocalServer(app: ShopApplication) {
  const server = Fastify({
    loggerInstance: app.logger,
  });

  server.setErrorHandler((error, request, reply) => {
    app.logger.error({ error, requestId: request.id }, 'Local API request failed');
    reply.code(error.statusCode ?? 500).send({
      success: false,
      error: {
        code: error.code ?? 'LOCAL_API_ERROR',
        message: error.message,
      },
    });
  });

  const io = new SocketServer(server.server, {
    path: app.environment.SHOP_SOCKET_IO_PATH,
    cors: {
      origin: app.environment.SHOP_LOCAL_API_ALLOWED_ORIGINS.split(',').map((origin) => origin.trim()),
      credentials: true,
    },
  });

  server.addHook('onRequest', async (request, reply) => {
    const providedCorrelationId = request.headers['x-correlation-id'];
    const correlationId = typeof providedCorrelationId === 'string' && providedCorrelationId.length > 0 ? providedCorrelationId : randomUUID();
    reply.header('x-correlation-id', correlationId);
  });

  io.on('connection', (socket) => {
    app.logger.info({ socketId: socket.id }, 'Client socket connected');
    io.emit(socketEvents.deviceConnected, { socketId: socket.id });

    socket.emit(socketEvents.serverStatus, app.services.health.status());
    socket.emit(socketEvents.syncStatus, app.services.sync.status());

    socket.on('disconnect', () => {
      app.logger.info({ socketId: socket.id }, 'Client socket disconnected');
      io.emit(socketEvents.deviceDisconnected, { socketId: socket.id });
    });
  });

  await registerHealthRoutes(server, app);
  await registerSystemRoutes(server, app, io);
  await registerDatabaseRoutes(server, app);
  await registerActivationRoutes(server, app);
  await registerAuthRoutes(server, app, io);
  await registerDeviceRoutes(server, app, io);
  await registerProductRoutes(server, app);
  await registerInventoryRoutes(server, app);
  await registerPurchaseRoutes(server, app);
  await registerCustomerRoutes(server, app);
  await registerCashierRoutes(server, app);
  await registerSalesRoutes(server, app);
  await registerSessionRoutes(server);

  return {
    server,
    io,
    async start() {
      await server.listen({
        host: app.environment.SHOP_LOCAL_API_HOST,
        port: app.environment.SHOP_LOCAL_API_PORT,
      });
      app.services.sync.start();
      io.emit(socketEvents.serverStatus, app.services.health.status());
    },
    async stop() {
      await io.close();
      await server.close();
    },
  };
}
