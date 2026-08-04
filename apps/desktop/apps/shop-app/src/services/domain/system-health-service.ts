import type { ShopEnvironment } from '../../shared/environment';

export class SystemHealthService {
  constructor(private readonly environment: ShopEnvironment) {}

  status() {
    return {
      status: 'ok',
      service: 'marketlens-shop-app',
      mode: this.environment.SHOP_APP_ENV,
      activationStatus: this.environment.SHOP_ACTIVATION_STATUS,
      timestamp: new Date().toISOString(),
    };
  }
}
