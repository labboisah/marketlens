import { randomUUID } from 'node:crypto';
import { stockMovements, stocks, warehouses } from '../database/schema';
import type { ShopDatabase } from '../database/sqlite';
import { DrizzleRepository } from './base-repository';

export type CreateWarehouseInput = {
  code: string;
  name: string;
  address?: string;
};

export type CreateStockInput = {
  productUuid: string;
  warehouseUuid: string;
  unitUuid: string;
  quantity: string;
  reservedQuantity?: string;
};

export type CreateStockMovementInput = {
  productUuid: string;
  fromWarehouseUuid?: string;
  toWarehouseUuid?: string;
  quantityBase: string;
  unitUuid: string;
  movementType: string;
  reference?: string;
  metadata?: Record<string, unknown>;
};

export class InventoryRepository extends DrizzleRepository {
  constructor(db: ShopDatabase) {
    super(db);
  }

  createWarehouse(input: CreateWarehouseInput) {
    const now = new Date().toISOString();
    const warehouse = {
      uuid: randomUUID(),
      code: input.code,
      name: input.name,
      address: input.address ?? null,
      createdAt: now,
      updatedAt: now,
    };

    this.db.insert(warehouses).values(warehouse).run();
    return warehouse;
  }

  listWarehouses(search?: string) {
    const query = this.db.select().from(warehouses);
    if (search && search.length > 0) {
      return query.where(warehouses.name.like(`%${search}%`).or(warehouses.code.like(`%${search}%`))).all();
    }

    return query.all();
  }

  createStock(input: CreateStockInput) {
    const now = new Date().toISOString();
    const stock = {
      uuid: randomUUID(),
      productUuid: input.productUuid,
      warehouseUuid: input.warehouseUuid,
      unitUuid: input.unitUuid,
      quantity: input.quantity,
      reservedQuantity: input.reservedQuantity ?? '0',
      lastUpdatedAt: now,
      createdAt: now,
      updatedAt: now,
    };

    this.db.insert(stocks).values(stock).run();
    return stock;
  }

  adjustStock(stockUuid: string, quantity: string, reservedQuantity?: string) {
    const now = new Date().toISOString();
    const updates: { quantity: string; lastUpdatedAt: string; updatedAt: string; reservedQuantity?: string | null } = {
      quantity,
      lastUpdatedAt: now,
      updatedAt: now,
      reservedQuantity: reservedQuantity ?? null,
    };

    this.db.update(stocks).set(updates).where(stocks.uuid.eq(stockUuid)).run();
  }

  listStocks(productUuid?: string, warehouseUuid?: string) {
    let query = this.db.select().from(stocks);
    if (productUuid) {
      query = query.where(stocks.productUuid.eq(productUuid));
    }
    if (warehouseUuid) {
      query = query.where(stocks.warehouseUuid.eq(warehouseUuid));
    }
    return query.all();
  }

  createStockMovement(input: CreateStockMovementInput) {
    const now = new Date().toISOString();
    const movement = {
      uuid: randomUUID(),
      productUuid: input.productUuid,
      fromWarehouseUuid: input.fromWarehouseUuid ?? null,
      toWarehouseUuid: input.toWarehouseUuid ?? null,
      quantityBase: input.quantityBase,
      unitUuid: input.unitUuid,
      movementType: input.movementType,
      reference: input.reference ?? null,
      metadataJson: input.metadata ? JSON.stringify(input.metadata) : null,
      createdAt: now,
    };

    this.db.insert(stockMovements).values(movement).run();
    return movement;
  }

  listStockMovements(productUuid?: string, warehouseUuid?: string) {
    let query = this.db.select().from(stockMovements);
    if (productUuid) {
      query = query.where(stockMovements.productUuid.eq(productUuid));
    }
    if (warehouseUuid) {
      query = query.where(stockMovements.fromWarehouseUuid.eq(warehouseUuid).or(stockMovements.toWarehouseUuid.eq(warehouseUuid)));
    }
    return query.all();
  }
}
