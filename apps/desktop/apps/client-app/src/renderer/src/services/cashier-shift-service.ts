import { apiBasePath, apiSuccessResponseSchema } from '@marketlens/api-contracts';
import { z } from 'zod';

const shiftPaymentSummarySchema = z.object({
  paymentMethod: z.string(),
  amount: z.coerce.number(),
});

const terminalSchema = z.object({
  uuid: z.string().uuid(),
  code: z.string(),
  name: z.string(),
  status: z.string(),
});

const cashAccountSchema = z.object({
  uuid: z.string().uuid(),
  code: z.string(),
  name: z.string(),
  currentBalance: z.coerce.number(),
  active: z.string(),
});

const currentShiftSchema = z.object({
  uuid: z.string().uuid(),
  shiftNumber: z.string(),
  terminalUuid: z.string().uuid(),
  terminalCode: z.string(),
  terminalName: z.string(),
  cashAccountUuid: z.string().uuid(),
  cashAccountName: z.string(),
  cashierUserUuid: z.string().uuid(),
  status: z.string(),
  openingCash: z.string(),
  notes: z.string().nullable().optional(),
  expectedCash: z.coerce.number(),
  actualCash: z.coerce.number().nullable().optional(),
  variance: z.coerce.number().nullable().optional(),
  openedAt: z.string(),
  closedAt: z.string().nullable().optional(),
  salesCount: z.coerce.number(),
  salesValue: z.coerce.number(),
  refunds: z.coerce.number(),
  expenses: z.coerce.number(),
  currentDurationMinutes: z.coerce.number(),
  paymentMethodSummary: z.array(shiftPaymentSummarySchema),
});

const currentShiftResponseSchema = apiSuccessResponseSchema(z.object({
  active: z.boolean(),
  shift: currentShiftSchema.nullable(),
}));

const openShiftResponseSchema = apiSuccessResponseSchema(z.object({
  uuid: z.string().uuid(),
  shiftNumber: z.string(),
  terminalUuid: z.string().uuid(),
  cashAccountUuid: z.string().uuid(),
  cashierUserUuid: z.string().uuid(),
  status: z.string(),
  openingCash: z.string(),
  notes: z.string().nullable().optional(),
  openedAt: z.string(),
  createdAt: z.string(),
  updatedAt: z.string(),
}));

const closeShiftResponseSchema = apiSuccessResponseSchema(z.object({
  uuid: z.string().uuid(),
  status: z.string(),
  expectedCash: z.string(),
  actualCash: z.string(),
  variance: z.string(),
  settlementUuid: z.string().uuid(),
  notes: z.string().nullable().optional(),
  cashDenominations: z.array(z.object({ denomination: z.string(), count: z.number() })).default([]),
  supportingExplanation: z.string().nullable().optional(),
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

export type CashierShiftTerminal = z.infer<typeof terminalSchema>;
export type CashierShiftCashAccount = z.infer<typeof cashAccountSchema>;
export type CashierCurrentShift = z.infer<typeof currentShiftSchema>;

export async function loadCashierShiftContext(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string }) {
  const [terminalsResponse, cashAccountsResponse, currentShiftResponse] = await Promise.all([
    fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/terminals`, { headers: headers(input.accessToken, input.deviceUuid) }),
    fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/cash-accounts`, { headers: headers(input.accessToken, input.deviceUuid) }),
    fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/cashier-shifts/current`, { headers: headers(input.accessToken, input.deviceUuid) }),
  ]);

  const terminals = (await parseResponse(terminalsResponse, apiSuccessResponseSchema(z.array(terminalSchema)))).data;
  const cashAccounts = (await parseResponse(cashAccountsResponse, apiSuccessResponseSchema(z.array(cashAccountSchema)))).data;
  const currentShift = (await parseResponse(currentShiftResponse, currentShiftResponseSchema)).data;

  return { terminals, cashAccounts, currentShift };
}

export async function openCashierShift(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  terminalUuid: string;
  cashAccountUuid: string;
  openingCash: string;
  notes?: string;
}) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/cashier-shifts`, {
    method: 'POST',
    headers: {
      ...headers(input.accessToken, input.deviceUuid),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      terminalUuid: input.terminalUuid,
      cashAccountUuid: input.cashAccountUuid,
      openingCash: input.openingCash,
      notes: input.notes,
    }),
  });

  return (await parseResponse(response, openShiftResponseSchema)).data;
}

export async function closeCashierShift(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  shiftUuid: string;
  actualCash: string;
  notes?: string;
  cashDenominations?: Array<{ denomination: string; count: number }>;
  supportingExplanation?: string;
}) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/cashier-shifts/${input.shiftUuid}/close`, {
    method: 'POST',
    headers: {
      ...headers(input.accessToken, input.deviceUuid),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      actualCash: input.actualCash,
      notes: input.notes,
      cashDenominations: input.cashDenominations ?? [],
      supportingExplanation: input.supportingExplanation,
    }),
  });

  return (await parseResponse(response, closeShiftResponseSchema)).data;
}

export function formatCashierShiftDuration(minutes: number): string {
  const hours = Math.floor(minutes / 60);
  const remainingMinutes = minutes % 60;

  if (hours <= 0) {
    return `${remainingMinutes}m`;
  }

  return `${hours}h ${remainingMinutes}m`;
}
