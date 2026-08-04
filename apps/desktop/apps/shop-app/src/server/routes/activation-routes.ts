import { z } from 'zod';
import type { FastifyInstance } from 'fastify';
import type { ShopApplication } from '../../services/application/shop-application';

const completeActivationSchema = z.object({
  activationCode: z.string().min(1),
  deviceUuid: z.string().uuid(),
  deviceName: z.string().min(1),
  operatingSystem: z.string().min(1),
  appVersion: z.string().min(1),
  deviceFingerprint: z.string().min(1),
});

const platformConnectionSchema = z.object({
  platformBaseUrl: z.string().url(),
});

export async function registerActivationRoutes(server: FastifyInstance, app: ShopApplication): Promise<void> {
  server.post('/api/v1/activation/complete', async (request, reply) => {
    const input = completeActivationSchema.parse(request.body);
    const result = await app.services.activation.completeActivation(input);

    if (!result.success) {
      return reply.code(400).send({ success: false, error: { code: 'ACTIVATION_FAILED', message: result.reason ?? 'Activation failed' } });
    }

    return { success: true, data: result.activationData, meta: {} };
  });

  server.get('/api/v1/activation/status', async (request, reply) => {
    const deviceUuid = typeof request.query.deviceUuid === 'string' ? request.query.deviceUuid : undefined;
    const status = app.services.activation.getStatus(deviceUuid);
    return { success: true, data: { status }, meta: {} };
  });

  server.post('/api/v1/platform/connection-test', async (request, reply) => {
    const input = platformConnectionSchema.parse(request.body);
    const result = await app.services.activation.testConnection(input.platformBaseUrl);

    if (!result.success) {
      return reply.code(502).send({ success: false, error: { code: 'PLATFORM_CONNECTION_FAILED', message: result.reason ?? 'Unable to reach Platform' } });
    }

    return { success: true, data: { success: true }, meta: {} };
  });
}
