import { apiBasePath, apiSuccessResponseSchema } from '@marketlens/api-contracts';
import { z } from 'zod';

const supplierSchema = z.object({
  uuid: z.string().uuid(),
  code: z.string(),
  name: z.string(),
  contactName: z.string().nullable().optional(),
  contactPhone: z.string().nullable().optional(),
  contactEmail: z.string().nullable().optional(),
  address: z.string().nullable().optional(),
  preferred: z.string(),
  taxNumber: z.string().nullable().optional(),
  paymentTerms: z.string().nullable().optional(),
  createdAt: z.string(),
  updatedAt: z.string(),
});

const supplierBalanceSchema = z.object({
  supplierUuid: z.string().uuid(),
  code: z.string(),
  name: z.string(),
  contactPhone: z.string().nullable().optional(),
  contactEmail: z.string().nullable().optional(),
  currentBalance: z.string(),
});

const purchaseItemSchema = z.object({
  uuid: z.string().uuid(),
  purchaseUuid: z.string().uuid(),
  productUuid: z.string().uuid(),
  purchaseUnitUuid: z.string().uuid(),
  quantity: z.string(),
  unitCost: z.string(),
  batchNumber: z.string().nullable().optional(),
  expiryDate: z.string().nullable().optional(),
  serialNumbersJson: z.string().nullable().optional(),
  baseUnitMultiplier: z.string(),
  receivedQuantity: z.string(),
  createdAt: z.string(),
  updatedAt: z.string(),
});

const purchasePaymentSchema = z.object({
  uuid: z.string().uuid(),
  purchaseUuid: z.string().uuid(),
  supplierUuid: z.string().uuid(),
  amount: z.string(),
  reference: z.string().nullable().optional(),
  paymentMethod: z.string().nullable().optional(),
  createdAt: z.string(),
});

const purchaseReturnSchema = z.object({
  uuid: z.string().uuid(),
  purchaseUuid: z.string().uuid(),
  supplierUuid: z.string().uuid(),
  warehouseUuid: z.string().uuid(),
  productUuid: z.string().uuid(),
  unitUuid: z.string().uuid(),
  quantity: z.string(),
  unitCost: z.string(),
  amount: z.string(),
  reason: z.string().nullable().optional(),
  note: z.string().nullable().optional(),
  createdAt: z.string(),
});

const purchaseListSchema = z.object({
  uuid: z.string().uuid(),
  supplierUuid: z.string().uuid(),
  supplierName: z.string(),
  invoiceNumber: z.string().nullable().optional(),
  invoiceDate: z.string().nullable().optional(),
  status: z.string(),
  currency: z.string(),
  transportCost: z.string(),
  loadingCost: z.string(),
  otherLandedCost: z.string(),
  tax: z.string(),
  discount: z.string(),
  notes: z.string().nullable().optional(),
  totalAmount: z.string(),
  amountPaid: z.string(),
  balanceAmount: z.string(),
  createdAt: z.string(),
  updatedAt: z.string(),
});

const purchaseDetailsSchema = purchaseListSchema.extend({
  items: z.array(purchaseItemSchema),
  payments: z.array(purchasePaymentSchema),
  returns: z.array(purchaseReturnSchema),
});

const supplierStatementSchema = z.object({
  supplier: z.object({
    uuid: z.string().uuid(),
    code: z.string(),
    name: z.string(),
    contactPhone: z.string().nullable().optional(),
  }),
  account: z.object({
    currentBalance: z.string(),
  }),
  transactions: z.array(z.object({
    uuid: z.string(),
    occurredAt: z.string(),
    entryType: z.enum(['purchase', 'payment', 'return']),
    debit: z.string(),
    credit: z.string(),
    reference: z.string(),
    runningBalance: z.string(),
  })),
});

const productSchema = z.object({
  uuid: z.string().uuid(),
  sku: z.string(),
  name: z.string(),
  baseUnitUuid: z.string().uuid().nullable().optional(),
  defaultPurchaseUnitUuid: z.string().uuid().nullable().optional(),
});

const unitSchema = z.object({
  uuid: z.string().uuid(),
  code: z.string(),
  name: z.string(),
  isBase: z.string(),
});

const warehouseSchema = z.object({
  uuid: z.string().uuid(),
  code: z.string(),
  name: z.string(),
  address: z.string().nullable().optional(),
});

const suppliersResponseSchema = apiSuccessResponseSchema(z.array(supplierSchema));
const supplierBalanceResponseSchema = apiSuccessResponseSchema(z.array(supplierBalanceSchema));
const purchaseListResponseSchema = apiSuccessResponseSchema(z.array(purchaseListSchema));
const purchaseDetailsResponseSchema = apiSuccessResponseSchema(purchaseDetailsSchema);
const purchasePaymentsResponseSchema = apiSuccessResponseSchema(z.array(purchasePaymentSchema));
const purchaseReturnsResponseSchema = apiSuccessResponseSchema(z.array(purchaseReturnSchema));
const supplierStatementResponseSchema = apiSuccessResponseSchema(supplierStatementSchema);
const productsResponseSchema = apiSuccessResponseSchema(z.array(productSchema));
const unitsResponseSchema = apiSuccessResponseSchema(z.array(unitSchema));
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

export type PurchaseListRow = z.infer<typeof purchaseListSchema>;
export type PurchaseDetails = z.infer<typeof purchaseDetailsSchema>;
export type SupplierStatement = z.infer<typeof supplierStatementSchema>;

export async function searchSuppliers(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; search?: string }) {
  const url = new URL(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/suppliers`);
  if (input.search?.trim()) url.searchParams.set('search', input.search.trim());
  const response = await fetch(url, { headers: headers(input.accessToken, input.deviceUuid) });
  return (await parseResponse(response, suppliersResponseSchema)).data;
}

export async function createSupplierProfile(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  code: string;
  name: string;
  contactName?: string;
  contactPhone?: string;
  contactEmail?: string;
  address?: string;
  preferred?: boolean;
  taxNumber?: string;
  paymentTerms?: string;
}) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/suppliers`, {
    method: 'POST',
    headers: {
      ...headers(input.accessToken, input.deviceUuid),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(input),
  });

  return (await parseResponse(response, apiSuccessResponseSchema(supplierSchema))).data;
}

export async function listSupplierBalances(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; search?: string }) {
  const url = new URL(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/suppliers/balances`);
  if (input.search?.trim()) url.searchParams.set('search', input.search.trim());
  const response = await fetch(url, { headers: headers(input.accessToken, input.deviceUuid) });
  return (await parseResponse(response, supplierBalanceResponseSchema)).data;
}

export async function fetchSupplierStatement(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; supplierUuid: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/suppliers/${input.supplierUuid}/statement`, {
    headers: headers(input.accessToken, input.deviceUuid),
  });
  return (await parseResponse(response, supplierStatementResponseSchema)).data;
}

export async function listProductsForPurchase(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; search?: string }) {
  const url = new URL(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/products`);
  if (input.search?.trim()) url.searchParams.set('search', input.search.trim());
  const response = await fetch(url, { headers: headers(input.accessToken, input.deviceUuid) });
  return (await parseResponse(response, productsResponseSchema)).data;
}

export async function listUnitsForPurchase(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/products/units`, {
    headers: headers(input.accessToken, input.deviceUuid),
  });
  return (await parseResponse(response, unitsResponseSchema)).data;
}

export async function listWarehousesForPurchase(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/inventory/warehouses`, {
    headers: headers(input.accessToken, input.deviceUuid),
  });
  return (await parseResponse(response, warehousesResponseSchema)).data;
}

export async function createPurchaseEntry(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  supplierUuid: string;
  invoiceNumber?: string;
  invoiceDate?: string;
  currency?: string;
  transportCost?: string;
  loadingCost?: string;
  otherLandedCost?: string;
  tax?: string;
  discount?: string;
  notes?: string;
  items: Array<{
    productUuid: string;
    purchaseUnitUuid: string;
    quantity: string;
    unitCost: string;
    batchNumber?: string;
    expiryDate?: string;
    serialNumbers?: string[];
    baseUnitMultiplier?: string;
  }>;
}) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/purchases`, {
    method: 'POST',
    headers: {
      ...headers(input.accessToken, input.deviceUuid),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(input),
  });

  return (await parseResponse(response, apiSuccessResponseSchema(z.record(z.string(), z.unknown())))).data;
}

export async function listPurchases(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  search?: string;
  supplierUuid?: string;
  status?: 'draft' | 'completed' | 'received' | 'paid' | 'partially_paid';
}) {
  const url = new URL(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/purchases`);
  if (input.search?.trim()) url.searchParams.set('search', input.search.trim());
  if (input.supplierUuid) url.searchParams.set('supplierUuid', input.supplierUuid);
  if (input.status) url.searchParams.set('status', input.status);
  const response = await fetch(url, { headers: headers(input.accessToken, input.deviceUuid) });
  return (await parseResponse(response, purchaseListResponseSchema)).data;
}

export async function fetchPurchaseDetails(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; purchaseUuid: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/purchases/${input.purchaseUuid}`, {
    headers: headers(input.accessToken, input.deviceUuid),
  });
  return (await parseResponse(response, purchaseDetailsResponseSchema)).data;
}

export async function receivePurchaseStock(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  purchaseUuid: string;
  warehouseUuid: string;
  receivedItems: Array<{
    productUuid: string;
    quantity: string;
    batchNumber?: string;
    expiryDate?: string;
    serialNumbers?: string[];
  }>;
}) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/purchases/${input.purchaseUuid}/receive`, {
    method: 'POST',
    headers: {
      ...headers(input.accessToken, input.deviceUuid),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ warehouseUuid: input.warehouseUuid, receivedItems: input.receivedItems }),
  });
  return (await parseResponse(response, apiSuccessResponseSchema(z.record(z.string(), z.unknown())))).data;
}

export async function recordPurchasePayment(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  purchaseUuid: string;
  amount: string;
  reference?: string;
  paymentMethod?: string;
}) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/purchases/${input.purchaseUuid}/payment`, {
    method: 'POST',
    headers: {
      ...headers(input.accessToken, input.deviceUuid),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ amount: input.amount, reference: input.reference, paymentMethod: input.paymentMethod }),
  });

  return (await parseResponse(response, apiSuccessResponseSchema(z.record(z.string(), z.unknown())))).data;
}

export async function listPurchasePayments(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; supplierUuid?: string; purchaseUuid?: string }) {
  const url = new URL(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/purchase-payments`);
  if (input.supplierUuid) url.searchParams.set('supplierUuid', input.supplierUuid);
  if (input.purchaseUuid) url.searchParams.set('purchaseUuid', input.purchaseUuid);
  const response = await fetch(url, { headers: headers(input.accessToken, input.deviceUuid) });
  return (await parseResponse(response, purchasePaymentsResponseSchema)).data;
}

export async function createPurchaseReturn(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  purchaseUuid: string;
  warehouseUuid: string;
  reason?: string;
  note?: string;
  items: Array<{ productUuid: string; quantity: string; unitUuid?: string }>;
}) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/purchases/${input.purchaseUuid}/returns`, {
    method: 'POST',
    headers: {
      ...headers(input.accessToken, input.deviceUuid),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      warehouseUuid: input.warehouseUuid,
      reason: input.reason,
      note: input.note,
      items: input.items,
    }),
  });

  return (await parseResponse(response, apiSuccessResponseSchema(z.record(z.string(), z.unknown())))).data;
}

export async function listPurchaseReturns(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; supplierUuid?: string; purchaseUuid?: string }) {
  const url = new URL(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/purchase-returns`);
  if (input.supplierUuid) url.searchParams.set('supplierUuid', input.supplierUuid);
  if (input.purchaseUuid) url.searchParams.set('purchaseUuid', input.purchaseUuid);
  const response = await fetch(url, { headers: headers(input.accessToken, input.deviceUuid) });
  return (await parseResponse(response, purchaseReturnsResponseSchema)).data;
}
