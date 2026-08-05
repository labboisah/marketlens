import type { FastifyInstance } from 'fastify';
import type { Server as SocketServer } from 'socket.io';
import { networkInterfaces } from 'node:os';
import { socketEvents } from '../socket-events';
import type { ShopApplication } from '../../services/application/shop-application';

function localServerIp(configuredHost: string): string {
  if (configuredHost && configuredHost !== '0.0.0.0') {
    return configuredHost;
  }

  const firstLan = Object.values(networkInterfaces())
    .flat()
    .find((item): item is NonNullable<typeof item> => Boolean(item && item.family === 'IPv4' && !item.internal));

  return firstLan?.address ?? '127.0.0.1';
}

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

    const installation = app.database.sqlite
      .prepare('select business_uuid as businessUuid, business_name as businessName, branch_uuid as branchUuid, branch_name as branchName, device_uuid as deviceUuid, device_name as deviceName from app_installation order by updated_at desc limit 1')
      .get() as { businessUuid?: string; businessName?: string; branchUuid?: string; branchName?: string; deviceUuid?: string; deviceName?: string } | undefined;

    const subscriptionSnapshot = app.database.sqlite
      .prepare('select subscription_state as subscriptionState from subscription_snapshot order by updated_at desc limit 1')
      .get() as { subscriptionState?: string } | undefined;

    const moduleRows = app.database.sqlite
      .prepare('select module_key as moduleKey, plan_allowed as planAllowed, business_enabled as businessEnabled, branch_effective_status as branchEffectiveStatus from module_settings order by module_key asc')
      .all() as Array<{ moduleKey: string; planAllowed: string; businessEnabled: string; branchEffectiveStatus: string }>;

    const enabledModules = moduleRows
      .filter((moduleRow) =>
        ['1', 'true', 'enabled', 'active'].includes(String(moduleRow.planAllowed).toLowerCase()) &&
        ['1', 'true', 'enabled', 'active'].includes(String(moduleRow.businessEnabled).toLowerCase()) &&
        ['1', 'true', 'enabled', 'active'].includes(String(moduleRow.branchEffectiveStatus).toLowerCase()),
      )
      .map((moduleRow) => moduleRow.moduleKey);

    const disabledModules = moduleRows
      .filter((moduleRow) => !enabledModules.includes(moduleRow.moduleKey))
      .map((moduleRow) => moduleRow.moduleKey);

    const response = {
      success: true,
      data: {
        applicationVersion: app.environment.SHOP_APP_VERSION,
        databaseVersion,
        businessUuid: installation?.businessUuid ?? null,
        businessName: installation?.businessName ?? null,
        branchUuid: installation?.branchUuid ?? null,
        branchName: installation?.branchName ?? null,
        shopDeviceUuid: installation?.deviceUuid ?? null,
        shopDeviceName: installation?.deviceName ?? app.environment.SHOP_DEVICE_NAME,
        shopServerName: installation?.deviceName ?? app.environment.SHOP_DEVICE_NAME,
        serverIp: localServerIp(app.environment.SHOP_LOCAL_API_HOST),
        serverPort: app.environment.SHOP_LOCAL_API_PORT,
        serverStatus: healthStatus.status,
        shopActivationStatus: healthStatus.activationStatus,
        subscriptionStatus: subscriptionSnapshot?.subscriptionState ?? 'unknown',
        platformConnectivity: syncStatus.platformConnectivity,
        lastSynchronization: syncStatus.lastSynchronization,
        pendingSyncRecordCount: typeof pendingSyncCountRow?.count === 'number' ? pendingSyncCountRow.count : 0,
        authorizedClientAppCount: typeof authorizedClientAppCountRow?.count === 'number' ? authorizedClientAppCountRow.count : 0,
        enabledModules,
        disabledModules,
      },
      meta: {},
    };

    io.emit(socketEvents.serverStatus, healthStatus);
    io.emit(socketEvents.syncStatus, syncStatus);

    return response;
  });
}
