import { z } from 'zod';

export const apiVersion = 'v1' as const;
export const apiBasePath = '/api/v1' as const;
export const localApiVersion = apiVersion;

export const uuidSchema = z.string().uuid();
export const isoDateTimeSchema = z.string().datetime({ offset: true });
export const localDateSchema = z.string().regex(/^\d{4}-\d{2}-\d{2}$/);
export const currencyCodeSchema = z.string().regex(/^[A-Z]{3}$/);
export const moneyMinorSchema = z.object({
  amount_minor: z.number().int(),
  currency: currencyCodeSchema,
});
export const decimalStringSchema = z.string().regex(/^-?\d+(\.\d+)?$/);
export const quantitySchema = z.object({
  quantity: decimalStringSchema,
  unit_uuid: uuidSchema,
});

export const apiMetaSchema = z.record(z.string(), z.unknown()).default({});

export const apiSuccessResponseSchema = <T extends z.ZodTypeAny>(dataSchema: T) =>
  z.object({
    success: z.literal(true),
    message: z.string().default('Operation completed'),
    data: dataSchema,
    meta: apiMetaSchema,
  });

export const apiErrorCodeSchema = z.enum([
  'VALIDATION_ERROR',
  'AUTHENTICATION_REQUIRED',
  'ACCOUNT_DISABLED',
  'PASSWORD_CHANGE_REQUIRED',
  'AUTHORIZATION_DENIED',
  'NOT_FOUND',
  'CONFLICT',
  'IDEMPOTENCY_CONFLICT',
  'RATE_LIMITED',
  'SYNC_DEVICE_REVOKED',
  'SYNC_BATCH_REJECTED',
  'SYNC_RECORD_CONFLICT',
  'SERVER_ERROR',
  'SERVICE_UNAVAILABLE',
]);

export const apiErrorResponseSchema = z.object({
  success: z.literal(false),
  message: z.string(),
  code: apiErrorCodeSchema,
  errors: z.record(z.string(), z.array(z.string())).default({}),
  trace_id: uuidSchema,
});

export const cursorPaginationRequestSchema = z.object({
  cursor: z.string().optional(),
  limit: z.coerce.number().int().min(1).max(100).default(25),
});

export const cursorPaginationMetaSchema = z.object({
  pagination: z.object({
    limit: z.number().int().positive(),
    next_cursor: z.string().nullable(),
    previous_cursor: z.string().nullable(),
    has_more: z.boolean(),
  }),
});

export const filterQuerySchema = z.record(z.string(), z.union([z.string(), z.array(z.string())]));
export const sortQuerySchema = z
  .string()
  .regex(/^-?[a-zA-Z0-9_]+(,-?[a-zA-Z0-9_]+)*$/)
  .optional();

export const correlationHeadersSchema = z.object({
  'x-correlation-id': uuidSchema.optional(),
  'idempotency-key': z.string().min(1).max(200).optional(),
});

export const bearerTokenSchema = z.string().regex(/^Bearer\s.+$/);

export const clientToShopAuthHeadersSchema = correlationHeadersSchema.extend({
  authorization: bearerTokenSchema,
  'x-marketlens-device-uuid': uuidSchema,
});

export const shopToPlatformSyncHeadersSchema = correlationHeadersSchema.extend({
  'x-marketlens-device-uuid': uuidSchema,
  'x-marketlens-timestamp': isoDateTimeSchema,
  'x-marketlens-nonce': z.string().min(16).max(200),
  'x-marketlens-signature': z.string().min(32),
  'content-encoding': z.enum(['gzip', 'identity']).optional(),
});

export const monitoringAuthHeadersSchema = correlationHeadersSchema.extend({
  authorization: bearerTokenSchema,
});

export const clientRoleSchema = z.enum([
  'owner',
  'administrator',
  'manager',
  'cashier',
  'storekeeper',
  'accountant',
  'service_staff',
  'supervisor',
  'auditor',
]);

export const clientPreferencesSchema = z.object({
  shopBaseUrl: z.string().url(),
  lastDeviceName: z.string().min(1).max(120),
  preferredRole: clientRoleSchema,
});

export const shopSystemStatusSchema = z.object({
  applicationVersion: z.string(),
  databaseVersion: z.string(),
  businessUuid: uuidSchema.nullable(),
  businessName: z.string().nullable(),
  branchUuid: uuidSchema.nullable(),
  branchName: z.string().nullable(),
  shopDeviceUuid: uuidSchema.nullable(),
  shopDeviceName: z.string(),
  shopServerName: z.string(),
  serverIp: z.string(),
  serverPort: z.number().int().positive(),
  serverStatus: z.string(),
  shopActivationStatus: z.string(),
  subscriptionStatus: z.string(),
  platformConnectivity: z.string(),
  lastSynchronization: z.string().nullable(),
  pendingSyncRecordCount: z.number().int().nonnegative(),
  authorizedClientAppCount: z.number().int().nonnegative(),
  enabledModules: z.array(z.string()),
  disabledModules: z.array(z.string()),
});

export const shopSystemStatusResponseSchema = apiSuccessResponseSchema(shopSystemStatusSchema);

export const shopDevicePairRequestSchema = z.object({
  deviceName: z.string().min(1).max(120),
  pairingCode: z.string().min(1).max(128),
});

export const shopDevicePairResultSchema = z.object({
  paired: z.boolean(),
  reason: z.string().optional(),
  deviceUuid: uuidSchema.optional(),
});

export const shopDevicePairResponseSchema = apiSuccessResponseSchema(shopDevicePairResultSchema);

export const shopDeviceStatusResponseSchema = apiSuccessResponseSchema(
  z.object({
    status: z.enum(['pending', 'paired', 'authorized', 'revoked', 'unknown']),
  }),
);

export const shopLoginRequestSchema = z.object({
  identity: z.string().min(1),
  password: z.string().min(1),
  deviceUuid: uuidSchema.optional(),
  rememberDevice: z.boolean().default(false),
});

export const shopAccountStatusSchema = z.enum(['active', 'inactive', 'suspended', 'disabled']);

export const shopLoginResponseSchema = apiSuccessResponseSchema(
  z.object({
    authenticated: z.boolean(),
    accessToken: z.string().min(1),
    refreshToken: z.string().min(1),
    expiresAt: isoDateTimeSchema,
    accountStatus: shopAccountStatusSchema,
    passwordChangeRequired: z.boolean(),
  }),
);

export const shopRefreshRequestSchema = z.object({
  refreshToken: z.string().min(8),
});

export const shopRefreshResponseSchema = apiSuccessResponseSchema(
  z.object({
    accessToken: z.string().min(1),
    refreshToken: z.string().min(1),
    expiresAt: isoDateTimeSchema,
  }),
);

export const shopLogoutRequestSchema = z.object({
  refreshToken: z.string().min(8).optional(),
}).default({});

export const shopLogoutResponseSchema = apiSuccessResponseSchema(
  z.object({
    loggedOut: z.boolean(),
  }),
);

export const shopUserSummarySchema = z.object({
  uuid: uuidSchema,
  username: z.string().min(1),
  displayName: z.string().min(1),
});

export const shopAuthMeResponseSchema = apiSuccessResponseSchema(
  z.object({
    authenticated: z.boolean(),
    accountStatus: shopAccountStatusSchema,
    passwordChangeRequired: z.boolean(),
    user: shopUserSummarySchema.nullable(),
    permissions: z.array(z.string()),
    roles: z.array(z.string()),
  }),
);

export const shopChangePasswordRequestSchema = z.object({
  oldPassword: z.string().min(1),
  newPassword: z.string().min(8),
});

export const shopChangePasswordResponseSchema = apiSuccessResponseSchema(
  z.object({
    changed: z.boolean(),
  }),
);

export const shopSessionMeResponseSchema = apiSuccessResponseSchema(
  z.object({
    authenticated: z.boolean(),
    accountStatus: shopAccountStatusSchema.default('active'),
    passwordChangeRequired: z.boolean().default(false),
    user: shopUserSummarySchema.nullable(),
    permissions: z.array(z.string()),
    roles: z.array(z.string()).default([]),
  }),
);

export type MoneyMinor = z.infer<typeof moneyMinorSchema>;
export type Quantity = z.infer<typeof quantitySchema>;
export type ApiErrorCode = z.infer<typeof apiErrorCodeSchema>;
export type ApiErrorResponse = z.infer<typeof apiErrorResponseSchema>;
export type CursorPaginationRequest = z.infer<typeof cursorPaginationRequestSchema>;
export type CursorPaginationMeta = z.infer<typeof cursorPaginationMetaSchema>;
export type ClientToShopAuthHeaders = z.infer<typeof clientToShopAuthHeadersSchema>;
export type ShopToPlatformSyncHeaders = z.infer<typeof shopToPlatformSyncHeadersSchema>;
export type MonitoringAuthHeaders = z.infer<typeof monitoringAuthHeadersSchema>;
export type ClientRole = z.infer<typeof clientRoleSchema>;
export type ClientPreferences = z.infer<typeof clientPreferencesSchema>;
export type ShopSystemStatus = z.infer<typeof shopSystemStatusSchema>;
export type ShopDevicePairRequest = z.infer<typeof shopDevicePairRequestSchema>;
export type ShopDevicePairResult = z.infer<typeof shopDevicePairResultSchema>;
export type ShopUserSummary = z.infer<typeof shopUserSummarySchema>;
export type ShopAccountStatus = z.infer<typeof shopAccountStatusSchema>;
export type ApiSuccessResponse<T> = {
  success: true;
  message: string;
  data: T;
  meta: Record<string, unknown>;
};
export type ApiResponse<T> = ApiSuccessResponse<T> | ApiErrorResponse;
