import { config as loadDotenv } from 'dotenv';
import { z } from 'zod';

loadDotenv();

const numberFromEnv = z.coerce.number().int().positive();
const booleanFromEnv = z
  .enum(['true', 'false'])
  .default('false')
  .transform((value) => value === 'true');

export const shopEnvironmentSchema = z.object({
  SHOP_APP_ENV: z.string().default('local'),
  SHOP_APP_LOG_LEVEL: z.string().default('info'),
  SHOP_LOCAL_API_HOST: z.string().default('127.0.0.1'),
  SHOP_LOCAL_API_PORT: numberFromEnv.default(4090),
  SHOP_LOCAL_API_PUBLIC_HOST: z.string().optional().default(''),
  SHOP_LOCAL_API_ALLOWED_ORIGINS: z.string().default('http://127.0.0.1:4091,http://localhost:4091'),
  SHOP_SOCKET_IO_PATH: z.string().default('/socket.io'),
  SHOP_DEVICE_NAME: z.string().default('MarketLens Development Shop'),
  SHOP_DEVICE_PAIRING_CODE: z.string().default('marketlens-pairing-code'),
  SHOP_INITIAL_ADMIN_USERNAME: z.string().default('admin'),
  SHOP_INITIAL_ADMIN_PASSWORD: z.string().default('password'),
  SHOP_ACTIVATION_STATUS: z.enum(['active', 'inactive']).default('inactive'),
  SHOP_APP_VERSION: z.string().default('0.0.0'),
  SHOP_BRANCH_UUID: z.string().uuid().default('00000000-0000-4000-8000-000000000001'),
  SHOP_TENANT_UUID: z.string().uuid().default('00000000-0000-4000-8000-000000000001'),
  SHOP_SQLITE_PATH: z.string().default('./data/shop-development.sqlite'),
  SHOP_TEST_SQLITE_PATH: z.string().default('./data/shop-test.sqlite'),
  SHOP_AUTO_START_ENABLED: booleanFromEnv,
  SHOP_BACKUP_RETENTION_DAYS: numberFromEnv.default(14),
  SHOP_JWT_SECRET: z.string().default('please-change-this-secret'),
  SHOP_JWT_ACCESS_EXPIRES_SECONDS: numberFromEnv.default(60),
  SHOP_JWT_REFRESH_EXPIRES_DAYS: numberFromEnv.default(7),
  PLATFORM_SYNC_ENABLED: booleanFromEnv,
  PLATFORM_SYNC_BASE_URL: z.string().url().default('http://localhost:8000/api'),
  PLATFORM_SYNC_DEVICE_TOKEN: z.string().optional().default(''),
  PLATFORM_SYNC_HMAC_SECRET: z.string().min(16).default('development-sync-secret-change-me'),
  PLATFORM_SYNC_PROTOCOL_VERSION: z.string().default('2026-08-04.v1'),
  PLATFORM_SYNC_INTERVAL_SECONDS: numberFromEnv.default(60),
  LOCAL_BACKUP_PATH: z.string().default('./data/backups'),
  LOCAL_EXPORT_PATH: z.string().default('./data/exports'),
});

export type ShopEnvironment = z.infer<typeof shopEnvironmentSchema>;

export function loadShopEnvironment(): ShopEnvironment {
  return shopEnvironmentSchema.parse(process.env);
}
