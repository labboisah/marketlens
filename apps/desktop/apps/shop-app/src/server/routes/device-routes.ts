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
  // Legacy pairing by code (keeps compatibility)
  server.post('/api/v1/devices/pair', async (request, reply) => {
    const input = pairDeviceSchema.parse(request.body);
    const result = await app.services.devices.pair(input);

    if (result.paired) {
      io.emit(socketEvents.deviceConnected, { deviceUuid: result.deviceUuid, deviceName: input.deviceName });
      io.emit(socketEvents.deviceAuthorized, { deviceUuid: result.deviceUuid });
      return { success: true, data: result };
    }

    return reply.code(202).send({ success: true, data: result });
  });

  const pairRequestSchema = z.object({ deviceName: z.string().min(1).max(120), kind: z.string().optional() });
  server.post('/api/v1/devices/pair-request', async (request) => {
    const input = pairRequestSchema.parse(request.body);
    const device = app.services.clientDevices.createDevice(input.deviceName, input.kind ?? 'client_app');
    const pairing = app.services.clientDevices.createPairingCode(Math.random().toString(36).slice(2, 8).toUpperCase(), device.uuid);

    io.emit(socketEvents.devicePairingRequested, { deviceUuid: device.uuid, deviceName: device.name });

    return { success: true, data: { deviceUuid: device.uuid, pairingCode: pairing.code }, meta: {} };
  });

  const approveSchema = z.object({ deviceUuid: z.string().uuid() });
  server.post('/api/v1/devices/approve', async (request) => {
    const input = approveSchema.parse(request.body);
    app.services.clientDevices.approve(input.deviceUuid);
    io.emit(socketEvents.deviceAuthorized, { deviceUuid: input.deviceUuid });
    return { success: true, data: {}, meta: {} };
  });

  const revokeSchema = z.object({ deviceUuid: z.string().uuid() });
  server.post('/api/v1/devices/revoke', async (request) => {
    const input = revokeSchema.parse(request.body);
    app.services.clientDevices.revoke(input.deviceUuid);
    io.emit(socketEvents.deviceRevoked, { deviceUuid: input.deviceUuid });
    return { success: true, data: {}, meta: {} };
  });

  server.get('/api/v1/devices', async () => {
    const list = app.services.clientDevices.list();
    return { success: true, data: list, meta: {} };
  });

  server.get('/api/v1/devices/status', async (request) => {
    const deviceUuid = typeof request.query.deviceUuid === 'string' ? request.query.deviceUuid : undefined;
    if (!deviceUuid) return { success: false, error: { code: 'VALIDATION_ERROR', message: 'deviceUuid is required' } };
    const device = app.services.clientDevices.findByUuid(deviceUuid);
    return { success: true, data: { status: device?.status ?? 'unknown' }, meta: {} };
  });
}
