import {
  apiBasePath,
  apiErrorResponseSchema,
  shopChangePasswordRequestSchema,
  shopChangePasswordResponseSchema,
  shopAuthMeResponseSchema,
  shopDevicePairRequestSchema,
  shopDevicePairResponseSchema,
  shopDeviceStatusResponseSchema,
  shopLoginRequestSchema,
  shopLoginResponseSchema,
  shopLogoutRequestSchema,
  shopLogoutResponseSchema,
  shopRefreshRequestSchema,
  shopRefreshResponseSchema,
  shopSessionMeResponseSchema,
  shopSystemStatusResponseSchema,
  type ShopAccountStatus,
  type ShopDevicePairResult,
  type ShopSystemStatus,
  type ShopUserSummary,
} from '@marketlens/api-contracts';

export interface ShopLoginInput {
  shopBaseUrl: string;
  identity: string;
  password: string;
  deviceUuid?: string;
  rememberDevice?: boolean;
}

export interface ShopSession {
  shopBaseUrl: string;
  userUuid: string;
  username: string;
  deviceUuid: string;
  token: string;
  refreshToken: string;
  permissions: string[];
  roles: string[];
  rememberDevice: boolean;
  accountStatus: ShopAccountStatus;
  passwordChangeRequired: boolean;
  user: ShopUserSummary;
  expiresAt: string;
}

function normalizeBaseUrl(baseUrl: string): string {
  return baseUrl.replace(/\/+$/, '');
}

async function parseResponse<T>(response: Response, schema: { parse(input: unknown): T }): Promise<T> {
  const payload = await response.json();

  if (!response.ok) {
    const parsedError = apiErrorResponseSchema.safeParse(payload);
    if (parsedError.success) {
      throw new Error(parsedError.data.message);
    }

    throw new Error(`Shop App returned ${response.status}`);
  }

  return schema.parse(payload);
}

export async function checkShopStatus(baseUrl: string): Promise<{ reachable: boolean; status: ShopSystemStatus }> {
  const response = await fetch(`${normalizeBaseUrl(baseUrl)}${apiBasePath}/system/status`, {
    headers: { Accept: 'application/json' },
  });

  const parsed = await parseResponse(response, shopSystemStatusResponseSchema);
  return {
    reachable: true,
    status: parsed.data,
  };
}

export async function pairDevice(shopBaseUrl: string, input: { deviceName: string; pairingCode: string }): Promise<ShopDevicePairResult> {
  const response = await fetch(`${normalizeBaseUrl(shopBaseUrl)}${apiBasePath}/devices/pair`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(shopDevicePairRequestSchema.parse(input)),
  });

  const payload = await response.json();

  if (!response.ok && response.status !== 202) {
    const parsedError = apiErrorResponseSchema.safeParse(payload);
    throw new Error(parsedError.success ? parsedError.data.message : `Device pairing failed with ${response.status}`);
  }

  return shopDevicePairResponseSchema.parse(payload).data;
}

export async function checkDeviceStatus(shopBaseUrl: string, deviceUuid: string): Promise<'pending' | 'paired' | 'authorized' | 'revoked' | 'unknown'> {
  const url = new URL(`${normalizeBaseUrl(shopBaseUrl)}${apiBasePath}/devices/status`);
  url.searchParams.set('deviceUuid', deviceUuid);

  const response = await fetch(url, {
    headers: { Accept: 'application/json' },
  });

  return (await parseResponse(response, shopDeviceStatusResponseSchema)).data.status;
}

export async function readAuthenticatedSession(shopBaseUrl: string, accessToken: string) {
  const response = await fetch(`${normalizeBaseUrl(shopBaseUrl)}${apiBasePath}/session/me`, {
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${accessToken}`,
    },
  });

  return (await parseResponse(response, shopSessionMeResponseSchema)).data;
}

export async function getCurrentUser(shopBaseUrl: string, accessToken: string): Promise<ShopUserSummary> {
  const response = await fetch(`${normalizeBaseUrl(shopBaseUrl)}${apiBasePath}/auth/me`, {
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${accessToken}`,
    },
  });

  const payload = await parseResponse(response, shopAuthMeResponseSchema);
  if (!payload.data.authenticated || !payload.data.user) {
    throw new Error('Authentication failed.');
  }

  return payload.data.user;
}

export async function readAuthState(shopBaseUrl: string, accessToken: string) {
  const response = await fetch(`${normalizeBaseUrl(shopBaseUrl)}${apiBasePath}/auth/me`, {
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${accessToken}`,
    },
  });

  return (await parseResponse(response, shopAuthMeResponseSchema)).data;
}

export async function refreshShopSession(shopBaseUrl: string, refreshToken: string) {
  const response = await fetch(`${normalizeBaseUrl(shopBaseUrl)}${apiBasePath}/auth/refresh`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(shopRefreshRequestSchema.parse({ refreshToken })),
  });

  return (await parseResponse(response, shopRefreshResponseSchema)).data;
}

export async function logoutFromShop(shopBaseUrl: string, token: string, refreshToken?: string): Promise<void> {
  const response = await fetch(`${normalizeBaseUrl(shopBaseUrl)}${apiBasePath}/auth/logout`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
    },
    body: JSON.stringify(shopLogoutRequestSchema.parse({ refreshToken })),
  });

  await parseResponse(response, shopLogoutResponseSchema);
}

export async function changeShopPassword(shopBaseUrl: string, token: string, oldPassword: string, newPassword: string) {
  const response = await fetch(`${normalizeBaseUrl(shopBaseUrl)}${apiBasePath}/auth/change-password`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
    },
    body: JSON.stringify(shopChangePasswordRequestSchema.parse({ oldPassword, newPassword })),
  });

  return (await parseResponse(response, shopChangePasswordResponseSchema)).data;
}

export async function loginToShop(input: ShopLoginInput): Promise<ShopSession> {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(shopLoginRequestSchema.parse({
      identity: input.identity,
      password: input.password,
      deviceUuid: input.deviceUuid,
      rememberDevice: input.rememberDevice ?? false,
    })),
  });

  const loginPayload = await parseResponse(response, shopLoginResponseSchema);
  const [sessionState, authState] = await Promise.all([
    readAuthenticatedSession(input.shopBaseUrl, loginPayload.data.accessToken),
    readAuthState(input.shopBaseUrl, loginPayload.data.accessToken),
  ]);

  if (!sessionState.authenticated || !sessionState.user || !input.deviceUuid) {
    throw new Error('Authentication failed.');
  }

  return {
    shopBaseUrl: normalizeBaseUrl(input.shopBaseUrl),
    userUuid: sessionState.user.uuid,
    username: sessionState.user.username,
    deviceUuid: input.deviceUuid,
    token: loginPayload.data.accessToken,
    refreshToken: loginPayload.data.refreshToken,
    permissions: sessionState.permissions,
    roles: sessionState.roles,
    rememberDevice: input.rememberDevice ?? false,
    accountStatus: authState.accountStatus,
    passwordChangeRequired: authState.passwordChangeRequired,
    user: sessionState.user,
    expiresAt: loginPayload.data.expiresAt,
  };
}
