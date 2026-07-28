import { z } from 'zod';

export const connectionStatusSchema = z.object({
  success: z.boolean().optional(),
  data: z
    .object({
      status: z.string().optional(),
      shop: z
        .object({
          name: z.string().optional(),
        })
        .optional(),
    })
    .optional(),
});

export interface ShopLoginInput {
  shopBaseUrl: string;
  username: string;
  password: string;
  deviceUuid?: string;
}

export async function checkShopStatus(baseUrl: string): Promise<{ reachable: boolean; name: string }> {
  const response = await fetch(`${baseUrl}/api/v1/system/status`, {
    headers: { Accept: 'application/json' },
  });

  if (!response.ok) {
    throw new Error(`Shop App returned ${response.status}`);
  }

  const parsed = connectionStatusSchema.parse(await response.json());
  return {
    reachable: true,
    name: parsed.data?.shop?.name ?? 'MarketLens Shop App',
  };
}

export async function pairDevice(shopBaseUrl: string, input: { deviceName: string; pairingCode: string }) {
  const response = await fetch(`${shopBaseUrl}/api/v1/devices/pair`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(input),
  });

  if (!response.ok && response.status !== 202) {
    throw new Error(`Device pairing failed with ${response.status}`);
  }

  return response.json() as Promise<{
    success: true;
    data: {
      paired: boolean;
      reason?: string;
      deviceUuid?: string;
    };
  }>;
}

export async function loginToShop(input: ShopLoginInput) {
  const response = await fetch(`${input.shopBaseUrl}/api/v1/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({
      username: input.username,
      password: input.password,
      deviceUuid: input.deviceUuid,
    }),
  });

  if (!response.ok) {
    throw new Error('Login was rejected by the Shop App.');
  }

  const payload = (await response.json()) as {
    data?: {
      sessionUuid?: string;
      userUuid?: string;
      permissions?: string[];
    };
  };

  return {
    token: payload.data?.sessionUuid ?? 'shop-session-token-placeholder',
    userUuid: payload.data?.userUuid ?? '00000000-0000-4000-8000-000000000001',
    permissions: payload.data?.permissions ?? ['pos.access'],
    expiresAt: new Date(Date.now() + 8 * 60 * 60 * 1000).toISOString(),
  };
}
