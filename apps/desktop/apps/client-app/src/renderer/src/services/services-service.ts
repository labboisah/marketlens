import { apiBasePath, apiSuccessResponseSchema } from '@marketlens/api-contracts';
import { z } from 'zod';

const moduleAccessSchema = z.object({
  moduleKey: z.string(),
  planAllowed: z.boolean(),
  businessEnabled: z.boolean(),
  branchEnabled: z.boolean(),
  enabled: z.boolean(),
  effectiveValue: z.unknown().optional(),
  updatedAt: z.string().optional(),
});

const servicesDashboardSchema = z.object({
  pending: z.coerce.number(),
  assigned: z.coerce.number(),
  inProgress: z.coerce.number(),
  completed: z.coerce.number(),
  cancelled: z.coerce.number(),
  refunded: z.coerce.number(),
  grossRevenue: z.coerce.number(),
  collectedPayments: z.coerce.number(),
  directServiceExpenses: z.coerce.number(),
  pendingAllocations: z.coerce.number(),
  approvedAllocations: z.coerce.number(),
  outstandingStaffEarnings: z.coerce.number(),
});

const serviceCategorySchema = z.object({
  uuid: z.string().uuid(),
  name: z.string(),
  active: z.string(),
  createdAt: z.string(),
  updatedAt: z.string(),
});

const serviceCatalogItemSchema = z.object({
  uuid: z.string().uuid(),
  categoryUuid: z.string().uuid(),
  categoryName: z.string().nullable().optional(),
  code: z.string(),
  name: z.string(),
  active: z.string(),
  currentPrice: z.string().nullable().optional(),
  createdAt: z.string(),
  updatedAt: z.string(),
});

const serviceCatalogSchema = z.object({
  categories: z.array(serviceCategorySchema),
  services: z.array(serviceCatalogItemSchema),
});

const customerSchema = z.object({
  uuid: z.string().uuid(),
  code: z.string(),
  name: z.string(),
  type: z.string(),
  phone: z.string().nullable().optional(),
  address: z.string().nullable().optional(),
  status: z.string(),
});

const serviceStaffSchema = z.object({
  uuid: z.string().uuid(),
  username: z.string(),
  displayName: z.string().nullable().optional(),
  staffCode: z.string().nullable().optional(),
  role: z.string().nullable().optional(),
});

const serviceOrderSchema = z.object({
  uuid: z.string().uuid(),
  orderNumber: z.string(),
  customerUuid: z.string().uuid().nullable().optional(),
  customerName: z.string().nullable().optional(),
  branchUuid: z.string().uuid(),
  status: z.enum(['pending', 'assigned', 'in_progress', 'completed', 'cancelled', 'refunded']),
  discountAmount: z.string(),
  paymentConfirmedAt: z.string().nullable().optional(),
  allocationRequestedAt: z.string().nullable().optional(),
  approvedByUserUuid: z.string().uuid().nullable().optional(),
  cancelledAt: z.string().nullable().optional(),
  refundedAt: z.string().nullable().optional(),
  createdAt: z.string(),
  completedAt: z.string().nullable().optional(),
  grossAmount: z.coerce.number().optional(),
  paidAmount: z.coerce.number().optional(),
  directExpenses: z.coerce.number().optional(),
  allocatedAmount: z.coerce.number().optional(),
});

const serviceOrderItemSchema = z.object({
  uuid: z.string().uuid(),
  serviceUuid: z.string().uuid(),
  serviceName: z.string(),
  quantity: z.string(),
  unitPrice: z.string(),
  lineTotal: z.string(),
  createdAt: z.string(),
});

const serviceAssignmentSchema = z.object({
  uuid: z.string().uuid(),
  orderItemUuid: z.string().uuid(),
  orderUuid: z.string().uuid().optional(),
  orderNumber: z.string().optional(),
  serviceName: z.string().optional(),
  staffUserUuid: z.string().uuid(),
  staffName: z.string().nullable().optional(),
  contributionPercentage: z.string(),
  assignmentRole: z.string().nullable().optional(),
  startedAt: z.string().nullable().optional(),
  completedAt: z.string().nullable().optional(),
  notes: z.string().nullable().optional(),
  orderStatus: z.string().optional(),
  createdAt: z.string(),
});

const servicePaymentSchema = z.object({
  uuid: z.string().uuid(),
  orderUuid: z.string().uuid(),
  orderNumber: z.string(),
  amount: z.string(),
  paymentMethod: z.string(),
  reference: z.string().nullable().optional(),
  createdAt: z.string(),
});

const serviceExpenseSchema = z.object({
  uuid: z.string().uuid(),
  orderUuid: z.string().uuid(),
  orderNumber: z.string(),
  category: z.string().nullable().optional(),
  description: z.string(),
  amount: z.string(),
  paidBy: z.string().nullable().optional(),
  paymentMethod: z.string().nullable().optional(),
  receiptAttachmentPath: z.string().nullable().optional(),
  approvalStatus: z.string(),
  expenseScope: z.string(),
  createdAt: z.string(),
});

const revenueAllocationSchema = z.object({
  uuid: z.string().uuid(),
  orderUuid: z.string().uuid(),
  orderNumber: z.string(),
  ruleUuid: z.string(),
  componentType: z.string(),
  staffUserUuid: z.string().uuid().nullable().optional(),
  staffName: z.string().nullable().optional(),
  amount: z.string(),
  status: z.string(),
  reversalOfUuid: z.string().nullable().optional(),
  createdAt: z.string(),
});

const staffEarningSchema = z.object({
  staffUserUuid: z.string().uuid(),
  staffName: z.string().nullable().optional(),
  username: z.string(),
  currentBalance: z.string(),
  updatedAt: z.string(),
});

const staffSettlementSchema = z.object({
  uuid: z.string().uuid(),
  staffUserUuid: z.string().uuid(),
  staffName: z.string().nullable().optional(),
  amount: z.string(),
  status: z.string(),
  createdAt: z.string(),
});

const allocationPreviewSchema = z.object({
  orderUuid: z.string().uuid(),
  grossAmount: z.coerce.number(),
  discountAmount: z.coerce.number(),
  directExpenses: z.coerce.number(),
  calculationBasis: z.string(),
  distributableAmount: z.coerce.number(),
  staffShare: z.coerce.number(),
  businessShare: z.coerce.number(),
  expenseReserveShare: z.coerce.number(),
  staffSplit: z.array(z.object({
    staffUserUuid: z.string().uuid(),
    contributionPercentage: z.coerce.number(),
    amount: z.coerce.number(),
  })),
  rule: z.object({
    uuid: z.string().uuid(),
    basis: z.string(),
    fixedAmount: z.string().nullable().optional(),
    requiresApproval: z.string(),
  }),
  components: z.array(z.object({
    componentType: z.string(),
    amount: z.coerce.number(),
    percentage: z.coerce.number(),
    staffSplit: z.array(z.object({
      staffUserUuid: z.string().uuid(),
      contributionPercentage: z.coerce.number(),
      amount: z.coerce.number(),
    })),
  })),
  items: z.array(z.object({
    itemUuid: z.string().uuid(),
    serviceUuid: z.string().uuid(),
    serviceName: z.string(),
    lineTotal: z.coerce.number(),
    basisAmount: z.coerce.number(),
  }).passthrough()),
});

const moduleAccessResponseSchema = apiSuccessResponseSchema(moduleAccessSchema);
const dashboardResponseSchema = apiSuccessResponseSchema(servicesDashboardSchema);
const catalogResponseSchema = apiSuccessResponseSchema(serviceCatalogSchema);
const customersResponseSchema = apiSuccessResponseSchema(z.array(customerSchema));
const staffResponseSchema = apiSuccessResponseSchema(z.array(serviceStaffSchema));
const ordersResponseSchema = apiSuccessResponseSchema(z.array(serviceOrderSchema));
const orderDetailResponseSchema = apiSuccessResponseSchema(z.object({
  order: serviceOrderSchema.passthrough(),
  items: z.array(serviceOrderItemSchema),
  assignments: z.array(serviceAssignmentSchema),
}));
const assignmentsResponseSchema = apiSuccessResponseSchema(z.array(serviceAssignmentSchema));
const paymentsResponseSchema = apiSuccessResponseSchema(z.array(servicePaymentSchema));
const expensesResponseSchema = apiSuccessResponseSchema(z.array(serviceExpenseSchema));
const allocationsResponseSchema = apiSuccessResponseSchema(z.array(revenueAllocationSchema));
const staffEarningsResponseSchema = apiSuccessResponseSchema(z.array(staffEarningSchema));
const staffSettlementsResponseSchema = apiSuccessResponseSchema(z.array(staffSettlementSchema));
const previewResponseSchema = apiSuccessResponseSchema(allocationPreviewSchema);
const genericSuccessSchema = apiSuccessResponseSchema(z.record(z.string(), z.unknown()));

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

export type ServicesDashboard = z.infer<typeof servicesDashboardSchema>;
export type ServiceCatalogItem = z.infer<typeof serviceCatalogItemSchema>;
export type ServiceOrder = z.infer<typeof serviceOrderSchema>;
export type ServiceAssignment = z.infer<typeof serviceAssignmentSchema>;
export type ServicePayment = z.infer<typeof servicePaymentSchema>;
export type ServiceExpense = z.infer<typeof serviceExpenseSchema>;
export type RevenueAllocation = z.infer<typeof revenueAllocationSchema>;
export type ServiceModuleAccess = z.infer<typeof moduleAccessSchema>;

export async function getServicesModuleAccess(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/services/module-access`, {
    headers: headers(input.accessToken, input.deviceUuid),
  });
  return (await parseResponse(response, moduleAccessResponseSchema)).data;
}

export async function getServicesDashboard(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/services/dashboard`, {
    headers: headers(input.accessToken, input.deviceUuid),
  });
  return (await parseResponse(response, dashboardResponseSchema)).data;
}

export async function getServicesCatalog(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/services/catalog`, {
    headers: headers(input.accessToken, input.deviceUuid),
  });
  return (await parseResponse(response, catalogResponseSchema)).data;
}

export async function listServiceCustomers(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/services/customers`, {
    headers: headers(input.accessToken, input.deviceUuid),
  });
  return (await parseResponse(response, customersResponseSchema)).data;
}

export async function listServiceStaff(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/services/staff`, {
    headers: headers(input.accessToken, input.deviceUuid),
  });
  return (await parseResponse(response, staffResponseSchema)).data;
}

export async function listServiceOrders(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  status?: string;
  customerUuid?: string;
  from?: string;
  to?: string;
}) {
  const url = new URL(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/service-orders`);
  if (input.status) url.searchParams.set('status', input.status);
  if (input.customerUuid) url.searchParams.set('customerUuid', input.customerUuid);
  if (input.from) url.searchParams.set('from', input.from);
  if (input.to) url.searchParams.set('to', input.to);
  const response = await fetch(url, { headers: headers(input.accessToken, input.deviceUuid) });
  return (await parseResponse(response, ordersResponseSchema)).data;
}

export async function getServiceOrderDetail(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; orderUuid: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/service-orders/${input.orderUuid}`, {
    headers: headers(input.accessToken, input.deviceUuid),
  });
  return (await parseResponse(response, orderDetailResponseSchema)).data;
}

export async function createServiceOrder(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  customerUuid?: string;
  branchUuid: string;
  discountAmount?: string;
  items: Array<{ serviceUuid: string; quantity: string; unitPrice: string }>;
}) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/service-orders`, {
    method: 'POST',
    headers: { ...headers(input.accessToken, input.deviceUuid), 'Content-Type': 'application/json' },
    body: JSON.stringify(input),
  });
  return (await parseResponse(response, genericSuccessSchema)).data;
}

export async function assignServiceStaff(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  orderItemUuid: string;
  assignments: Array<{ staffUserUuid: string; contributionPercentage: string; assignmentRole?: string; startedAt?: string; completedAt?: string; notes?: string }>;
}) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/service-order-items/${input.orderItemUuid}/staff-assignments`, {
    method: 'POST',
    headers: { ...headers(input.accessToken, input.deviceUuid), 'Content-Type': 'application/json' },
    body: JSON.stringify({ assignments: input.assignments }),
  });
  return (await parseResponse(response, genericSuccessSchema)).data;
}

async function postSimple(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; path: string; body?: Record<string, unknown> }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}${input.path}`, {
    method: 'POST',
    headers: { ...headers(input.accessToken, input.deviceUuid), 'Content-Type': 'application/json' },
    body: JSON.stringify(input.body ?? {}),
  });
  return (await parseResponse(response, genericSuccessSchema)).data;
}

export function startServiceOrder(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; orderUuid: string }) {
  return postSimple({ ...input, path: `/service-orders/${input.orderUuid}/start` });
}

export function completeServiceOrder(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; orderUuid: string }) {
  return postSimple({ ...input, path: `/service-orders/${input.orderUuid}/complete` });
}

export function confirmServiceOrderPayment(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; orderUuid: string }) {
  return postSimple({ ...input, path: `/service-orders/${input.orderUuid}/confirm-payment` });
}

export function requestServiceAllocation(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; orderUuid: string; requestedByUserUuid?: string }) {
  return postSimple({ ...input, path: `/service-orders/${input.orderUuid}/request-allocation`, body: { requestedByUserUuid: input.requestedByUserUuid } });
}

export function approveServiceAllocation(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; orderUuid: string; approvedByUserUuid: string }) {
  return postSimple({ ...input, path: `/service-orders/${input.orderUuid}/approve-allocation`, body: { approvedByUserUuid: input.approvedByUserUuid } });
}

export function cancelServiceOrder(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; orderUuid: string; reason: string }) {
  return postSimple({ ...input, path: `/service-orders/${input.orderUuid}/cancel`, body: { reason: input.reason } });
}

export async function recordServicePayment(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  orderUuid: string;
  amount: string;
  paymentMethod: string;
  reference?: string;
}) {
  return postSimple({
    ...input,
    path: `/service-orders/${input.orderUuid}/payments`,
    body: { amount: input.amount, paymentMethod: input.paymentMethod, reference: input.reference },
  });
}

export async function recordServiceExpense(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  orderUuid: string;
  category: string;
  description: string;
  amount: string;
  paidBy: string;
  paymentMethod: string;
  receiptAttachmentPath?: string;
  approvalStatus?: string;
}) {
  return postSimple({
    ...input,
    path: `/service-orders/${input.orderUuid}/expenses`,
    body: {
      category: input.category,
      description: input.description,
      amount: input.amount,
      paidBy: input.paidBy,
      paymentMethod: input.paymentMethod,
      receiptAttachmentPath: input.receiptAttachmentPath,
      approvalStatus: input.approvalStatus,
    },
  });
}

export async function getServiceAllocationPreview(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; orderUuid: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/service-orders/${input.orderUuid}/allocation-preview`, {
    headers: headers(input.accessToken, input.deviceUuid),
  });
  return (await parseResponse(response, previewResponseSchema)).data;
}

export async function listServiceAssignments(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  staffUserUuid?: string;
}) {
  const url = new URL(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/service-assignments`);
  if (input.staffUserUuid) url.searchParams.set('staffUserUuid', input.staffUserUuid);
  const response = await fetch(url, { headers: headers(input.accessToken, input.deviceUuid) });
  return (await parseResponse(response, assignmentsResponseSchema)).data;
}

export async function listServicePayments(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  orderUuid?: string;
  from?: string;
  to?: string;
}) {
  const url = new URL(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/service-payments`);
  if (input.orderUuid) url.searchParams.set('orderUuid', input.orderUuid);
  if (input.from) url.searchParams.set('from', input.from);
  if (input.to) url.searchParams.set('to', input.to);
  const response = await fetch(url, { headers: headers(input.accessToken, input.deviceUuid) });
  return (await parseResponse(response, paymentsResponseSchema)).data;
}

export async function listServiceExpenses(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  orderUuid?: string;
  from?: string;
  to?: string;
  approvalStatus?: string;
}) {
  const url = new URL(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/service-expenses`);
  if (input.orderUuid) url.searchParams.set('orderUuid', input.orderUuid);
  if (input.from) url.searchParams.set('from', input.from);
  if (input.to) url.searchParams.set('to', input.to);
  if (input.approvalStatus) url.searchParams.set('approvalStatus', input.approvalStatus);
  const response = await fetch(url, { headers: headers(input.accessToken, input.deviceUuid) });
  return (await parseResponse(response, expensesResponseSchema)).data;
}

export async function listRevenueAllocations(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  orderUuid?: string;
  status?: string;
  from?: string;
  to?: string;
}) {
  const url = new URL(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/service-revenue-allocations`);
  if (input.orderUuid) url.searchParams.set('orderUuid', input.orderUuid);
  if (input.status) url.searchParams.set('status', input.status);
  if (input.from) url.searchParams.set('from', input.from);
  if (input.to) url.searchParams.set('to', input.to);
  const response = await fetch(url, { headers: headers(input.accessToken, input.deviceUuid) });
  return (await parseResponse(response, allocationsResponseSchema)).data;
}

export async function listStaffEarnings(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string }) {
  const response = await fetch(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/staff-earnings`, {
    headers: headers(input.accessToken, input.deviceUuid),
  });
  return (await parseResponse(response, staffEarningsResponseSchema)).data;
}

export async function listStaffSettlements(input: {
  shopBaseUrl: string;
  accessToken: string;
  deviceUuid: string;
  staffUserUuid?: string;
  from?: string;
  to?: string;
}) {
  const url = new URL(`${normalizeBaseUrl(input.shopBaseUrl)}${apiBasePath}/staff-settlements`);
  if (input.staffUserUuid) url.searchParams.set('staffUserUuid', input.staffUserUuid);
  if (input.from) url.searchParams.set('from', input.from);
  if (input.to) url.searchParams.set('to', input.to);
  const response = await fetch(url, { headers: headers(input.accessToken, input.deviceUuid) });
  return (await parseResponse(response, staffSettlementsResponseSchema)).data;
}

export function settleStaffEarnings(input: { shopBaseUrl: string; accessToken: string; deviceUuid: string; staffUserUuid: string; amount: string }) {
  return postSimple({
    ...input,
    path: `/staff/${input.staffUserUuid}/settlements`,
    body: { amount: input.amount },
  });
}
