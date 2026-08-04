import type { FastifyInstance } from 'fastify';
import type { ShopApplication } from '../../services/application/shop-application';

export async function registerHealthRoutes(server: FastifyInstance, app: ShopApplication): Promise<void> {
  server.get('/health', async () => ({
    success: true,
    data: app.services.health.status(),
    meta: {},
  }));
}
