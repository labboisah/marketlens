import type { FastifyInstance } from 'fastify';

export async function registerSessionRoutes(server: FastifyInstance): Promise<void> {
  server.get('/api/v1/session/me', async () => ({
    success: true,
    data: {
      authenticated: false,
      user: null,
      permissions: [],
    },
  }));
}
