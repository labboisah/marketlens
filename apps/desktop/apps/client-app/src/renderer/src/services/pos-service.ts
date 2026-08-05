import { apiBasePath, apiSuccessResponseSchema } from '@marketlens/api-contracts';
import { z } from 'zod';

const customerSchema = z.object({
  uuid: z.string().uuid(),
  code: z.string(),
  name: z.string(),
  type: z.string(),
  phone: z.string().nullable().optional(),
  address: z.string().nullable().optional(),
  status: z.string(),
});

const paymentMethodSchema = z.object({
  uuid: z.string().uuid(),
  code: z.string(),
  name: z.string(),
  type: z.string(),
});

const warehouseSchema = z.object({
  uuid: z.string().uuid(),
  code: z.string(),
  name: z.string(),
  address: z.string().nullable().optional(),
});

const categorySchema = z.object({
  uuid: z.string().uuid(),
  name: z.string(),
  slug: z.string().nullable().optional(),
});

const brandSchema = z.object({
  uuid: z.string().uuid(),
  name: z.string(),
  description: z.string().nullable().optional(),
});

const posUnitSchema = z.object({
  unitUuid: z.string().uuid(),
  unitName: z.string(),
  unitCode: z.string(),
  toBaseMultiplier: z.string(),
  isBase: z.boolean(),
});

const posStockSchema = z.object({
  unitUuid: z.string().uuid(),
  quantity: z.string(),
});

const posProductSchema = z.object({
  uuid: z.string().uuid(),
  sku: z.string(),
  name: z.string(),
  description: z.string().nullable().optional(),
  categoryUuid: z.string().uuid().nullable().optional(),
  categoryName: z.string().nullable().optional(),
  brandUuid: z.string().uuid().nullable().optional(),
  brandName: z.string().nullable().optional(),
  active: z.string(),
  isService: z.string(),
  isNonStock: z.string(),
  minSellingPrice: z.string().nullable().optional(),
  retailPrice: z.string().nullable().optional(),
  wholesalePrice: z.string().nullable().optional(),
  defaultSalesUnitUuid: z.string().uuid().nullable().optional(),
  defaultSalesUnitName: z.string().nullable().optional(),
  defaultSalesUnitCode: z.string().nullable().optional(),
  barcodes: z.array(z.string()),
  units: z.array(posUnitSchema),
  stockByUnit: z.array(posStockSchema),
  lastSoldAt: z.string().nullable().optional(),
  soldCount: z.coerce.number(),
});

const posCatalogSchema = apiSuccessResponseSchema(z.object({
  warehouses: z.array(warehouseSchema),
  categories: z.array(categorySchema),
  brands: z.array(brandSchema),
  paymentMethods: z.array(paymentMethodSchema),
  products: z.array(posProductSchema),
}));

const customersResponseSchema = apiSuccessResponseSchema(z.array(customerSchema));

const saleResponseSchema = apiSuccessResponseSchema(z.object({
  uuid: z.string().uuid(),
  saleNumber: z.string(),
  status: z.string(),
  subtotal: z.string(),
  discountAmount: z.string(),
  totalAmount: z.string(),
  receipt: z.object({
    uuid: z.string().uuid(),
    saleUuid: z.string().uuid(),
    format: z.enum(['thermal', 'a4']),
    receiptNumber: z.string(),
    printCount: z.coerce.number(),
    createdAt: z.string(),
  }),
}));

const receiptDetailsSchema = apiSuccessResponseSchema(z.object({
  uuid: z.string().uuid(),
  saleUuid: z.string().uuid(),
  format: z.string(),
  receiptNumber: z.string(),
  printCount: z.coerce.number(),
  createdAt: z.string(),
  saleNumber: z.string(),
  customerUuid: z.string().uuid().nullable(),
  totalAmount: z.string(),
  subtotal: z.string(),
  discountAmount: z.string(),
  saleCreatedAt: z.string(),
  cashierUserUuid: z.string().uuid().nullable(),
  cashierUsername: z.string().nullable(),
  customerCode: z.string().nullable(),
  customerName: z.string().nullable(),
  customerPhone: z.string().nullable(),
  businessName: z.string().nullable(),
  branchName: z.string().nullable(),
  shopDeviceName: z.string().nullable(),
  receiptVerificationReference: z.string().nullable().optional(),
  thankYouMessage: z.string().optional(),
  items: z.array(z.object({
    uuid: z.string().uuid(),
    productUuid: z.string().uuid(),
    sku: z.string().nullable(),
    name: z.string(),
    unitCode: z.string().nullable(),
    unitName: z.string().nullable(),
    quantity: z.string(),
    unitPrice: z.string(),
    lineTotal: z.string(),
  })),
  payments: z.array(z.object({
    uuid: z.string().uuid(),
    paymentMethodCode: z.string(),
    paymentMethodName: z.string(),
    paymentMethodType: z.string(),
    amount: z.string(),
    amountTendered: z.string().nullable(),
    reference: z.string().nullable(),
    transferVerified: z.boolean(),
    creditAmount: z.string().nullable(),
    customerCreditSelection: z.string().nullable(),
  })),
  totals: z.object({
    subtotal: z.string(),
    discountAmount: z.string(),
    totalAmount: z.string(),
    totalPaid: z.string(),
    totalTendered: z.string(),
    changeDue: z.string(),
    balance: z.string(),
  }),
}));

const reprintResponseSchema = apiSuccessResponseSchema(z.object({
  receiptUuid: z.string().uuid(),
  printCount: z.coerce.number(),
}));

const saleLookupSchema = apiSuccessResponseSchema(z.array(z.object({
  uuid: z.string().uuid(),
  saleNumber: z.string(),
  status: z.string(),
  subtotal: z.string(),
  discountAmount: z.string(),
  totalAmount: z.string(),
  createdAt: z.string(),
  shiftUuid: z.string().uuid(),
  warehouseUuid: z.string().uuid(),
  receiptUuid: z.string().uuid().nullable(),
  receiptNumber: z.string().nullable(),
  customerUuid: z.string().uuid().nullable(),
  customerCode: z.string().nullable(),
  customerName: z.string().nullable(),
  cashierUserUuid: z.string().uuid().nullable(),
  cashierUsername: z.string().nullable(),
  cancellation: z.object({
    reason: z.string(),
    approvalState: z.string(),
    refundState: z.string(),
    refundMethod: z.string().nullable(),
  }).nullable(),
  returnSummary: z.object({
    returnCount: z.coerce.number(),
    refundAmount: z.coerce.number(),
    pendingApprovalCount: z.coerce.number(),
    refundedCount: z.coerce.number(),
  }),
  items: z.array(z.object({
    uuid: z.string().uuid(),
    productUuid: z.string().uuid(),
    sku: z.string(),
    name: z.string(),
    unitUuid: z.string().uuid(),
    unitCode: z.string().nullable(),
    unitName: z.string().nullable(),
    quantity: z.string(),
    unitPrice: z.string(),
    lineTotal: z.string(),
    returnedQuantity: z.coerce.number(),
  })),
  payments: z.array(z.object({
    uuid: z.string().uuid(),
    paymentMethodName: z.string(),
    paymentMethodType: z.string(),
    amount: z.string(),
    reference: z.string().nullable(),
  })),
})));

const saleReturnResponseSchema = apiSuccessResponseSchema(z.object({
  uuid: z.string().uuid(),
  saleUuid: z.string().uuid(),
  saleNumber: z.string(),
  status: z.string(),
  approvalState: z.string(),
  refundState: z.string(),
  reason: z.string(),
  refundMethod: z.string(),
  refundAmount: z.string(),
  createdAt: z.string(),
}));

const saleReturnDetailsSchema = apiSuccessResponseSchema(z.object({
  uuid: z.string().uuid(),
  saleUuid: z.string().uuid(),
  status: z.string(),
  refundAmount: z.string(),
  reason: z.string(),
  refundMethod: z.string().nullable(),
  approvalRequested: z.string().nullable(),
  approvedByUserUuid: z.string().uuid().nullable(),
  createdAt: z.string(),
  saleNumber: z.string(),
  saleCreatedAt: z.string(),
  customerUuid: z.string().uuid().nullable(),
  customerCode: z.string().nullable(),
  customerName: z.string().nullable(),
  cashierUserUuid: z.string().uuid().nullable(),
  cashierUsername: z.string().nullable(),
  businessName: z.string().nullable(),
  branchName: z.string().nullable(),
  receiptVerificationReference: z.string(),
  thankYouMessage: z.string(),
  approvalState: z.string(),
  refundState: z.string(),
  items: z.array(z.object({
    uuid: z.string().uuid(),
    quantity: z.string(),
    amount: z.string(),
    productUuid: z.string().uuid(),
    sku: z.string().nullable(),
    name: z.string(),
    unitCode: z.string().nullable(),
    unitName: z.string().nullable(),
  })),
  totals: z.object({
    refundAmount: z.string(),
  }),
}));

const saleCancellationResponseSchema = apiSuccessResponseSchema(z.object({
  uuid: z.string().uuid(),
  saleUuid: z.string().uuid(),
  saleNumber: z.string(),
  reason: z.string(),
  approvalState: z.string(),
  refundState: z.string(),
  refundMethod: z.string(),
  createdAt: z.string(),
}));

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

export type PosWarehouse = z.infer<typeof warehouseSchema>;
export type PosCategory = z.infer<typeof categorySchema>;
export type PosBrand = z.infer<typeof brandSchema>;
export type PosPaymentMethod = z.infer<typeof paymentMethodSchema>;
export type PosUnit = z.infer<typeof posUnitSchema>;
export type PosStock = z.infer<typeof posStockSchema>;
export type PosProduct = z.infer<typeof posProductSchema>;
export type PosCustomer = z.infer<typeof customerSchema>;
export type PosCompletedSale = z.infer<typeof saleResponseSchema>['data'];
export type PosReceiptDetails = z.infer<typeof receiptDetailsSchema>['data'];
export type PosSaleLookupResult = z.infer<typeof saleLookupSchema>['data'][number];
export type PosSaleReturn = z.infer<typeof saleReturnResponseSchema>['data'];
export type PosSaleReturnDetails = z.infer<typeof saleReturnDetailsSchema>['data'];
export type PosSaleCancellation = z.infer<typeof saleCancellationResponseSchema>['data'];

export async function loadPosCatalog(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; warehouseUuid?: string; limit?: number }) {
  const url = new URL(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/pos/catalog`);
  if (input.warehouseUuid) {
    url.searchParams.set('warehouseUuid', input.warehouseUuid);
  }
  if (typeof input.limit === 'number') {
    url.searchParams.set('limit', String(input.limit));
  }

  const response = await fetch(url, { headers: headers(input.accessToken, input.deviceUuid) });
  return (await parseResponse(response, posCatalogSchema)).data;
}

export async function searchCustomers(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; search: string }) {
  const url = new URL(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/customers`);
  url.searchParams.set('search', input.search);

  const response = await fetch(url, { headers: headers(input.accessToken, input.deviceUuid) });
  return (await parseResponse(response, customersResponseSchema)).data;
}

export async function submitPosSale(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  idempotencyKey: string;
  shiftUuid: string;
  warehouseUuid: string;
  customerUuid?: string;
  discountAmount?: string;
  receiptFormat?: 'thermal' | 'a4';
  items: Array<{ productUuid: string; unitUuid: string; quantity: string; unitPrice: string }>;
  payments: Array<{
    paymentMethodUuid: string;
    amount: string;
    amountTendered?: string;
    reference?: string;
    transferVerified?: boolean;
    creditAmount?: string;
    customerCreditSelection?: string;
  }>;
}) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/pos/sales`, {
    method: 'POST',
    headers: {
      ...headers(input.accessToken, input.deviceUuid),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      idempotencyKey: input.idempotencyKey,
      shiftUuid: input.shiftUuid,
      warehouseUuid: input.warehouseUuid,
      customerUuid: input.customerUuid,
      discountAmount: input.discountAmount,
      receiptFormat: input.receiptFormat,
      items: input.items,
      payments: input.payments,
    }),
  });

  return (await parseResponse(response, saleResponseSchema)).data;
}

export async function fetchReceiptDetails(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; receiptUuid: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/receipts/${input.receiptUuid}`, {
    headers: headers(input.accessToken, input.deviceUuid),
  });

  return (await parseResponse(response, receiptDetailsSchema)).data;
}

export async function reprintReceipt(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; receiptUuid: string; reason: string; approvedByUserUuid?: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/receipts/${input.receiptUuid}/reprints`, {
    method: 'POST',
    headers: {
      ...headers(input.accessToken, input.deviceUuid),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      reason: input.reason,
      approvedByUserUuid: input.approvedByUserUuid,
    }),
  });

  return (await parseResponse(response, reprintResponseSchema)).data;
}

export async function searchPosSales(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  saleNumber?: string;
  receiptNumber?: string;
  customer?: string;
  date?: string;
  cashier?: string;
  product?: string;
  limit?: number;
}) {
  const url = new URL(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/pos/sales/search`);
  if (input.saleNumber) url.searchParams.set('saleNumber', input.saleNumber);
  if (input.receiptNumber) url.searchParams.set('receiptNumber', input.receiptNumber);
  if (input.customer) url.searchParams.set('customer', input.customer);
  if (input.date) url.searchParams.set('date', input.date);
  if (input.cashier) url.searchParams.set('cashier', input.cashier);
  if (input.product) url.searchParams.set('product', input.product);
  if (typeof input.limit === 'number') url.searchParams.set('limit', String(input.limit));

  const response = await fetch(url, { headers: headers(input.accessToken, input.deviceUuid) });
  return (await parseResponse(response, saleLookupSchema)).data;
}

export async function createPosSaleReturn(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  saleUuid: string;
  returnType: 'full' | 'partial';
  reason: string;
  items?: Array<{ saleItemUuid: string; quantity: string }>;
  refundMethod: 'cash' | 'bank_transfer' | 'pos_terminal' | 'mobile_money' | 'credit_note';
  approvalRequested?: boolean;
  approvedByUserUuid?: string;
  requestedByUserUuid?: string;
}) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/pos/returns`, {
    method: 'POST',
    headers: {
      ...headers(input.accessToken, input.deviceUuid),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      saleUuid: input.saleUuid,
      returnType: input.returnType,
      reason: input.reason,
      items: input.items,
      refundMethod: input.refundMethod,
      approvalRequested: input.approvalRequested,
      approvedByUserUuid: input.approvedByUserUuid,
      requestedByUserUuid: input.requestedByUserUuid,
    }),
  });

  return (await parseResponse(response, saleReturnResponseSchema)).data;
}

export async function fetchSaleReturnDetails(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; saleReturnUuid: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/pos/returns/${input.saleReturnUuid}`, {
    headers: headers(input.accessToken, input.deviceUuid),
  });

  return (await parseResponse(response, saleReturnDetailsSchema)).data;
}

export async function cancelPosSale(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  saleUuid: string;
  reason: string;
  refundMethod: 'cash' | 'bank_transfer' | 'pos_terminal' | 'mobile_money' | 'credit_note';
  approvalRequested?: boolean;
  approvedByUserUuid?: string;
  refundState?: 'pending' | 'refunded';
  requestedByUserUuid?: string;
}) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/pos/sales/${input.saleUuid}/cancel`, {
    method: 'POST',
    headers: {
      ...headers(input.accessToken, input.deviceUuid),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      reason: input.reason,
      refundMethod: input.refundMethod,
      approvalRequested: input.approvalRequested,
      approvedByUserUuid: input.approvedByUserUuid,
      refundState: input.refundState,
      requestedByUserUuid: input.requestedByUserUuid,
    }),
  });

  return (await parseResponse(response, saleCancellationResponseSchema)).data;
}