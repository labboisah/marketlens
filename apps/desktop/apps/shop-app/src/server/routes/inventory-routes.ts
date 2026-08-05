import { z } from 'zod';
import type { FastifyInstance } from 'fastify';
import type { ShopApplication } from '../../services/application/shop-application';
import { randomUUID } from 'node:crypto';

const warehouseSchema = z.object({
  code: z.string().min(1),
  name: z.string().min(1),
  address: z.string().optional(),
});

const stockSchema = z.object({
  productUuid: z.string().uuid(),
  warehouseUuid: z.string().uuid(),
  unitUuid: z.string().uuid(),
  quantity: z.string().min(1),
  reservedQuantity: z.string().optional(),
});

const stockMovementSchema = z.object({
  productUuid: z.string().uuid(),
  fromWarehouseUuid: z.string().uuid().optional(),
  toWarehouseUuid: z.string().uuid().optional(),
  quantityBase: z.string().min(1),
  unitUuid: z.string().uuid(),
  movementType: z.string().min(1),
  reference: z.string().optional(),
  metadata: z.record(z.any()).optional(),
});

const stockCountSessionSchema = z.object({
  warehouseUuid: z.string().uuid(),
  notes: z.string().optional(),
  createdByUserUuid: z.string().uuid().optional(),
});

const stockCountItemSchema = z.object({
  productUuid: z.string().uuid(),
  unitUuid: z.string().uuid(),
  barcode: z.string().optional(),
  countedQuantity: z.string().regex(/^-?\d+(\.\d+)?$/),
  note: z.string().optional(),
});

const approveStockCountSchema = z.object({
  approvedByUserUuid: z.string().uuid().optional(),
});

const adjustmentSchema = z.object({
  warehouseUuid: z.string().uuid(),
  productUuid: z.string().uuid(),
  unitUuid: z.string().uuid(),
  adjustmentType: z.enum(['increase', 'decrease']),
  quantity: z.string().regex(/^\d+(\.\d+)?$/),
  reason: z.string().min(1),
  supportingNote: z.string().optional(),
  approvalRequested: z.boolean().optional(),
  createdByUserUuid: z.string().uuid().optional(),
});

const approveAdjustmentSchema = z.object({
  approvedByUserUuid: z.string().uuid().optional(),
});

const transferSchema = z.object({
  fromWarehouseUuid: z.string().uuid(),
  toWarehouseUuid: z.string().uuid(),
  reason: z.string().optional(),
  approvalRequested: z.boolean().optional(),
  requestedByUserUuid: z.string().uuid().optional(),
  items: z.array(z.object({
    productUuid: z.string().uuid(),
    unitUuid: z.string().uuid(),
    quantity: z.string().regex(/^\d+(\.\d+)?$/),
    note: z.string().optional(),
  })).min(1),
});

const approveTransferSchema = z.object({
  approvedByUserUuid: z.string().uuid().optional(),
});

function toNumber(value: string | number | null | undefined): number {
  return Number(value ?? 0);
}

function asTextNumber(value: number): string {
  return String(value);
}

function findStock(sqlite: ShopApplication['database']['sqlite'], productUuid: string, warehouseUuid: string, unitUuid: string) {
  return sqlite.prepare('SELECT uuid, quantity FROM stocks WHERE product_uuid = ? AND warehouse_uuid = ? AND unit_uuid = ? LIMIT 1')
    .get(productUuid, warehouseUuid, unitUuid) as { uuid: string; quantity: string } | undefined;
}

function upsertStock(sqlite: ShopApplication['database']['sqlite'], input: { productUuid: string; warehouseUuid: string; unitUuid: string; nextQuantity: number }) {
  const existing = findStock(sqlite, input.productUuid, input.warehouseUuid, input.unitUuid);
  const now = new Date().toISOString();
  if (existing) {
    sqlite.prepare('UPDATE stocks SET quantity = ?, last_updated_at = ?, updated_at = ? WHERE uuid = ?')
      .run(asTextNumber(input.nextQuantity), now, now, existing.uuid);
    return existing.uuid;
  }

  const uuid = randomUUID();
  sqlite.prepare('INSERT INTO stocks (uuid, product_uuid, warehouse_uuid, unit_uuid, quantity, reserved_quantity, last_updated_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
    .run(uuid, input.productUuid, input.warehouseUuid, input.unitUuid, asTextNumber(input.nextQuantity), '0', now, now, now);
  return uuid;
}

function recordMovement(sqlite: ShopApplication['database']['sqlite'], input: {
  productUuid: string;
  unitUuid: string;
  quantityBase: string;
  movementType: string;
  fromWarehouseUuid?: string;
  toWarehouseUuid?: string;
  reference?: string;
  metadata?: Record<string, unknown>;
}) {
  sqlite.prepare('INSERT INTO stock_movements (uuid, product_uuid, from_warehouse_uuid, to_warehouse_uuid, quantity_base, unit_uuid, movement_type, reference, metadata_json, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
    .run(
      randomUUID(),
      input.productUuid,
      input.fromWarehouseUuid ?? null,
      input.toWarehouseUuid ?? null,
      input.quantityBase,
      input.unitUuid,
      input.movementType,
      input.reference ?? null,
      input.metadata ? JSON.stringify(input.metadata) : null,
      new Date().toISOString(),
    );
}

export async function registerInventoryRoutes(server: FastifyInstance, app: ShopApplication): Promise<void> {
  server.get('/api/v1/inventory/products', async () => {
    const data = app.database.sqlite.prepare(`
      SELECT p.uuid, p.sku, p.name, p.description, p.reorder_level AS reorderLevel, p.max_stock AS maxStock, p.active
      FROM products p
      ORDER BY p.name ASC
    `).all();
    return { success: true, data, meta: {} };
  });

  server.post('/api/v1/inventory/warehouses', async (request) => {
    const input = warehouseSchema.parse(request.body);
    const warehouse = app.services.inventory.createWarehouse(input);
    return { success: true, data: warehouse, meta: {} };
  });

  server.get('/api/v1/inventory/warehouses', async (request) => {
    const search = typeof request.query.search === 'string' ? request.query.search : undefined;
    const warehouses = app.services.inventory.listWarehouses(search);
    return { success: true, data: warehouses, meta: {} };
  });

  server.post('/api/v1/inventory/stocks', async (request) => {
    const input = stockSchema.parse(request.body);
    const stock = app.services.inventory.createStock(input);
    return { success: true, data: stock, meta: {} };
  });

  server.get('/api/v1/inventory/stocks', async (request) => {
    const productUuid = typeof request.query.productUuid === 'string' ? request.query.productUuid : undefined;
    const warehouseUuid = typeof request.query.warehouseUuid === 'string' ? request.query.warehouseUuid : undefined;
    const stocks = app.services.inventory.listStocks(productUuid, warehouseUuid);
    return { success: true, data: stocks, meta: {} };
  });

  server.get('/api/v1/inventory/stocks/detailed', async (request) => {
    const warehouseUuid = typeof request.query.warehouseUuid === 'string' ? request.query.warehouseUuid : '';
    const status = typeof request.query.status === 'string' ? request.query.status : 'all';
    const rows = app.database.sqlite.prepare(`
      SELECT
        s.uuid,
        s.product_uuid AS productUuid,
        s.warehouse_uuid AS warehouseUuid,
        s.unit_uuid AS unitUuid,
        s.quantity,
        s.reserved_quantity AS reservedQuantity,
        s.last_updated_at AS lastUpdatedAt,
        p.sku,
        p.name,
        p.reorder_level AS reorderLevel,
        p.max_stock AS maxStock,
        w.name AS warehouseName,
        w.code AS warehouseCode,
        u.name AS unitName,
        u.code AS unitCode
      FROM stocks s
      JOIN products p ON p.uuid = s.product_uuid
      JOIN warehouses w ON w.uuid = s.warehouse_uuid
      LEFT JOIN units u ON u.uuid = s.unit_uuid
      WHERE (? = '' OR s.warehouse_uuid = ?)
      ORDER BY p.name ASC
    `).all(warehouseUuid, warehouseUuid) as Array<Record<string, unknown>>;

    const filtered = rows.filter((row) => {
      const quantity = toNumber(row.quantity as string);
      const reorder = toNumber(row.reorderLevel as string | null);
      if (status === 'low') return quantity > 0 && reorder > 0 && quantity <= reorder;
      if (status === 'out') return quantity <= 0;
      return true;
    });

    return { success: true, data: filtered, meta: {} };
  });

  server.post('/api/v1/inventory/stock-movements', async (request) => {
    const input = stockMovementSchema.parse(request.body);
    const movement = app.services.inventory.createStockMovement(input);
    return { success: true, data: movement, meta: {} };
  });

  server.get('/api/v1/inventory/stock-movements', async (request) => {
    const productUuid = typeof request.query.productUuid === 'string' ? request.query.productUuid : undefined;
    const warehouseUuid = typeof request.query.warehouseUuid === 'string' ? request.query.warehouseUuid : undefined;
    const movementType = typeof request.query.movementType === 'string' ? request.query.movementType : undefined;
    const rows = app.services.inventory.listStockMovements(productUuid, warehouseUuid) as Array<Record<string, unknown>>;
    const movements = movementType ? rows.filter((row) => String(row.movementType) === movementType) : rows;
    return { success: true, data: movements, meta: {} };
  });

  server.get('/api/v1/inventory/damaged-stock', async () => {
    const data = app.database.sqlite.prepare(`
      SELECT
        sm.uuid,
        sm.product_uuid AS productUuid,
        p.sku,
        p.name,
        sm.quantity_base AS quantity,
        sm.unit_uuid AS unitUuid,
        u.code AS unitCode,
        u.name AS unitName,
        sm.reference,
        sm.metadata_json AS metadataJson,
        sm.created_at AS createdAt
      FROM stock_movements sm
      JOIN products p ON p.uuid = sm.product_uuid
      LEFT JOIN units u ON u.uuid = sm.unit_uuid
      WHERE sm.movement_type IN ('damage', 'damaged_stock', 'expiry_disposal', 'stock_adjustment_decrease')
      ORDER BY sm.created_at DESC
    `).all();
    return { success: true, data, meta: {} };
  });

  server.get('/api/v1/inventory/expired-stock', async () => {
    const data = app.database.sqlite.prepare(`
      SELECT
        pb.uuid,
        pb.product_uuid AS productUuid,
        p.sku,
        p.name,
        pb.batch_number AS batchNumber,
        pb.expiry_at AS expiryAt,
        pb.quantity
      FROM product_batches pb
      JOIN products p ON p.uuid = pb.product_uuid
      WHERE pb.expiry_at IS NOT NULL
        AND date(pb.expiry_at) < date('now')
        AND CAST(pb.quantity AS REAL) > 0
      ORDER BY pb.expiry_at ASC
    `).all();
    return { success: true, data, meta: {} };
  });

  server.get('/api/v1/inventory/product-batches', async (request) => {
    const productUuid = typeof request.query.productUuid === 'string' ? request.query.productUuid : undefined;
    const data = app.services.products.listBatches(productUuid);
    return { success: true, data, meta: {} };
  });

  server.get('/api/v1/inventory/serial-numbers', async (request) => {
    const productUuid = typeof request.query.productUuid === 'string' ? request.query.productUuid : undefined;
    const data = app.services.products.listSerialNumbers(productUuid);
    return { success: true, data, meta: {} };
  });

  server.post('/api/v1/inventory/stock-count-sessions', async (request) => {
    const input = stockCountSessionSchema.parse(request.body);
    const now = new Date().toISOString();
    const session = {
      uuid: randomUUID(),
      warehouseUuid: input.warehouseUuid,
      status: 'draft',
      approvalState: 'draft',
      notes: input.notes ?? null,
      createdByUserUuid: input.createdByUserUuid ?? null,
      approvedByUserUuid: null,
      approvedAt: null,
      createdAt: now,
      updatedAt: now,
    };
    app.database.sqlite.prepare('INSERT INTO stock_count_sessions (uuid, warehouse_uuid, status, approval_state, notes, created_by_user_uuid, approved_by_user_uuid, approved_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
      .run(session.uuid, session.warehouseUuid, session.status, session.approvalState, session.notes, session.createdByUserUuid, null, null, now, now);
    return { success: true, data: session, meta: {} };
  });

  server.get('/api/v1/inventory/stock-count-sessions', async () => {
    const sessions = app.database.sqlite.prepare(`
      SELECT
        scs.uuid,
        scs.warehouse_uuid AS warehouseUuid,
        w.name AS warehouseName,
        scs.status,
        scs.approval_state AS approvalState,
        scs.notes,
        scs.created_by_user_uuid AS createdByUserUuid,
        scs.approved_by_user_uuid AS approvedByUserUuid,
        scs.approved_at AS approvedAt,
        scs.created_at AS createdAt,
        scs.updated_at AS updatedAt
      FROM stock_count_sessions scs
      JOIN warehouses w ON w.uuid = scs.warehouse_uuid
      ORDER BY scs.created_at DESC
    `).all();
    return { success: true, data: sessions, meta: {} };
  });

  server.get('/api/v1/inventory/stock-count-sessions/:sessionUuid', async (request) => {
    const { sessionUuid } = request.params as { sessionUuid: string };
    const session = app.database.sqlite.prepare('SELECT uuid, warehouse_uuid AS warehouseUuid, status, approval_state AS approvalState, notes, created_by_user_uuid AS createdByUserUuid, approved_by_user_uuid AS approvedByUserUuid, approved_at AS approvedAt, created_at AS createdAt, updated_at AS updatedAt FROM stock_count_sessions WHERE uuid = ?')
      .get(sessionUuid) as Record<string, unknown> | undefined;
    if (!session) throw new Error('Stock count session not found');

    const items = app.database.sqlite.prepare('SELECT uuid, session_uuid AS sessionUuid, product_uuid AS productUuid, unit_uuid AS unitUuid, barcode, system_quantity AS systemQuantity, counted_quantity AS countedQuantity, variance_quantity AS varianceQuantity, note, created_at AS createdAt, updated_at AS updatedAt FROM stock_count_items WHERE session_uuid = ? ORDER BY id ASC')
      .all(sessionUuid);
    return { success: true, data: { ...session, items }, meta: {} };
  });

  server.post('/api/v1/inventory/stock-count-sessions/:sessionUuid/items', async (request) => {
    const { sessionUuid } = request.params as { sessionUuid: string };
    const input = stockCountItemSchema.parse(request.body);
    const session = app.database.sqlite.prepare('SELECT uuid, warehouse_uuid AS warehouseUuid, status FROM stock_count_sessions WHERE uuid = ?')
      .get(sessionUuid) as { uuid: string; warehouseUuid: string; status: string } | undefined;
    if (!session) throw new Error('Stock count session not found');
    if (session.status !== 'draft') throw new Error('Only draft sessions can be edited');

    const stock = findStock(app.database.sqlite, input.productUuid, session.warehouseUuid, input.unitUuid);
    const systemQuantity = toNumber(stock?.quantity ?? '0');
    const countedQuantity = toNumber(input.countedQuantity);
    const varianceQuantity = countedQuantity - systemQuantity;
    const now = new Date().toISOString();

    const existing = app.database.sqlite.prepare('SELECT uuid FROM stock_count_items WHERE session_uuid = ? AND product_uuid = ? AND unit_uuid = ? LIMIT 1')
      .get(sessionUuid, input.productUuid, input.unitUuid) as { uuid: string } | undefined;

    if (existing) {
      app.database.sqlite.prepare('UPDATE stock_count_items SET barcode = ?, system_quantity = ?, counted_quantity = ?, variance_quantity = ?, note = ?, updated_at = ? WHERE uuid = ?')
        .run(input.barcode ?? null, asTextNumber(systemQuantity), asTextNumber(countedQuantity), asTextNumber(varianceQuantity), input.note ?? null, now, existing.uuid);
      return {
        success: true,
        data: { uuid: existing.uuid, sessionUuid, ...input, systemQuantity: asTextNumber(systemQuantity), varianceQuantity: asTextNumber(varianceQuantity), updatedAt: now },
        meta: {},
      };
    }

    const uuid = randomUUID();
    app.database.sqlite.prepare('INSERT INTO stock_count_items (uuid, session_uuid, product_uuid, unit_uuid, barcode, system_quantity, counted_quantity, variance_quantity, note, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
      .run(uuid, sessionUuid, input.productUuid, input.unitUuid, input.barcode ?? null, asTextNumber(systemQuantity), asTextNumber(countedQuantity), asTextNumber(varianceQuantity), input.note ?? null, now, now);

    return {
      success: true,
      data: { uuid, sessionUuid, ...input, systemQuantity: asTextNumber(systemQuantity), varianceQuantity: asTextNumber(varianceQuantity), createdAt: now, updatedAt: now },
      meta: {},
    };
  });

  server.post('/api/v1/inventory/stock-count-sessions/:sessionUuid/submit', async (request) => {
    const { sessionUuid } = request.params as { sessionUuid: string };
    const session = app.database.sqlite.prepare('SELECT uuid, status FROM stock_count_sessions WHERE uuid = ?')
      .get(sessionUuid) as { uuid: string; status: string } | undefined;
    if (!session) throw new Error('Stock count session not found');
    if (session.status !== 'draft') throw new Error('Only draft sessions can be submitted');

    const now = new Date().toISOString();
    app.database.sqlite.prepare("UPDATE stock_count_sessions SET status = 'submitted', approval_state = 'pending', updated_at = ? WHERE uuid = ?")
      .run(now, sessionUuid);
    return { success: true, data: { sessionUuid, status: 'submitted', approvalState: 'pending', updatedAt: now }, meta: {} };
  });

  server.post('/api/v1/inventory/stock-count-sessions/:sessionUuid/approve', async (request) => {
    const { sessionUuid } = request.params as { sessionUuid: string };
    const input = approveStockCountSchema.parse(request.body);
    const session = app.database.sqlite.prepare('SELECT uuid, warehouse_uuid AS warehouseUuid, status FROM stock_count_sessions WHERE uuid = ?')
      .get(sessionUuid) as { uuid: string; warehouseUuid: string; status: string } | undefined;
    if (!session) throw new Error('Stock count session not found');
    if (session.status !== 'submitted') throw new Error('Only submitted sessions can be approved');

    const items = app.database.sqlite.prepare('SELECT product_uuid AS productUuid, unit_uuid AS unitUuid, counted_quantity AS countedQuantity, variance_quantity AS varianceQuantity FROM stock_count_items WHERE session_uuid = ?')
      .all(sessionUuid) as Array<{ productUuid: string; unitUuid: string; countedQuantity: string; varianceQuantity: string }>;

    const now = new Date().toISOString();
    app.database.sqlite.transaction(() => {
      for (const item of items) {
        const current = findStock(app.database.sqlite, item.productUuid, session.warehouseUuid, item.unitUuid);
        const nextQuantity = toNumber(item.countedQuantity);
        if (nextQuantity < 0) throw new Error('Counted quantity cannot be negative');
        upsertStock(app.database.sqlite, { productUuid: item.productUuid, warehouseUuid: session.warehouseUuid, unitUuid: item.unitUuid, nextQuantity });

        const variance = toNumber(item.varianceQuantity);
        if (variance !== 0) {
          recordMovement(app.database.sqlite, {
            productUuid: item.productUuid,
            unitUuid: item.unitUuid,
            quantityBase: asTextNumber(Math.abs(variance)),
            movementType: variance > 0 ? 'stock_count_increase' : 'stock_count_decrease',
            toWarehouseUuid: variance > 0 ? session.warehouseUuid : undefined,
            fromWarehouseUuid: variance < 0 ? session.warehouseUuid : undefined,
            reference: sessionUuid,
            metadata: { source: 'stock_count_approval', previousQuantity: current?.quantity ?? '0', countedQuantity: item.countedQuantity },
          });
        }
      }

      app.database.sqlite.prepare("UPDATE stock_count_sessions SET status = 'approved', approval_state = 'approved', approved_by_user_uuid = ?, approved_at = ?, updated_at = ? WHERE uuid = ?")
        .run(input.approvedByUserUuid ?? null, now, now, sessionUuid);
    })();

    return { success: true, data: { sessionUuid, status: 'approved', approvalState: 'approved', approvedAt: now }, meta: {} };
  });

  server.post('/api/v1/inventory/stock-adjustments', async (request) => {
    const input = adjustmentSchema.parse(request.body);
    const now = new Date().toISOString();
    const adjustment = {
      uuid: randomUUID(),
      warehouseUuid: input.warehouseUuid,
      productUuid: input.productUuid,
      unitUuid: input.unitUuid,
      adjustmentType: input.adjustmentType,
      quantity: input.quantity,
      reason: input.reason,
      supportingNote: input.supportingNote ?? null,
      approvalRequested: input.approvalRequested ? '1' : '0',
      approvalState: input.approvalRequested ? 'pending' : 'approved',
      createdByUserUuid: input.createdByUserUuid ?? null,
      approvedByUserUuid: input.approvalRequested ? null : (input.createdByUserUuid ?? null),
      approvedAt: input.approvalRequested ? null : now,
      createdAt: now,
    };

    app.database.sqlite.prepare('INSERT INTO stock_adjustments (uuid, warehouse_uuid, product_uuid, unit_uuid, adjustment_type, quantity, reason, supporting_note, approval_requested, approval_state, created_by_user_uuid, approved_by_user_uuid, approved_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
      .run(
        adjustment.uuid,
        adjustment.warehouseUuid,
        adjustment.productUuid,
        adjustment.unitUuid,
        adjustment.adjustmentType,
        adjustment.quantity,
        adjustment.reason,
        adjustment.supportingNote,
        adjustment.approvalRequested,
        adjustment.approvalState,
        adjustment.createdByUserUuid,
        adjustment.approvedByUserUuid,
        adjustment.approvedAt,
        adjustment.createdAt,
      );

    if (!input.approvalRequested) {
      const current = findStock(app.database.sqlite, input.productUuid, input.warehouseUuid, input.unitUuid);
      const currentQuantity = toNumber(current?.quantity ?? '0');
      const quantity = toNumber(input.quantity);
      const nextQuantity = input.adjustmentType === 'increase' ? currentQuantity + quantity : currentQuantity - quantity;
      if (nextQuantity < 0) throw new Error('Adjustment would create negative stock');

      upsertStock(app.database.sqlite, {
        productUuid: input.productUuid,
        warehouseUuid: input.warehouseUuid,
        unitUuid: input.unitUuid,
        nextQuantity,
      });

      recordMovement(app.database.sqlite, {
        productUuid: input.productUuid,
        unitUuid: input.unitUuid,
        quantityBase: input.quantity,
        movementType: input.adjustmentType === 'increase' ? 'stock_adjustment_increase' : 'stock_adjustment_decrease',
        toWarehouseUuid: input.adjustmentType === 'increase' ? input.warehouseUuid : undefined,
        fromWarehouseUuid: input.adjustmentType === 'decrease' ? input.warehouseUuid : undefined,
        reference: adjustment.uuid,
        metadata: { reason: input.reason, supportingNote: input.supportingNote ?? null },
      });
    }

    return { success: true, data: adjustment, meta: {} };
  });

  server.get('/api/v1/inventory/stock-adjustments', async () => {
    const data = app.database.sqlite.prepare(`
      SELECT
        sa.uuid,
        sa.warehouse_uuid AS warehouseUuid,
        w.name AS warehouseName,
        sa.product_uuid AS productUuid,
        p.sku,
        p.name,
        sa.unit_uuid AS unitUuid,
        u.code AS unitCode,
        sa.adjustment_type AS adjustmentType,
        sa.quantity,
        sa.reason,
        sa.supporting_note AS supportingNote,
        sa.approval_requested AS approvalRequested,
        sa.approval_state AS approvalState,
        sa.created_by_user_uuid AS createdByUserUuid,
        sa.approved_by_user_uuid AS approvedByUserUuid,
        sa.approved_at AS approvedAt,
        sa.created_at AS createdAt
      FROM stock_adjustments sa
      JOIN products p ON p.uuid = sa.product_uuid
      JOIN warehouses w ON w.uuid = sa.warehouse_uuid
      LEFT JOIN units u ON u.uuid = sa.unit_uuid
      ORDER BY sa.created_at DESC
    `).all();
    return { success: true, data, meta: {} };
  });

  server.post('/api/v1/inventory/stock-adjustments/:adjustmentUuid/approve', async (request) => {
    const { adjustmentUuid } = request.params as { adjustmentUuid: string };
    const input = approveAdjustmentSchema.parse(request.body);
    const adjustment = app.database.sqlite.prepare('SELECT uuid, warehouse_uuid AS warehouseUuid, product_uuid AS productUuid, unit_uuid AS unitUuid, adjustment_type AS adjustmentType, quantity, reason, supporting_note AS supportingNote, approval_state AS approvalState FROM stock_adjustments WHERE uuid = ?')
      .get(adjustmentUuid) as {
        uuid: string;
        warehouseUuid: string;
        productUuid: string;
        unitUuid: string;
        adjustmentType: 'increase' | 'decrease';
        quantity: string;
        reason: string;
        supportingNote: string | null;
        approvalState: string;
      } | undefined;
    if (!adjustment) throw new Error('Stock adjustment not found');
    if (adjustment.approvalState !== 'pending') throw new Error('Only pending adjustments can be approved');

    const current = findStock(app.database.sqlite, adjustment.productUuid, adjustment.warehouseUuid, adjustment.unitUuid);
    const currentQuantity = toNumber(current?.quantity ?? '0');
    const quantity = toNumber(adjustment.quantity);
    const nextQuantity = adjustment.adjustmentType === 'increase' ? currentQuantity + quantity : currentQuantity - quantity;
    if (nextQuantity < 0) throw new Error('Adjustment would create negative stock');

    upsertStock(app.database.sqlite, {
      productUuid: adjustment.productUuid,
      warehouseUuid: adjustment.warehouseUuid,
      unitUuid: adjustment.unitUuid,
      nextQuantity,
    });

    recordMovement(app.database.sqlite, {
      productUuid: adjustment.productUuid,
      unitUuid: adjustment.unitUuid,
      quantityBase: adjustment.quantity,
      movementType: adjustment.adjustmentType === 'increase' ? 'stock_adjustment_increase' : 'stock_adjustment_decrease',
      toWarehouseUuid: adjustment.adjustmentType === 'increase' ? adjustment.warehouseUuid : undefined,
      fromWarehouseUuid: adjustment.adjustmentType === 'decrease' ? adjustment.warehouseUuid : undefined,
      reference: adjustment.uuid,
      metadata: { reason: adjustment.reason, supportingNote: adjustment.supportingNote },
    });

    const now = new Date().toISOString();
    app.database.sqlite.prepare("UPDATE stock_adjustments SET approval_state = 'approved', approved_by_user_uuid = ?, approved_at = ? WHERE uuid = ?")
      .run(input.approvedByUserUuid ?? null, now, adjustmentUuid);
    return { success: true, data: { adjustmentUuid, approvalState: 'approved', approvedAt: now }, meta: {} };
  });

  server.post('/api/v1/inventory/stock-transfers', async (request) => {
    const input = transferSchema.parse(request.body);
    if (input.fromWarehouseUuid === input.toWarehouseUuid) throw new Error('Transfer source and destination cannot match');

    const now = new Date().toISOString();
    const transfer = {
      uuid: randomUUID(),
      fromWarehouseUuid: input.fromWarehouseUuid,
      toWarehouseUuid: input.toWarehouseUuid,
      status: 'draft',
      approvalRequested: input.approvalRequested ? '1' : '0',
      approvalState: input.approvalRequested ? 'pending' : 'approved',
      reason: input.reason ?? null,
      requestedByUserUuid: input.requestedByUserUuid ?? null,
      approvedByUserUuid: null,
      approvedAt: null,
      createdAt: now,
      updatedAt: now,
    };

    app.database.sqlite.transaction(() => {
      app.database.sqlite.prepare('INSERT INTO stock_transfers (uuid, from_warehouse_uuid, to_warehouse_uuid, status, approval_requested, approval_state, reason, requested_by_user_uuid, approved_by_user_uuid, approved_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        .run(
          transfer.uuid,
          transfer.fromWarehouseUuid,
          transfer.toWarehouseUuid,
          transfer.status,
          transfer.approvalRequested,
          transfer.approvalState,
          transfer.reason,
          transfer.requestedByUserUuid,
          transfer.approvedByUserUuid,
          transfer.approvedAt,
          transfer.createdAt,
          transfer.updatedAt,
        );

      for (const item of input.items) {
        app.database.sqlite.prepare('INSERT INTO stock_transfer_items (uuid, transfer_uuid, product_uuid, unit_uuid, quantity, note, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
          .run(randomUUID(), transfer.uuid, item.productUuid, item.unitUuid, item.quantity, item.note ?? null, now);
      }
    })();

    return { success: true, data: transfer, meta: {} };
  });

  server.get('/api/v1/inventory/stock-transfers', async () => {
    const transfers = app.database.sqlite.prepare(`
      SELECT
        st.uuid,
        st.from_warehouse_uuid AS fromWarehouseUuid,
        fw.name AS fromWarehouseName,
        st.to_warehouse_uuid AS toWarehouseUuid,
        tw.name AS toWarehouseName,
        st.status,
        st.approval_requested AS approvalRequested,
        st.approval_state AS approvalState,
        st.reason,
        st.requested_by_user_uuid AS requestedByUserUuid,
        st.approved_by_user_uuid AS approvedByUserUuid,
        st.approved_at AS approvedAt,
        st.created_at AS createdAt,
        st.updated_at AS updatedAt
      FROM stock_transfers st
      JOIN warehouses fw ON fw.uuid = st.from_warehouse_uuid
      JOIN warehouses tw ON tw.uuid = st.to_warehouse_uuid
      ORDER BY st.created_at DESC
    `).all() as Array<Record<string, unknown>>;

    const transferItemsStatement = app.database.sqlite.prepare(`
      SELECT
        sti.uuid,
        sti.transfer_uuid AS transferUuid,
        sti.product_uuid AS productUuid,
        p.sku,
        p.name,
        sti.unit_uuid AS unitUuid,
        u.code AS unitCode,
        sti.quantity,
        sti.note,
        sti.created_at AS createdAt
      FROM stock_transfer_items sti
      JOIN products p ON p.uuid = sti.product_uuid
      LEFT JOIN units u ON u.uuid = sti.unit_uuid
      WHERE sti.transfer_uuid = ?
      ORDER BY sti.id ASC
    `);

    const data = transfers.map((transfer) => ({
      ...transfer,
      items: transferItemsStatement.all(transfer.uuid),
    }));

    return { success: true, data, meta: {} };
  });

  server.post('/api/v1/inventory/stock-transfers/:transferUuid/submit', async (request) => {
    const { transferUuid } = request.params as { transferUuid: string };
    const transfer = app.database.sqlite.prepare('SELECT uuid, status FROM stock_transfers WHERE uuid = ?')
      .get(transferUuid) as { uuid: string; status: string } | undefined;
    if (!transfer) throw new Error('Stock transfer not found');
    if (transfer.status !== 'draft') throw new Error('Only draft transfers can be submitted');

    const now = new Date().toISOString();
    app.database.sqlite.prepare("UPDATE stock_transfers SET status = 'submitted', approval_state = 'pending', updated_at = ? WHERE uuid = ?")
      .run(now, transferUuid);
    return { success: true, data: { transferUuid, status: 'submitted', approvalState: 'pending', updatedAt: now }, meta: {} };
  });

  server.post('/api/v1/inventory/stock-transfers/:transferUuid/approve', async (request) => {
    const { transferUuid } = request.params as { transferUuid: string };
    const input = approveTransferSchema.parse(request.body);
    const transfer = app.database.sqlite.prepare('SELECT uuid, from_warehouse_uuid AS fromWarehouseUuid, to_warehouse_uuid AS toWarehouseUuid, status FROM stock_transfers WHERE uuid = ?')
      .get(transferUuid) as { uuid: string; fromWarehouseUuid: string; toWarehouseUuid: string; status: string } | undefined;
    if (!transfer) throw new Error('Stock transfer not found');
    if (transfer.status !== 'submitted' && transfer.status !== 'draft') throw new Error('Transfer is not pending approval');

    const items = app.database.sqlite.prepare('SELECT product_uuid AS productUuid, unit_uuid AS unitUuid, quantity FROM stock_transfer_items WHERE transfer_uuid = ?')
      .all(transferUuid) as Array<{ productUuid: string; unitUuid: string; quantity: string }>;

    app.database.sqlite.transaction(() => {
      for (const item of items) {
        const sourceStock = findStock(app.database.sqlite, item.productUuid, transfer.fromWarehouseUuid, item.unitUuid);
        const currentSourceQuantity = toNumber(sourceStock?.quantity ?? '0');
        const quantity = toNumber(item.quantity);
        if (quantity <= 0) throw new Error('Transfer quantity must be greater than zero');
        if (currentSourceQuantity < quantity) throw new Error('Insufficient source stock for transfer');

        upsertStock(app.database.sqlite, {
          productUuid: item.productUuid,
          warehouseUuid: transfer.fromWarehouseUuid,
          unitUuid: item.unitUuid,
          nextQuantity: currentSourceQuantity - quantity,
        });

        const destinationStock = findStock(app.database.sqlite, item.productUuid, transfer.toWarehouseUuid, item.unitUuid);
        const destinationQuantity = toNumber(destinationStock?.quantity ?? '0');
        upsertStock(app.database.sqlite, {
          productUuid: item.productUuid,
          warehouseUuid: transfer.toWarehouseUuid,
          unitUuid: item.unitUuid,
          nextQuantity: destinationQuantity + quantity,
        });

        recordMovement(app.database.sqlite, {
          productUuid: item.productUuid,
          unitUuid: item.unitUuid,
          quantityBase: item.quantity,
          movementType: 'stock_transfer',
          fromWarehouseUuid: transfer.fromWarehouseUuid,
          toWarehouseUuid: transfer.toWarehouseUuid,
          reference: transferUuid,
        });
      }

      const now = new Date().toISOString();
      app.database.sqlite.prepare("UPDATE stock_transfers SET status = 'approved', approval_state = 'approved', approved_by_user_uuid = ?, approved_at = ?, updated_at = ? WHERE uuid = ?")
        .run(input.approvedByUserUuid ?? null, now, now, transferUuid);
    })();

    return { success: true, data: { transferUuid, status: 'approved', approvalState: 'approved' }, meta: {} };
  });
}
