import { apiBasePath, apiSuccessResponseSchema } from '@marketlens/api-contracts';
import { z } from 'zod';

const customerSummarySchema = z.object({
  uuid: z.string().uuid(),
  code: z.string(),
  name: z.string(),
  type: z.string(),
  phone: z.string().nullable().optional(),
  address: z.string().nullable().optional(),
  status: z.string(),
});

const customerListSchema = apiSuccessResponseSchema(z.array(customerSummarySchema));

const customerMutationSchema = apiSuccessResponseSchema(customerSummarySchema);

const customerStatementSchema = apiSuccessResponseSchema(z.object({
  customerUuid: z.string().uuid(),
  balance: z.string(),
  overdue: z.boolean(),
  transactions: z.array(z.object({
    uuid: z.string().uuid(),
    customerUuid: z.string().uuid(),
    transactionType: z.string(),
    amount: z.string(),
    dueDate: z.string().nullable(),
    reference: z.string().nullable(),
    reversalOfUuid: z.string().nullable(),
    metadataJson: z.string().nullable(),
    createdAt: z.string(),
  })),
}));

const debtProfileSchema = apiSuccessResponseSchema(z.object({
  customer: customerSummarySchema,
  account: z.object({
    currentBalance: z.string(),
    creditLimit: z.string(),
    availableCredit: z.string(),
    overdueAmount: z.string(),
    hasOverdueBalance: z.boolean(),
    additionalCreditAllowed: z.boolean(),
  }),
  paymentHistory: z.array(z.object({
    uuid: z.string().uuid(),
    amount: z.string(),
    reference: z.string().nullable(),
    paymentMethod: z.string().nullable(),
    createdAt: z.string(),
    allocatedAmount: z.string(),
    unallocatedAmount: z.string(),
  })),
  openCreditSales: z.array(z.object({
    uuid: z.string().uuid(),
    reference: z.string().nullable(),
    dueDate: z.string().nullable(),
    amount: z.string(),
    createdAt: z.string(),
    allocatedAmount: z.string(),
    outstandingAmount: z.string(),
  })),
}));

const creditEligibilitySchema = apiSuccessResponseSchema(z.object({
  customerUuid: z.string().uuid(),
  dueDate: z.string(),
  requestedAmount: z.string(),
  eligible: z.boolean(),
  requiresApproval: z.boolean(),
  reason: z.string().nullable(),
  existingBalance: z.string(),
  availableCredit: z.string(),
  creditLimit: z.string(),
  overdueAmount: z.string(),
  hasOverdueBalance: z.boolean(),
}));

const customerPaymentSchema = apiSuccessResponseSchema(z.object({
  uuid: z.string().uuid(),
  customerUuid: z.string().uuid(),
  amount: z.string(),
  reference: z.string().nullable(),
  paymentMethod: z.string().nullable(),
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

export type CustomerSummary = z.infer<typeof customerSummarySchema>;
export type CustomerStatement = z.infer<typeof customerStatementSchema>['data'];
export type CustomerDebtProfile = z.infer<typeof debtProfileSchema>['data'];
export type CreditSaleEligibility = z.infer<typeof creditEligibilitySchema>['data'];

export async function searchCustomersForAccounts(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; search?: string }) {
  const url = new URL(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/customers`);
  if (input.search?.trim()) {
    url.searchParams.set('search', input.search.trim());
  }

  const response = await fetch(url, { headers: headers(input.accessToken, input.deviceUuid) });
  return (await parseResponse(response, customerListSchema)).data;
}

export async function createCustomerAccountProfile(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  code: string;
  name: string;
  type: 'retail' | 'wholesale' | 'corporate';
  phone?: string;
  address?: string;
  creditLimit?: string;
  openingBalance?: string;
}) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/customers`, {
    method: 'POST',
    headers: {
      ...headers(input.accessToken, input.deviceUuid),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      code: input.code,
      name: input.name,
      type: input.type,
      phone: input.phone,
      address: input.address,
      creditLimit: input.creditLimit,
      openingBalance: input.openingBalance,
    }),
  });

  return (await parseResponse(response, customerMutationSchema)).data;
}

export async function updateCustomerAccountProfile(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  customerUuid: string;
  name?: string;
  type?: 'retail' | 'wholesale' | 'corporate';
  phone?: string;
  address?: string;
}) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/customers/${input.customerUuid}`, {
    method: 'PATCH',
    headers: {
      ...headers(input.accessToken, input.deviceUuid),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      name: input.name,
      type: input.type,
      phone: input.phone,
      address: input.address,
    }),
  });

  return (await parseResponse(response, customerMutationSchema)).data;
}

export async function fetchCustomerStatement(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; customerUuid: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/customers/${input.customerUuid}/statement`, {
    headers: headers(input.accessToken, input.deviceUuid),
  });

  return (await parseResponse(response, customerStatementSchema)).data;
}

export async function fetchCustomerDebtProfile(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; customerUuid: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/customers/${input.customerUuid}/debt-profile`, {
    headers: headers(input.accessToken, input.deviceUuid),
  });

  return (await parseResponse(response, debtProfileSchema)).data;
}

export async function fetchCreditSaleEligibility(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  customerUuid: string;
  requestedAmount: string;
  dueDate: string;
}) {
  const url = new URL(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/customers/${input.customerUuid}/credit-sale-eligibility`);
  url.searchParams.set('requestedAmount', input.requestedAmount);
  url.searchParams.set('dueDate', input.dueDate);

  const response = await fetch(url, {
    headers: headers(input.accessToken, input.deviceUuid),
  });

  return (await parseResponse(response, creditEligibilitySchema)).data;
}

export async function recordCustomerPayment(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  customerUuid: string;
  amount: string;
  reference?: string;
  paymentMethod?: string;
  allocations?: Array<{ accountTransactionUuid: string; amount: string }>;
}) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/customers/${input.customerUuid}/payments`, {
    method: 'POST',
    headers: {
      ...headers(input.accessToken, input.deviceUuid),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      amount: input.amount,
      reference: input.reference,
      paymentMethod: input.paymentMethod,
      allocations: input.allocations,
    }),
  });

  return (await parseResponse(response, customerPaymentSchema)).data;
}
