import { apiBasePath, apiSuccessResponseSchema } from '@marketlens/api-contracts';
import { z } from 'zod';

const inventoryProductSchema = z.object({
  uuid: z.string().uuid(),
  sku: z.string(),
  name: z.string(),
  description: z.string().nullable().optional(),
  reorderLevel: z.string().nullable().optional(),
  maxStock: z.string().nullable().optional(),
  active: z.string(),
});

const detailedStockSchema = z.object({
  uuid: z.string().uuid(),
  productUuid: z.string().uuid(),
  warehouseUuid: z.string().uuid(),
  unitUuid: z.string().uuid(),
  quantity: z.string(),
  reservedQuantity: z.string().nullable().optional(),
  lastUpdatedAt: z.string(),
  sku: z.string(),
  name: z.string(),
  reorderLevel: z.string().nullable().optional(),
  maxStock: z.string().nullable().optional(),
  warehouseName: z.string(),
  warehouseCode: z.string(),
  unitName: z.string().nullable().optional(),
  unitCode: z.string().nullable().optional(),
});

const stockMovementSchema = z.object({
  uuid: z.string().uuid(),
  productUuid: z.string().uuid(),
  fromWarehouseUuid: z.string().uuid().nullable().optional(),
  toWarehouseUuid: z.string().uuid().nullable().optional(),
  quantityBase: z.string(),
  unitUuid: z.string().uuid(),
  movementType: z.string(),
  reference: z.string().nullable().optional(),
  metadataJson: z.string().nullable().optional(),
  createdAt: z.string(),
});

const stockCountSessionSchema = z.object({
  uuid: z.string().uuid(),
  warehouseUuid: z.string().uuid(),
  warehouseName: z.string().optional(),
  status: z.string(),
  approvalState: z.string(),
  notes: z.string().nullable().optional(),
  createdByUserUuid: z.string().uuid().nullable().optional(),
  approvedByUserUuid: z.string().uuid().nullable().optional(),
  approvedAt: z.string().nullable().optional(),
  createdAt: z.string(),
  updatedAt: z.string(),
});

const stockCountItemSchema = z.object({
  uuid: z.string().uuid(),
  sessionUuid: z.string().uuid(),
  productUuid: z.string().uuid(),
  unitUuid: z.string().uuid(),
  barcode: z.string().nullable().optional(),
  systemQuantity: z.string(),
  countedQuantity: z.string(),
  varianceQuantity: z.string(),
  note: z.string().nullable().optional(),
  createdAt: z.string(),
  updatedAt: z.string(),
});

const stockCountSessionDetailSchema = z.object({
  uuid: z.string().uuid(),
  warehouseUuid: z.string().uuid(),
  status: z.string(),
  approvalState: z.string(),
  notes: z.string().nullable().optional(),
  createdByUserUuid: z.string().uuid().nullable().optional(),
  approvedByUserUuid: z.string().uuid().nullable().optional(),
  approvedAt: z.string().nullable().optional(),
  createdAt: z.string(),
  updatedAt: z.string(),
  items: z.array(stockCountItemSchema),
});

const stockAdjustmentSchema = z.object({
  uuid: z.string().uuid(),
  warehouseUuid: z.string().uuid(),
  warehouseName: z.string(),
  productUuid: z.string().uuid(),
  sku: z.string(),
  name: z.string(),
  unitUuid: z.string().uuid(),
  unitCode: z.string().nullable().optional(),
  adjustmentType: z.string(),
  quantity: z.string(),
  reason: z.string(),
  supportingNote: z.string().nullable().optional(),
  approvalRequested: z.string(),
  approvalState: z.string(),
  createdByUserUuid: z.string().uuid().nullable().optional(),
  approvedByUserUuid: z.string().uuid().nullable().optional(),
  approvedAt: z.string().nullable().optional(),
  createdAt: z.string(),
});

const stockTransferItemSchema = z.object({
  uuid: z.string().uuid(),
  transferUuid: z.string().uuid(),
  productUuid: z.string().uuid(),
  sku: z.string(),
  name: z.string(),
  unitUuid: z.string().uuid(),
  unitCode: z.string().nullable().optional(),
  quantity: z.string(),
  note: z.string().nullable().optional(),
  createdAt: z.string(),
});

const stockTransferSchema = z.object({
  uuid: z.string().uuid(),
  fromWarehouseUuid: z.string().uuid(),
  fromWarehouseName: z.string(),
  toWarehouseUuid: z.string().uuid(),
  toWarehouseName: z.string(),
  status: z.string(),
  approvalRequested: z.string(),
  approvalState: z.string(),
  reason: z.string().nullable().optional(),
  requestedByUserUuid: z.string().uuid().nullable().optional(),
  approvedByUserUuid: z.string().uuid().nullable().optional(),
  approvedAt: z.string().nullable().optional(),
  createdAt: z.string(),
  updatedAt: z.string(),
  items: z.array(stockTransferItemSchema),
});

const productBatchSchema = z.object({
  uuid: z.string().uuid(),
  productUuid: z.string().uuid(),
  batchNumber: z.string(),
  expiryAt: z.string().nullable().optional(),
  quantity: z.string(),
  createdAt: z.string(),
});

const serialNumberSchema = z.object({
  uuid: z.string().uuid(),
  productUuid: z.string().uuid(),
  serial: z.string(),
  batchUuid: z.string().uuid().nullable().optional(),
  sold: z.string(),
  createdAt: z.string(),
});

const warehouseSchema = z.object({
  uuid: z.string().uuid(),
  code: z.string(),
  name: z.string(),
  address: z.string().nullable().optional(),
});

const productsResponseSchema = apiSuccessResponseSchema(z.array(inventoryProductSchema));
const stockResponseSchema = apiSuccessResponseSchema(z.array(detailedStockSchema));
const movementsResponseSchema = apiSuccessResponseSchema(z.array(stockMovementSchema));
const stockCountSessionsResponseSchema = apiSuccessResponseSchema(z.array(stockCountSessionSchema));
const stockCountSessionDetailResponseSchema = apiSuccessResponseSchema(stockCountSessionDetailSchema);
const stockCountItemResponseSchema = apiSuccessResponseSchema(stockCountItemSchema);
const stockAdjustmentsResponseSchema = apiSuccessResponseSchema(z.array(stockAdjustmentSchema));
const stockTransfersResponseSchema = apiSuccessResponseSchema(z.array(stockTransferSchema));
const productBatchesResponseSchema = apiSuccessResponseSchema(z.array(productBatchSchema));
const serialNumbersResponseSchema = apiSuccessResponseSchema(z.array(serialNumberSchema));
const warehousesResponseSchema = apiSuccessResponseSchema(z.array(warehouseSchema));

function normalizeBaseUrl(baseUrl: string): string {
  return baseUrl.replace(/\/+$/, '');
}

function headers(accessToken: string, deviceUuid: string): HeadersInit {
  return {
    Accept: 'application/json',
    Authorization: `Bearer ${accessToken}`,
    'x-marketlens-device-uuid': deviceUuid,
  };
}

async function parseResponse<T>(response: Response, schema: { parse(input: unknown): T }): Promise<T> {
  const payload = await response.json();

  if (!response.ok) {
    throw new Error(payload?.message ?? `Shop App returned ${response.status}`);
  }

  return schema.parse(payload);
}

export type InventoryProduct = z.infer<typeof inventoryProductSchema>;
export type DetailedStock = z.infer<typeof detailedStockSchema>;
export type StockMovement = z.infer<typeof stockMovementSchema>;
export type StockCountSession = z.infer<typeof stockCountSessionSchema>;
export type StockCountSessionDetail = z.infer<typeof stockCountSessionDetailSchema>;
export type StockAdjustment = z.infer<typeof stockAdjustmentSchema>;
export type StockTransfer = z.infer<typeof stockTransferSchema>;

export async function listInventoryProducts(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/inventory/products`, {
    headers: headers(input.accessToken, input.deviceUuid),
  });
  return (await parseResponse(response, productsResponseSchema)).data;
}

export async function listWarehouses(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/inventory/warehouses`, {
    headers: headers(input.accessToken, input.deviceUuid),
  });
  return (await parseResponse(response, warehousesResponseSchema)).data;
}

export async function listDetailedStock(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; warehouseUuid?: string; status?: 'all' | 'low' | 'out' }) {
  const url = new URL(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/inventory/stocks/detailed`);
  if (input.warehouseUuid) url.searchParams.set('warehouseUuid', input.warehouseUuid);
  if (input.status) url.searchParams.set('status', input.status);

  const response = await fetch(url, { headers: headers(input.accessToken, input.deviceUuid) });
  return (await parseResponse(response, stockResponseSchema)).data;
}

export async function listStockMovements(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; movementType?: string }) {
  const url = new URL(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/inventory/stock-movements`);
  if (input.movementType) url.searchParams.set('movementType', input.movementType);

  const response = await fetch(url, { headers: headers(input.accessToken, input.deviceUuid) });
  return (await parseResponse(response, movementsResponseSchema)).data;
}

export async function listDamagedStock(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/inventory/damaged-stock`, {
    headers: headers(input.accessToken, input.deviceUuid),
  });
  return (await parseResponse(response, movementsResponseSchema)).data;
}

export async function listExpiredStock(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/inventory/expired-stock`, {
    headers: headers(input.accessToken, input.deviceUuid),
  });
  return (await parseResponse(response, apiSuccessResponseSchema(z.array(z.record(z.string(), z.unknown()))))).data;
}

export async function listProductBatches(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; productUuid?: string }) {
  const url = new URL(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/inventory/product-batches`);
  if (input.productUuid) url.searchParams.set('productUuid', input.productUuid);

  const response = await fetch(url, { headers: headers(input.accessToken, input.deviceUuid) });
  return (await parseResponse(response, productBatchesResponseSchema)).data;
}

export async function listSerialNumbers(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; productUuid?: string }) {
  const url = new URL(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/inventory/serial-numbers`);
  if (input.productUuid) url.searchParams.set('productUuid', input.productUuid);

  const response = await fetch(url, { headers: headers(input.accessToken, input.deviceUuid) });
  return (await parseResponse(response, serialNumbersResponseSchema)).data;
}

export async function createStockCountSession(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; warehouseUuid: string; notes?: string; createdByUserUuid?: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/inventory/stock-count-sessions`, {
    method: 'POST',
    headers: {
      ...headers(input.accessToken, input.deviceUuid),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(input),
  });

  return (await parseResponse(response, apiSuccessResponseSchema(stockCountSessionSchema))).data;
}

export async function listStockCountSessions(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/inventory/stock-count-sessions`, {
    headers: headers(input.accessToken, input.deviceUuid),
  });
  return (await parseResponse(response, stockCountSessionsResponseSchema)).data;
}

export async function getStockCountSession(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; sessionUuid: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/inventory/stock-count-sessions/${input.sessionUuid}`, {
    headers: headers(input.accessToken, input.deviceUuid),
  });
  return (await parseResponse(response, stockCountSessionDetailResponseSchema)).data;
}

export async function saveStockCountItem(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  sessionUuid: string;
  productUuid: string;
  unitUuid: string;
  barcode?: string;
  countedQuantity: string;
  note?: string;
}) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/inventory/stock-count-sessions/${input.sessionUuid}/items`, {
    method: 'POST',
    headers: {
      ...headers(input.accessToken, input.deviceUuid),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      productUuid: input.productUuid,
      unitUuid: input.unitUuid,
      barcode: input.barcode,
      countedQuantity: input.countedQuantity,
      note: input.note,
    }),
  });

  return (await parseResponse(response, stockCountItemResponseSchema)).data;
}

export async function submitStockCountSession(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; sessionUuid: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/inventory/stock-count-sessions/${input.sessionUuid}/submit`, {
    method: 'POST',
    headers: headers(input.accessToken, input.deviceUuid),
  });

  return (await parseResponse(response, apiSuccessResponseSchema(z.record(z.string(), z.unknown())))).data;
}

export async function approveStockCountSession(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; sessionUuid: string; approvedByUserUuid?: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/inventory/stock-count-sessions/${input.sessionUuid}/approve`, {
    method: 'POST',
    headers: {
      ...headers(input.accessToken, input.deviceUuid),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ approvedByUserUuid: input.approvedByUserUuid }),
  });

  return (await parseResponse(response, apiSuccessResponseSchema(z.record(z.string(), z.unknown())))).data;
}

export async function createStockAdjustment(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  warehouseUuid: string;
  productUuid: string;
  unitUuid: string;
  adjustmentType: 'increase' | 'decrease';
  quantity: string;
  reason: string;
  supportingNote?: string;
  approvalRequested?: boolean;
  createdByUserUuid?: string;
}) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/inventory/stock-adjustments`, {
    method: 'POST',
    headers: {
      ...headers(input.accessToken, input.deviceUuid),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(input),
  });

  return (await parseResponse(response, apiSuccessResponseSchema(z.record(z.string(), z.unknown())))).data;
}

export async function listStockAdjustments(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/inventory/stock-adjustments`, {
    headers: headers(input.accessToken, input.deviceUuid),
  });

  return (await parseResponse(response, stockAdjustmentsResponseSchema)).data;
}

export async function approveStockAdjustment(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; adjustmentUuid: string; approvedByUserUuid?: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/inventory/stock-adjustments/${input.adjustmentUuid}/approve`, {
    method: 'POST',
    headers: {
      ...headers(input.accessToken, input.deviceUuid),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ approvedByUserUuid: input.approvedByUserUuid }),
  });

  return (await parseResponse(response, apiSuccessResponseSchema(z.record(z.string(), z.unknown())))).data;
}

export async function createStockTransfer(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  fromWarehouseUuid: string;
  toWarehouseUuid: string;
  reason?: string;
  approvalRequested?: boolean;
  requestedByUserUuid?: string;
  items: Array<{ productUuid: string; unitUuid: string; quantity: string; note?: string }>;
}) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/inventory/stock-transfers`, {
    method: 'POST',
    headers: {
      ...headers(input.accessToken, input.deviceUuid),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(input),
  });

  return (await parseResponse(response, apiSuccessResponseSchema(z.record(z.string(), z.unknown())))).data;
}

export async function listStockTransfers(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/inventory/stock-transfers`, {
    headers: headers(input.accessToken, input.deviceUuid),
  });

  return (await parseResponse(response, stockTransfersResponseSchema)).data;
}

export async function submitStockTransfer(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; transferUuid: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/inventory/stock-transfers/${input.transferUuid}/submit`, {
    method: 'POST',
    headers: headers(input.accessToken, input.deviceUuid),
  });

  return (await parseResponse(response, apiSuccessResponseSchema(z.record(z.string(), z.unknown())))).data;
}

export async function approveStockTransfer(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; transferUuid: string; approvedByUserUuid?: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/inventory/stock-transfers/${input.transferUuid}/approve`, {
    method: 'POST',
    headers: {
      ...headers(input.accessToken, input.deviceUuid),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ approvedByUserUuid: input.approvedByUserUuid }),
  });

  return (await parseResponse(response, apiSuccessResponseSchema(z.record(z.string(), z.unknown())))).data;
}
