import type { FastifyInstance } from 'fastify';
import type { ShopApplication } from '../../services/application/shop-application';

export async function registerDatabaseRoutes(server: FastifyInstance, app: ShopApplication): Promise<void> {
  server.get('/api/v1/system/database', async () => {
    const sqliteVersion = app.database.sqlite
      .prepare('select sqlite_version() as version')
      .get().version as string;

    const userVersionRow = app.database.sqlite
      .prepare('PRAGMA user_version')
      .get();

    const pendingSyncCountRow = app.database.sqlite
      .prepare('select count(*) as count from sync_queue where status = ?')
      .get('pending');

    const authorizedClientAppCountRow = app.database.sqlite
      .prepare('select count(*) as count from devices where kind = ? and status = ?')
      .get('client_app', 'paired');

    return {
      success: true,
      data: {
        databaseVersion: sqliteVersion,
        userVersion: typeof userVersionRow?.user_version === 'number' ? userVersionRow.user_version : 0,
        pendingSyncRecordCount: typeof pendingSyncCountRow?.count === 'number' ? pendingSyncCountRow.count : 0,
        authorizedClientAppCount: typeof authorizedClientAppCountRow?.count === 'number' ? authorizedClientAppCountRow.count : 0,
      },
      meta: {},
    };
  });
}
