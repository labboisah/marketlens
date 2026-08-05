import { afterEach, describe, expect, it, vi } from 'vitest';
import { checkShopStatus, loginToShop, pairDevice, readAuthenticatedSession } from '../src/renderer/src/services/shop-api-client';

const originalFetch = global.fetch;

function jsonResponse(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: {
      'Content-Type': 'application/json',
    },
  });
}

afterEach(() => {
  vi.restoreAllMocks();
  global.fetch = originalFetch;
});

describe('Client App Shop API client', () => {
  it('parses confirmed Shop App status responses', async () => {
    global.fetch = vi.fn(async () =>
      jsonResponse(200, {
        success: true,
        message: 'Operation completed',
        data: {
          applicationVersion: '1.0.0',
          databaseVersion: '3.45.0',
          businessUuid: '00000000-0000-4000-8000-000000000001',
          businessName: 'MarketLens Retail',
          branchUuid: '00000000-0000-4000-8000-000000000010',
          branchName: 'Main Branch',
          shopDeviceUuid: '00000000-0000-4000-8000-000000000099',
          shopDeviceName: 'Front Desk Shop Server',
          shopServerName: 'Front Desk Shop Server',
          serverIp: '192.168.1.20',
          serverPort: 4090,
          serverStatus: 'healthy',
          shopActivationStatus: 'active',
          subscriptionStatus: 'active',
          platformConnectivity: 'online',
          lastSynchronization: null,
          pendingSyncRecordCount: 0,
          authorizedClientAppCount: 2,
          enabledModules: ['sales', 'cashier_shifts', 'inventory'],
          disabledModules: [],
        },
        meta: {},
      }),
    ) as typeof fetch;

    const result = await checkShopStatus('http://127.0.0.1:4090/');

    expect(result.reachable).toBe(true);
    expect(result.status.serverStatus).toBe('healthy');
    expect(result.status.businessName).toBe('MarketLens Retail');
  });

  it('returns pairing responses without pretending approval happened', async () => {
    global.fetch = vi.fn(async () =>
      jsonResponse(202, {
        success: true,
        message: 'Operation completed',
        data: {
          paired: false,
          reason: 'Pending manager approval.',
        },
        meta: {},
      }),
    ) as typeof fetch;

    const result = await pairDevice('http://127.0.0.1:4090', {
      deviceName: 'Client workstation',
      pairingCode: 'PAIR-123',
    });

    expect(result).toEqual({
      paired: false,
      reason: 'Pending manager approval.',
    });
  });

  it('reads authenticated session details from the Shop App', async () => {
    global.fetch = vi.fn(async () =>
      jsonResponse(200, {
        success: true,
        message: 'Operation completed',
        data: {
          authenticated: true,
          user: {
            uuid: '00000000-0000-4000-8000-000000000001',
            username: 'admin',
            displayName: 'Admin',
          },
          permissions: ['sales.process'],
          roles: ['Administrator'],
        },
        meta: {},
      }),
    ) as typeof fetch;

    const result = await readAuthenticatedSession('http://127.0.0.1:4090', 'token');

    expect(result.authenticated).toBe(true);
    expect(result.permissions).toEqual(['sales.process']);
  });

  it('refuses to create a workstation session until the Shop App confirms authentication and pairing', async () => {
    global.fetch = vi
      .fn()
      .mockResolvedValueOnce(
        jsonResponse(200, {
          success: true,
          message: 'Operation completed',
          data: {
            authenticated: true,
            accessToken: 'access-token',
            refreshToken: 'refresh-token',
            expiresAt: new Date(Date.now() + 60 * 60 * 1000).toISOString(),
            accountStatus: 'active',
            passwordChangeRequired: false,
          },
          meta: {},
        }),
      )
      .mockResolvedValueOnce(
        jsonResponse(200, {
          success: true,
          message: 'Operation completed',
          data: {
            authenticated: false,
            user: null,
            permissions: [],
            roles: [],
          },
          meta: {},
        }),
      )
      .mockResolvedValueOnce(
        jsonResponse(200, {
          success: true,
          message: 'Operation completed',
          data: {
            authenticated: false,
            accountStatus: 'active',
            passwordChangeRequired: false,
            user: null,
            permissions: [],
            roles: [],
          },
          meta: {},
        }),
      ) as typeof fetch;

    await expect(
      loginToShop({
        shopBaseUrl: 'http://127.0.0.1:4090',
        identity: 'admin',
        password: 'password',
        deviceUuid: '00000000-0000-4000-8000-000000000001',
      }),
    ).rejects.toThrow('Authentication failed.');
  });
}