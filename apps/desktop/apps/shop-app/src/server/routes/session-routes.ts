import type { FastifyInstance } from 'fastify';
import { verifyAccessToken } from '../../services/security/token-service';
import type { ShopApplication } from '../../services/application/shop-application';

function sessionState(app: ShopApplication, accessToken: string) {
  const payload = verifyAccessToken(accessToken, app.environment.SHOP_JWT_SECRET);
  const userUuid = typeof payload?.sub === 'string' ? payload.sub : undefined;

  if (!userUuid) {
    return {
      authenticated: false,
      accountStatus: 'inactive',
      passwordChangeRequired: false,
      user: null,
      permissions: [],
      roles: [],
    };
  }

  const user = app.database.sqlite
    .prepare('SELECT uuid, username, display_name AS displayName, status, force_password_change AS forcePasswordChange FROM users WHERE uuid = ? LIMIT 1')
    .get(userUuid) as { uuid: string; username: string; displayName: string; status: string; forcePasswordChange: string } | undefined;

  if (!user) {
    return {
      authenticated: false,
      accountStatus: 'inactive',
      passwordChangeRequired: false,
      user: null,
      permissions: [],
      roles: [],
    };
  }

  const permissions = app.database.sqlite
    .prepare(
      `SELECT DISTINCT p.key AS key
       FROM user_roles ur
       JOIN roles r ON r.uuid = ur.role_uuid
       LEFT JOIN role_permissions rp ON rp.role_uuid = r.uuid
       LEFT JOIN permissions p ON p.uuid = rp.permission_uuid
       WHERE ur.user_uuid = ? AND p.key IS NOT NULL`,
    )
    .all(userUuid) as Array<{ key: string }>;

  const roles = app.database.sqlite
    .prepare(
      `SELECT DISTINCT r.name AS name
       FROM user_roles ur
       JOIN roles r ON r.uuid = ur.role_uuid
       WHERE ur.user_uuid = ?`,
    )
    .all(userUuid) as Array<{ name: string }>;

  return {
    authenticated: true,
    accountStatus: user.status,
    passwordChangeRequired: user.forcePasswordChange === '1',
    user,
    permissions: permissions.map((permission) => permission.key),
    roles: roles.map((role) => role.name),
  };
}

export async function registerSessionRoutes(server: FastifyInstance, app: ShopApplication): Promise<void> {
  server.get('/api/v1/session/me', async (request) => {
    const accessToken = String(request.headers.authorization ?? '').replace(/^Bearer\s+/i, '');

    return {
      success: true,
      data: sessionState(app, accessToken),
      meta: {},
    };
  });
}
