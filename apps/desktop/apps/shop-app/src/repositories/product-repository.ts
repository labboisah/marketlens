import { randomUUID } from 'node:crypto';
import {
  brands,
  productBarcodes,
  productBatches,
  productCategories,
  productPriceHistories,
  productPrices,
  productSerialNumbers,
  productUnits,
  products,
  units,
} from '../database/schema';
import type { ShopDatabase } from '../database/sqlite';
import { DrizzleRepository } from './base-repository';

export type CreateCategoryInput = {
  name: string;
  slug?: string;
  parentUuid?: string;
  description?: string;
};

export type CreateBrandInput = {
  name: string;
  description?: string;
};

export type CreateUnitInput = {
  code: string;
  name: string;
  isBase?: boolean;
};

export type CreateProductInput = {
  sku: string;
  name: string;
  description?: string;
  categoryUuid?: string;
  brandUuid?: string;
  baseUnitUuid?: string;
  defaultPurchaseUnitUuid?: string;
  defaultSalesUnitUuid?: string;
  isService?: boolean;
  isNonStock?: boolean;
  active?: boolean;
  minSellingPrice?: string;
  reorderLevel?: string;
  maxStock?: string;
  costPrice?: string;
  retailPrice?: string;
  wholesalePrice?: string;
  imagesJson?: string | null;
};

export type CreateProductUnitInput = {
  productUuid: string;
  unitUuid: string;
  toBaseMultiplier: string;
};

export type CreateProductBatchInput = {
  productUuid: string;
  batchNumber: string;
  expiryAt?: string;
  quantity: string;
};

export type CreateProductSerialInput = {
  productUuid: string;
  serial: string;
  batchUuid?: string;
  sold?: boolean;
};

export class ProductRepository extends DrizzleRepository {
  constructor(db: ShopDatabase) {
    super(db);
  }

  createCategory(input: CreateCategoryInput) {
    const now = new Date().toISOString();
    const category = {
      uuid: randomUUID(),
      name: input.name,
      slug: input.slug ?? null,
      parentUuid: input.parentUuid ?? null,
      description: input.description ?? null,
      createdAt: now,
      updatedAt: now,
    };

    this.db.insert(productCategories).values(category).run();
    return category;
  }

  listCategories() {
    return this.db.select().from(productCategories).all();
  }

  createBrand(input: CreateBrandInput) {
    const now = new Date().toISOString();
    const brand = {
      uuid: randomUUID(),
      name: input.name,
      description: input.description ?? null,
      createdAt: now,
      updatedAt: now,
    };

    this.db.insert(brands).values(brand).run();
    return brand;
  }

  listBrands() {
    return this.db.select().from(brands).all();
  }

  createUnit(input: CreateUnitInput) {
    const now = new Date().toISOString();
    const unit = {
      uuid: randomUUID(),
      code: input.code,
      name: input.name,
      isBase: input.isBase ? '1' : '0',
      createdAt: now,
      updatedAt: now,
    };

    this.db.insert(units).values(unit).run();
    return unit;
  }

  listUnits() {
    return this.db.select().from(units).all();
  }

  createProduct(input: CreateProductInput) {
    const now = new Date().toISOString();
    const product = {
      uuid: randomUUID(),
      sku: input.sku,
      name: input.name,
      description: input.description ?? null,
      categoryUuid: input.categoryUuid ?? null,
      brandUuid: input.brandUuid ?? null,
      baseUnitUuid: input.baseUnitUuid ?? null,
      defaultPurchaseUnitUuid: input.defaultPurchaseUnitUuid ?? null,
      defaultSalesUnitUuid: input.defaultSalesUnitUuid ?? null,
      isService: input.isService ? '1' : '0',
      isNonStock: input.isNonStock ? '1' : '0',
      active: input.active ? '1' : '0',
      minSellingPrice: input.minSellingPrice ?? null,
      reorderLevel: input.reorderLevel ?? null,
      maxStock: input.maxStock ?? null,
      costPrice: input.costPrice ?? null,
      retailPrice: input.retailPrice ?? null,
      wholesalePrice: input.wholesalePrice ?? null,
      imagesJson: input.imagesJson ?? null,
      createdAt: now,
      updatedAt: now,
    };

    this.db.insert(products).values(product).run();
    return product;
  }

  findByUuid(uuid: string) {
    return this.db.select().from(products).where(products.uuid.eq(uuid)).get();
  }

  findBySku(sku: string) {
    return this.db.select().from(products).where(products.sku.eq(sku)).get();
  }

  listProducts(search?: string) {
    const query = this.db.select().from(products);
    if (search && search.length > 0) {
      return query
        .where(products.name.like(`%${search}%`).or(products.sku.like(`%${search}%`)))
        .all();
    }

    return query.all();
  }

  createProductUnit(input: CreateProductUnitInput) {
    const now = new Date().toISOString();
    const record = {
      uuid: randomUUID(),
      productUuid: input.productUuid,
      unitUuid: input.unitUuid,
      toBaseMultiplier: input.toBaseMultiplier,
      createdAt: now,
    };

    this.db.insert(productUnits).values(record).run();
    return record;
  }

  listProductUnits(productUuid?: string) {
    const query = this.db.select().from(productUnits);
    if (productUuid) {
      return query.where(productUnits.productUuid.eq(productUuid)).all();
    }

    return query.all();
  }

  createBarcode(productUuid: string, barcode: string) {
    const now = new Date().toISOString();
    const record = {
      uuid: randomUUID(),
      productUuid,
      barcode,
      createdAt: now,
    };

    this.db.insert(productBarcodes).values(record).run();
    return record;
  }

  findByBarcode(barcode: string) {
    return this.db.select().from(productBarcodes).where(productBarcodes.barcode.eq(barcode)).get();
  }

  createPrice(productUuid: string, priceType: string, amount: string, currency?: string, effectiveFrom?: string) {
    const now = new Date().toISOString();
    const price = {
      uuid: randomUUID(),
      productUuid,
      priceType,
      amount,
      currency: currency ?? null,
      effectiveFrom: effectiveFrom ?? null,
      createdAt: now,
    };

    this.db.insert(productPrices).values(price).run();
    return price;
  }

  listPrices(productUuid: string) {
    return this.db.select().from(productPrices).where(productPrices.productUuid.eq(productUuid)).all();
  }

  recordPriceHistory(productUuid: string, oldAmount: string | null, newAmount: string, changedBy?: string) {
    const now = new Date().toISOString();
    const history = {
      uuid: randomUUID(),
      productUuid,
      oldAmount,
      newAmount,
      changedAt: now,
      changedBy: changedBy ?? null,
    };

    this.db.insert(productPriceHistories).values(history).run();
    return history;
  }

  createBatch(input: CreateProductBatchInput) {
    const now = new Date().toISOString();
    const batch = {
      uuid: randomUUID(),
      productUuid: input.productUuid,
      batchNumber: input.batchNumber,
      expiryAt: input.expiryAt ?? null,
      quantity: input.quantity,
      createdAt: now,
    };

    this.db.insert(productBatches).values(batch).run();
    return batch;
  }

  listBatches(productUuid?: string) {
    const query = this.db.select().from(productBatches);
    if (productUuid) {
      return query.where(productBatches.productUuid.eq(productUuid)).all();
    }
    return query.all();
  }

  createSerialNumber(input: CreateProductSerialInput) {
    const now = new Date().toISOString();
    const serial = {
      uuid: randomUUID(),
      productUuid: input.productUuid,
      serial: input.serial,
      batchUuid: input.batchUuid ?? null,
      sold: input.sold ? '1' : '0',
      createdAt: now,
    };

    this.db.insert(productSerialNumbers).values(serial).run();
    return serial;
  }

  listSerialNumbers(productUuid?: string) {
    const query = this.db.select().from(productSerialNumbers);
    if (productUuid) {
      return query.where(productSerialNumbers.productUuid.eq(productUuid)).all();
    }
    return query.all();
  }
}
