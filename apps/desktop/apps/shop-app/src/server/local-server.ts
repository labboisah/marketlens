import Fastify from 'fastify';
import { randomUUID } from 'node:crypto';
import { verifyAccessToken } from '../services/security/token-service';
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
import { registerServiceRoutes } from './routes/service-routes';
import { registerShareholderRoutes } from './routes/shareholder-routes';
import { registerReportingRoutes } from './routes/reporting-routes';
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

  const requestWindows = new Map<string, { startedAt: number; count: number }>();
  const requestStartedAt = new Map<string, number>();
  server.addHook('onRequest', async (request, reply) => {
    const providedCorrelationId = request.headers['x-correlation-id'];
    const correlationId = typeof providedCorrelationId === 'string' && providedCorrelationId.length > 0 ? providedCorrelationId : randomUUID();
    reply.header('x-correlation-id', correlationId);
    requestStartedAt.set(request.id, Date.now());
    const key = `${request.ip}:${request.routerPath ?? request.url.split('?')[0]}`;
    const now = Date.now(); const current = requestWindows.get(key);
    const limit = request.url.startsWith('/api/v1/auth/') ? 10 : 120;
    const window = !current || now - current.startedAt >= 60_000 ? { startedAt: now, count: 1 } : { ...current, count: current.count + 1 };
    requestWindows.set(key, window);
    if (window.count > limit) {
      reply.header('retry-after', '60').code(429).send({ success: false, error: { code: 'RATE_LIMITED', message: 'Too many requests. Try again later.' } });
    }
    const bearer = String(request.headers.authorization ?? '').replace(/^Bearer\s+/i, '');
    if (bearer) {
      const payload = verifyAccessToken(bearer, app.environment.SHOP_JWT_SECRET);
      const sessionUuid = typeof payload?.session === 'string' ? payload.session : undefined;
      const session = sessionUuid ? app.database.sqlite.prepare('SELECT revoked_at AS revokedAt, expires_at AS expiresAt FROM user_sessions WHERE uuid = ?').get(sessionUuid) as { revokedAt: string | null; expiresAt: string } | undefined : undefined;
      if (!payload || !session || session.revokedAt || session.expiresAt <= new Date().toISOString()) reply.code(401).send({ success: false, error: { code: 'SESSION_REVOKED', message: 'Your session is no longer active' } });
    }
  });
  server.addHook('onResponse', async (request, reply) => {
    const durationMs = Date.now() - (requestStartedAt.get(request.id) ?? Date.now());
    requestStartedAt.delete(request.id);
    const payload = verifyAccessToken(String(request.headers.authorization ?? '').replace(/^Bearer\s+/i, ''), app.environment.SHOP_JWT_SECRET);
    app.logger.info({ traceId: reply.getHeader('x-correlation-id'), userUuid: typeof payload?.sub === 'string' ? payload.sub : undefined, deviceUuid: request.headers['x-device-uuid'], route: request.routerPath ?? request.url.split('?')[0], responseTimeMs: durationMs, statusCode: reply.statusCode }, 'Local API request completed');
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

  app.services.sync.onEvent((event, payload) => {
    io.emit(event, payload);
    io.emit(socketEvents.syncStatus, app.services.sync.status());
    if (event === 'sync.completed' || event === 'sync.failed') io.emit(socketEvents.platformStatusChanged, app.services.sync.status());
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
  await registerServiceRoutes(server, app);
  await registerShareholderRoutes(server, app);
  await registerReportingRoutes(server, app);
  await registerSessionRoutes(server, app);

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
      app.services.sync.stop();
      await io.close();
      await server.close();
    },
  };
}
