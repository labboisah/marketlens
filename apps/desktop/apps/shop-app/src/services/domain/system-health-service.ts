import type { ShopEnvironment } from '../../shared/environment';

export class SystemHealthService {
  constructor(private readonly environment: ShopEnvironment) {}

  status() {
    return {
      status: 'ok',
      service: 'marketlens-shop-app',
      mode: this.environment.SHOP_APP_ENV,
      databasePath: this.environment.SHOP_SQLITE_PATH,
      syncEnabled: this.environment.PLATFORM_SYNC_ENABLED,
      timestamp: new Date().toISOString(),
    };
  }
}
