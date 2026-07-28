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
  SHOP_BRANCH_UUID: z.string().uuid().default('00000000-0000-4000-8000-000000000001'),
  SHOP_TENANT_UUID: z.string().uuid().default('00000000-0000-4000-8000-000000000001'),
  SHOP_SQLITE_PATH: z.string().default('./data/shop-development.sqlite'),
  SHOP_TEST_SQLITE_PATH: z.string().default('./data/shop-test.sqlite'),
  SHOP_AUTO_START_ENABLED: booleanFromEnv,
  SHOP_BACKUP_RETENTION_DAYS: numberFromEnv.default(14),
  PLATFORM_SYNC_ENABLED: booleanFromEnv,
  PLATFORM_SYNC_BASE_URL: z.string().url().default('http://localhost:8000/api'),
  PLATFORM_SYNC_DEVICE_TOKEN: z.string().optional().default(''),
  PLATFORM_SYNC_INTERVAL_SECONDS: numberFromEnv.default(60),
  LOCAL_BACKUP_PATH: z.string().default('./data/backups'),
  LOCAL_EXPORT_PATH: z.string().default('./data/exports'),
});

export type ShopEnvironment = z.infer<typeof shopEnvironmentSchema>;

export function loadShopEnvironment(): ShopEnvironment {
  return shopEnvironmentSchema.parse(process.env);
}
