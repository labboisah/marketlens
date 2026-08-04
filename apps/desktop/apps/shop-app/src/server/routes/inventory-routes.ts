import { z } from 'zod';
import type { FastifyInstance } from 'fastify';
import type { ShopApplication } from '../../services/application/shop-application';

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

export async function registerInventoryRoutes(server: FastifyInstance, app: ShopApplication): Promise<void> {
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

  server.post('/api/v1/inventory/stock-movements', async (request) => {
    const input = stockMovementSchema.parse(request.body);
    const movement = app.services.inventory.createStockMovement(input);
    return { success: true, data: movement, meta: {} };
  });

  server.get('/api/v1/inventory/stock-movements', async (request) => {
    const productUuid = typeof request.query.productUuid === 'string' ? request.query.productUuid : undefined;
    const warehouseUuid = typeof request.query.warehouseUuid === 'string' ? request.query.warehouseUuid : undefined;
    const movements = app.services.inventory.listStockMovements(productUuid, warehouseUuid);
    return { success: true, data: movements, meta: {} };
  });
}
