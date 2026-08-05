import { apiBasePath, apiSuccessResponseSchema } from '@marketlens/api-contracts';
import { z } from 'zod';

const expenseCategorySchema = z.object({
  uuid: z.string().uuid(),
  name: z.string(),
  requiresApproval: z.string(),
  active: z.string(),
  createdAt: z.string(),
  updatedAt: z.string(),
});

const cashAccountSchema = z.object({
  uuid: z.string().uuid(),
  code: z.string(),
  name: z.string(),
  currentBalance: z.coerce.number(),
  active: z.string(),
});

const bankAccountSchema = z.object({
  uuid: z.string().uuid(),
  code: z.string(),
  name: z.string(),
  bankName: z.string().nullable().optional(),
  accountNumberMasked: z.string().nullable().optional(),
  currentBalance: z.coerce.number(),
  active: z.string(),
});

const expenseSchema = z.object({
  uuid: z.string().uuid(),
  categoryUuid: z.string().uuid(),
  categoryName: z.string().nullable().optional(),
  approvalRequested: z.string().nullable().optional(),
  createdByUserUuid: z.string().uuid().nullable().optional(),
  createdByName: z.string().nullable().optional(),
  approvedByUserUuid: z.string().uuid().nullable().optional(),
  reversedByUserUuid: z.string().uuid().nullable().optional(),
  reversedAt: z.string().nullable().optional(),
  cashAccountUuid: z.string().uuid().nullable().optional(),
  cashAccountName: z.string().nullable().optional(),
  bankAccountUuid: z.string().uuid().nullable().optional(),
  bankAccountName: z.string().nullable().optional(),
  shiftUuid: z.string().uuid().nullable().optional(),
  amount: z.string(),
  paymentMethod: z.string(),
  description: z.string(),
  receiptAttachmentPath: z.string().nullable().optional(),
  status: z.string(),
  createdAt: z.string(),
  updatedAt: z.string(),
});

const summaryByCategorySchema = z.object({
  categoryUuid: z.string().uuid().nullable().optional(),
  categoryName: z.string().nullable().optional(),
  expenseCount: z.coerce.number(),
  amount: z.coerce.number(),
});

const summaryByDateSchema = z.object({
  expenseDate: z.string(),
  expenseCount: z.coerce.number(),
  amount: z.coerce.number(),
});

const summaryByCreatorSchema = z.object({
  creatorUserUuid: z.string().uuid().nullable().optional(),
  creatorName: z.string(),
  expenseCount: z.coerce.number(),
  amount: z.coerce.number(),
});

const expenseCategoriesResponseSchema = apiSuccessResponseSchema(z.array(expenseCategorySchema));
const cashAccountsResponseSchema = apiSuccessResponseSchema(z.array(cashAccountSchema));
const bankAccountsResponseSchema = apiSuccessResponseSchema(z.array(bankAccountSchema));
const expensesResponseSchema = apiSuccessResponseSchema(z.array(expenseSchema));
const summaryByCategoryResponseSchema = apiSuccessResponseSchema(z.array(summaryByCategorySchema));
const summaryByDateResponseSchema = apiSuccessResponseSchema(z.array(summaryByDateSchema));
const summaryByCreatorResponseSchema = apiSuccessResponseSchema(z.array(summaryByCreatorSchema));

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

export type ExpenseCategory = z.infer<typeof expenseCategorySchema>;
export type CashAccount = z.infer<typeof cashAccountSchema>;
export type BankAccount = z.infer<typeof bankAccountSchema>;
export type ExpenseRow = z.infer<typeof expenseSchema>;

export async function listExpenseCategories(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/expense-categories`, {
    headers: headers(input.accessToken, input.deviceUuid),
  });
  return (await parseResponse(response, expenseCategoriesResponseSchema)).data;
}

export async function createExpenseCategory(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  name: string;
  requiresApproval: boolean;
}) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/expense-categories`, {
    method: 'POST',
    headers: {
      ...headers(input.accessToken, input.deviceUuid),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ name: input.name, requiresApproval: input.requiresApproval }),
  });

  return (await parseResponse(response, apiSuccessResponseSchema(z.record(z.string(), z.unknown())))).data;
}

export async function listCashAccounts(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/cash-accounts`, {
    headers: headers(input.accessToken, input.deviceUuid),
  });
  return (await parseResponse(response, cashAccountsResponseSchema)).data;
}

export async function listBankAccounts(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/bank-accounts`, {
    headers: headers(input.accessToken, input.deviceUuid),
  });
  return (await parseResponse(response, bankAccountsResponseSchema)).data;
}

export async function createExpense(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  categoryUuid: string;
  amount: string;
  paymentMethod: 'cash' | 'bank';
  description: string;
  cashAccountUuid?: string;
  bankAccountUuid?: string;
  shiftUuid?: string;
  receiptAttachmentPath?: string;
  approvalRequested?: boolean;
  createdByUserUuid?: string;
  createdByName?: string;
}) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/expenses`, {
    method: 'POST',
    headers: {
      ...headers(input.accessToken, input.deviceUuid),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(input),
  });

  return (await parseResponse(response, apiSuccessResponseSchema(z.record(z.string(), z.unknown())))).data;
}

export async function approveExpense(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; expenseUuid: string; approvedByUserUuid: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/expenses/${input.expenseUuid}/approve`, {
    method: 'POST',
    headers: {
      ...headers(input.accessToken, input.deviceUuid),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ approvedByUserUuid: input.approvedByUserUuid }),
  });

  return (await parseResponse(response, apiSuccessResponseSchema(z.record(z.string(), z.unknown())))).data;
}

export async function rejectExpense(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; expenseUuid: string; rejectedByUserUuid: string; reason?: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/expenses/${input.expenseUuid}/reject`, {
    method: 'POST',
    headers: {
      ...headers(input.accessToken, input.deviceUuid),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ rejectedByUserUuid: input.rejectedByUserUuid, reason: input.reason }),
  });

  return (await parseResponse(response, apiSuccessResponseSchema(z.record(z.string(), z.unknown())))).data;
}

export async function requestExpenseReversal(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  expenseUuid: string;
  reason: string;
  requestedByUserUuid?: string;
}) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/expenses/${input.expenseUuid}/reversal-requests`, {
    method: 'POST',
    headers: {
      ...headers(input.accessToken, input.deviceUuid),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ reason: input.reason, requestedByUserUuid: input.requestedByUserUuid }),
  });

  return (await parseResponse(response, apiSuccessResponseSchema(z.record(z.string(), z.unknown())))).data;
}

export async function listExpenses(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  status?: string;
  categoryUuid?: string;
  from?: string;
  to?: string;
  creatorUserUuid?: string;
}) {
  const url = new URL(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/expenses`);
  if (input.status) url.searchParams.set('status', input.status);
  if (input.categoryUuid) url.searchParams.set('categoryUuid', input.categoryUuid);
  if (input.from) url.searchParams.set('from', input.from);
  if (input.to) url.searchParams.set('to', input.to);
  if (input.creatorUserUuid) url.searchParams.set('creatorUserUuid', input.creatorUserUuid);

  const response = await fetch(url, { headers: headers(input.accessToken, input.deviceUuid) });
  return (await parseResponse(response, expensesResponseSchema)).data;
}

export async function listExpenseSummaryByCategory(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; from?: string; to?: string }) {
  const url = new URL(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/expenses/summary/by-category`);
  if (input.from) url.searchParams.set('from', input.from);
  if (input.to) url.searchParams.set('to', input.to);
  const response = await fetch(url, { headers: headers(input.accessToken, input.deviceUuid) });
  return (await parseResponse(response, summaryByCategoryResponseSchema)).data;
}

export async function listExpenseSummaryByDate(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; from?: string; to?: string }) {
  const url = new URL(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/expenses/summary/by-date`);
  if (input.from) url.searchParams.set('from', input.from);
  if (input.to) url.searchParams.set('to', input.to);
  const response = await fetch(url, { headers: headers(input.accessToken, input.deviceUuid) });
  return (await parseResponse(response, summaryByDateResponseSchema)).data;
}

export async function listExpenseSummaryByCreator(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; from?: string; to?: string }) {
  const url = new URL(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/expenses/summary/by-creator`);
  if (input.from) url.searchParams.set('from', input.from);
  if (input.to) url.searchParams.set('to', input.to);
  const response = await fetch(url, { headers: headers(input.accessToken, input.deviceUuid) });
  return (await parseResponse(response, summaryByCreatorResponseSchema)).data;
}
