import type { FastifyInstance } from 'fastify';
import type { ShopApplication } from '../../services/application/shop-application';
import { verifyAccessToken } from '../../services/security/token-service';

export async function registerHealthRoutes(server: FastifyInstance, app: ShopApplication): Promise<void> {
  server.get('/health', async () => ({
    success: true,
    data: { status: app.services.health.status().status, service: 'marketlens-shop-app', timestamp: new Date().toISOString() },
    meta: {},
  }));

  server.get('/api/v1/system/health', async (request, reply) => {
    const token = String(request.headers.authorization ?? '').replace(/^Bearer\s+/i, '');
    const payload = verifyAccessToken(token, app.environment.SHOP_JWT_SECRET);
    const userUuid = typeof payload?.sub === 'string' ? payload.sub : undefined;
    if (!userUuid) return reply.code(401).send({ success: false, error: { code: 'AUTH_REQUIRED', message: 'Authentication is required' } });
    const allowed = app.database.sqlite.prepare("SELECT 1 FROM user_roles ur JOIN roles r ON r.uuid = ur.role_uuid WHERE ur.user_uuid = ? AND r.name IN ('Owner', 'Administrator') LIMIT 1").get(userUuid);
    if (!allowed) return reply.code(403).send({ success: false, error: { code: 'HEALTH_PERMISSION_DENIED', message: 'System health access is restricted' } });
    return { success: true, data: app.services.health.status(), meta: {} };
  });
}
