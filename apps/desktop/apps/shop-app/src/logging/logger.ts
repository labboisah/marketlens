import pino from 'pino';
import type { ShopEnvironment } from '../shared/environment';

export function createLogger(environment: Pick<ShopEnvironment, 'SHOP_APP_LOG_LEVEL' | 'SHOP_APP_ENV'>) {
  return pino({
    level: environment.SHOP_APP_LOG_LEVEL,
    base: {
      app: 'marketlens-shop-app',
      env: environment.SHOP_APP_ENV,
    },
    timestamp: pino.stdTimeFunctions.isoTime,
  });
}

export type ShopLogger = ReturnType<typeof createLogger>;
