import type { ShopDatabase } from '../database/sqlite';

export abstract class DrizzleRepository {
  protected constructor(protected readonly db: ShopDatabase) {}
}
