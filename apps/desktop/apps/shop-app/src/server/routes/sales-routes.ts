import { z } from 'zod';
import type { FastifyInstance } from 'fastify';
import type { ShopApplication } from '../../services/application/shop-application';

const money = z.string().regex(/^\d+(\.\d+)?$/);
export async function registerSalesRoutes(server: FastifyInstance, app: ShopApplication): Promise<void> {
  const refundMethodSchema = z.enum(['cash', 'bank_transfer', 'pos_terminal', 'mobile_money', 'credit_note']);

  server.get('/api/v1/payment-methods', async () => {
    const data = app.database.sqlite.prepare("SELECT uuid, code, name, type FROM payment_methods WHERE active = '1' ORDER BY name ASC").all();
    return { success: true, data, meta: {} };
  });

  server.post('/api/v1/payment-methods', async request => ({ success: true, data: await app.services.sales.createPaymentMethod(z.object({ code: z.string().min(1), name: z.string().min(1), type: z.enum(['cash', 'bank_transfer', 'pos_terminal', 'mobile_money', 'credit']) }).parse(request.body)), meta: {} }));
  server.get('/api/v1/pos/catalog', async (request) => {
    const warehouseUuid = typeof request.query.warehouseUuid === 'string' ? request.query.warehouseUuid : undefined;
    const limit = Math.max(20, Math.min(250, Number(typeof request.query.limit === 'string' ? request.query.limit : 120) || 120));

    const warehouses = app.database.sqlite.prepare("SELECT uuid, code, name, address FROM warehouses ORDER BY name ASC").all() as Array<{ uuid: string; code: string; name: string; address: string | null }>;
    const categories = app.database.sqlite.prepare("SELECT uuid, name, slug FROM product_categories ORDER BY name ASC").all() as Array<{ uuid: string; name: string; slug: string | null }>;
    const brands = app.database.sqlite.prepare("SELECT uuid, name, description FROM brands ORDER BY name ASC").all() as Array<{ uuid: string; name: string; description: string | null }>;
    const paymentMethods = app.database.sqlite.prepare("SELECT uuid, code, name, type FROM payment_methods WHERE active = '1' ORDER BY name ASC").all() as Array<{ uuid: string; code: string; name: string; type: string }>;

    const productRows = app.database.sqlite.prepare(`
      SELECT
        p.uuid,
        p.sku,
        p.name,
        p.description,
        p.category_uuid AS categoryUuid,
        c.name AS categoryName,
        p.brand_uuid AS brandUuid,
        b.name AS brandName,
        p.active,
        p.is_service AS isService,
        p.is_non_stock AS isNonStock,
        p.min_selling_price AS minSellingPrice,
        p.retail_price AS retailPrice,
        p.wholesale_price AS wholesalePrice,
        p.default_sales_unit_uuid AS defaultSalesUnitUuid,
        u.name AS defaultSalesUnitName,
        u.code AS defaultSalesUnitCode
      FROM products p
      LEFT JOIN product_categories c ON c.uuid = p.category_uuid
      LEFT JOIN brands b ON b.uuid = p.brand_uuid
      LEFT JOIN units u ON u.uuid = p.default_sales_unit_uuid
      WHERE p.active = '1'
      ORDER BY p.name ASC
    `).all() as Array<Record<string, string | null>>;

    const productUuids = productRows.map((row) => String(row.uuid));
    const productBarcodes = productUuids.length > 0
      ? app.database.sqlite.prepare(`SELECT product_uuid AS productUuid, barcode FROM product_barcodes WHERE product_uuid IN (${productUuids.map(() => '?').join(', ')}) ORDER BY barcode ASC`).all(...productUuids) as Array<{ productUuid: string; barcode: string }>
      : [];
    const productUnits = productUuids.length > 0
      ? app.database.sqlite.prepare(`
        SELECT pu.product_uuid AS productUuid, pu.unit_uuid AS unitUuid, pu.to_base_multiplier AS toBaseMultiplier, u.name AS unitName, u.code AS unitCode, u.is_base AS isBase
        FROM product_units pu
        JOIN units u ON u.uuid = pu.unit_uuid
        WHERE pu.product_uuid IN (${productUuids.map(() => '?').join(', ')})
        ORDER BY pu.product_uuid ASC, u.name ASC
      `).all(...productUuids) as Array<{ productUuid: string; unitUuid: string; toBaseMultiplier: string; unitName: string; unitCode: string; isBase: string }>
      : [];
    const productStockRows = warehouseUuid
      ? app.database.sqlite.prepare(`SELECT product_uuid AS productUuid, unit_uuid AS unitUuid, quantity FROM stocks WHERE warehouse_uuid = ? AND product_uuid IN (${productUuids.map(() => '?').join(', ')})`).all(warehouseUuid, ...productUuids) as Array<{ productUuid: string; unitUuid: string; quantity: string }>
      : [];

    const recentRows = app.database.sqlite.prepare(`
      SELECT s.product_uuid AS productUuid, MAX(s.created_at) AS lastSoldAt, COUNT(*) AS soldCount
      FROM sale_items s
      JOIN sales sa ON sa.uuid = s.sale_uuid
      WHERE sa.status = 'completed'
      GROUP BY s.product_uuid
      ORDER BY lastSoldAt DESC
      LIMIT ?
    `).all(limit) as Array<{ productUuid: string; lastSoldAt: string; soldCount: number }>;

    const frequentRows = app.database.sqlite.prepare(`
      SELECT s.product_uuid AS productUuid, COUNT(*) AS soldCount, MAX(s.created_at) AS lastSoldAt
      FROM sale_items s
      JOIN sales sa ON sa.uuid = s.sale_uuid
      WHERE sa.status = 'completed' AND sa.created_at >= datetime('now', '-30 days')
      GROUP BY s.product_uuid
      ORDER BY soldCount DESC, lastSoldAt DESC
      LIMIT ?
    `).all(limit) as Array<{ productUuid: string; soldCount: number; lastSoldAt: string }>;

    const stockByProductAndUnit = new Map<string, Array<{ unitUuid: string; quantity: string }>>();
    for (const row of productStockRows) {
      const items = stockByProductAndUnit.get(row.productUuid) ?? [];
      items.push({ unitUuid: row.unitUuid, quantity: row.quantity });
      stockByProductAndUnit.set(row.productUuid, items);
    }

    const barcodeByProduct = new Map<string, string[]>();
    for (const barcode of productBarcodes) {
      const items = barcodeByProduct.get(barcode.productUuid) ?? [];
      items.push(barcode.barcode);
      barcodeByProduct.set(barcode.productUuid, items);
    }

    const unitsByProduct = new Map<string, Array<{ unitUuid: string; unitName: string; unitCode: string; toBaseMultiplier: string; isBase: boolean }>>();
    for (const unit of productUnits) {
      const items = unitsByProduct.get(unit.productUuid) ?? [];
      items.push({ unitUuid: unit.unitUuid, unitName: unit.unitName, unitCode: unit.unitCode, toBaseMultiplier: unit.toBaseMultiplier, isBase: unit.isBase === '1' });
      unitsByProduct.set(unit.productUuid, items);
    }

    const recentByProduct = new Map(recentRows.map((row) => [row.productUuid, row]));
    const frequentByProduct = new Map(frequentRows.map((row) => [row.productUuid, row]));

    const data = productRows.map((row) => ({
      uuid: row.uuid,
      sku: row.sku,
      name: row.name,
      description: row.description,
      categoryUuid: row.categoryUuid,
      categoryName: row.categoryName,
      brandUuid: row.brandUuid,
      brandName: row.brandName,
      active: row.active,
      isService: row.isService,
      isNonStock: row.isNonStock,
      minSellingPrice: row.minSellingPrice,
      retailPrice: row.retailPrice,
      wholesalePrice: row.wholesalePrice,
      defaultSalesUnitUuid: row.defaultSalesUnitUuid,
      defaultSalesUnitName: row.defaultSalesUnitName,
      defaultSalesUnitCode: row.defaultSalesUnitCode,
      barcodes: barcodeByProduct.get(String(row.uuid)) ?? [],
      units: unitsByProduct.get(String(row.uuid)) ?? [],
      stockByUnit: stockByProductAndUnit.get(String(row.uuid)) ?? [],
      lastSoldAt: recentByProduct.get(String(row.uuid))?.lastSoldAt ?? null,
      soldCount: frequentByProduct.get(String(row.uuid))?.soldCount ?? 0,
    })).slice(0, limit);

    return { success: true, data: { warehouses, categories, brands, paymentMethods, products: data }, meta: {} };
  });

  server.post('/api/v1/pos/sales', async request => ({ success: true, data: await app.services.sales.completeSale(z.object({ idempotencyKey: z.string().min(1), shiftUuid: z.string().uuid(), warehouseUuid: z.string().uuid(), customerUuid: z.string().uuid().optional(), discountAmount: money.optional(), receiptFormat: z.enum(['thermal', 'a4']).optional(), items: z.array(z.object({ productUuid: z.string().uuid(), unitUuid: z.string().uuid(), quantity: money, unitPrice: money })).min(1), payments: z.array(z.object({ paymentMethodUuid: z.string().uuid(), amount: money, amountTendered: money.optional(), reference: z.string().optional(), transferVerified: z.boolean().optional(), creditAmount: money.optional(), customerCreditSelection: z.string().optional() })).min(1) }).parse(request.body)), meta: {} }));
  server.get('/api/v1/receipts/:receiptUuid', async request => {
    const { receiptUuid } = request.params as { receiptUuid: string };
    return { success: true, data: app.services.sales.getReceiptDetails(receiptUuid), meta: {} };
  });
  server.get('/api/v1/pos/sales/search', async request => {
    const query = z.object({
      saleNumber: z.string().optional(),
      receiptNumber: z.string().optional(),
      customer: z.string().optional(),
      date: z.string().optional(),
      cashier: z.string().optional(),
      product: z.string().optional(),
      limit: z.coerce.number().int().min(1).max(100).optional(),
    }).parse(request.query);
    return { success: true, data: app.services.sales.searchSales(query), meta: {} };
  });
  server.post('/api/v1/pos/returns', async request => ({
    success: true,
    data: await app.services.sales.createSaleReturn(z.object({
      saleUuid: z.string().uuid(),
      returnType: z.enum(['full', 'partial']),
      reason: z.string().min(1),
      items: z.array(z.object({ saleItemUuid: z.string().uuid(), quantity: money })).optional(),
      refundMethod: refundMethodSchema,
      approvalRequested: z.boolean().optional(),
      approvedByUserUuid: z.string().uuid().optional(),
      requestedByUserUuid: z.string().uuid().optional(),
    }).parse(request.body)),
    meta: {},
  }));
  server.get('/api/v1/pos/returns/:saleReturnUuid', async request => {
    const { saleReturnUuid } = request.params as { saleReturnUuid: string };
    return { success: true, data: app.services.sales.getSaleReturnDetails(saleReturnUuid), meta: {} };
  });
  server.post('/api/v1/pos/sales/:saleUuid/cancel', async request => {
    const { saleUuid } = request.params as { saleUuid: string };
    const input = z.object({
      reason: z.string().min(1),
      refundMethod: refundMethodSchema,
      approvalRequested: z.boolean().optional(),
      approvedByUserUuid: z.string().uuid().optional(),
      refundState: z.enum(['pending', 'refunded']).optional(),
      requestedByUserUuid: z.string().uuid().optional(),
    }).parse(request.body);
    return { success: true, data: await app.services.sales.cancelSale({ saleUuid, ...input }), meta: {} };
  });
  server.post('/api/v1/receipts/:receiptUuid/reprints', async request => { const { receiptUuid } = request.params as { receiptUuid: string }; return { success: true, data: await app.services.sales.reprintReceipt(receiptUuid, z.object({ reason: z.string().min(1), approvedByUserUuid: z.string().uuid().optional() }).parse(request.body)), meta: {} }; });
}