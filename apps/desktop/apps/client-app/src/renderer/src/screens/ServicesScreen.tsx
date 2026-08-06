import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { AlertTriangle, BadgeInfo, Check, ChevronRight, DollarSign, Receipt, UserCheck } from 'lucide-react';
import { useClientUiStore } from '../state/client-ui-store';
import {
  addStaffEarningAdjustment,
  approveServiceAllocation,
  assignServiceStaff,
  cancelServiceOrder,
  completeServiceOrder,
  confirmServiceOrderPayment,
  createServiceOrder,
  getServiceAllocationPreview,
  getServiceOrderDetail,
  getStaffEarningsOverview,
  getStaffSettlementStatement,
  getCombinedStaffPerformance,
  getServicesCatalog,
  getServicesDashboard,
  getServicesModuleAccess,
  listRevenueAllocations,
  listServiceAssignments,
  listServiceCustomers,
  listServiceExpenses,
  listServiceOrders,
  listServicePayments,
  listServiceStaff,
  listStaffEarnings,
  listStaffSettlements,
  recordServiceExpense,
  recordServicePayment,
  requestServiceAllocation,
  previewStaffSettlement,
  settleStaffEarnings,
  startServiceOrder,
  type RevenueAllocation,
  type ServiceAssignment,
  type CombinedStaffPerformance,
  type ServiceOrder,
} from '../services/services-service';

type ServicesPageId =
  | 'dashboard'
  | 'service-orders'
  | 'new-service-order'
  | 'assigned-services'
  | 'service-payments'
  | 'service-expenses'
  | 'revenue-allocations'
  | 'staff-earnings'
  | 'staff-settlements'
  | 'staff-performance';

type CatalogItemDraft = {
  serviceUuid: string;
  quantity: string;
  unitPrice: string;
  assignmentRole: string;
  assignedStaff: Array<{ staffUserUuid: string; contributionPercentage: string; assignmentRole: string }>;
};

type ServicesAuth = { shopBaseUrl: string; accessToken: string; deviceUuid: string; branchUuid?: string };

const pages: Array<{ id: ServicesPageId; label: string; description: string }> = [
  { id: 'dashboard', label: 'Services Dashboard', description: 'Overview of service operations and balances.' },
  { id: 'service-orders', label: 'Service Orders', description: 'Browse lifecycle states from pending to refunded.' },
  { id: 'new-service-order', label: 'New Service Order', description: 'Create orders, add items, assign staff, and capture payment.' },
  { id: 'assigned-services', label: 'Assigned Services', description: 'Active staff assignments with percentages and timing.' },
  { id: 'service-payments', label: 'Service Payments', description: 'Deposits, receipts, and payment confirmation.' },
  { id: 'service-expenses', label: 'Service Expenses', description: 'Direct service expenses distinct from general expenses.' },
  { id: 'revenue-allocations', label: 'Revenue Allocations', description: 'Preview and approved allocation output.' },
  { id: 'staff-earnings', label: 'Staff Earnings', description: 'Outstanding earnings and balances.' },
  { id: 'staff-settlements', label: 'Staff Settlements', description: 'Payment settlements and completion history.' },
  { id: 'staff-performance', label: 'Staff Performance', description: 'Combined sales, service, and operational indicators with neutral labels.' },
];

const stateTone: Record<string, string> = {
  pending: 'bg-slate-100 text-slate-700',
  assigned: 'bg-blue-100 text-blue-800',
  in_progress: 'bg-amber-100 text-amber-800',
  completed: 'bg-emerald-100 text-emerald-800',
  cancelled: 'bg-rose-100 text-rose-800',
  refunded: 'bg-fuchsia-100 text-fuchsia-800',
};

const formatMoney = (value: number | string) => new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(Number(value));

function canUseServices(access: Awaited<ReturnType<typeof getServicesModuleAccess>>, permissions: string[]) {
  return access.enabled && permissions.some((permission) => [
    'services.view',
    'services.manage',
    'view-services',
    'services.manage_services',
    'services.create_service_order',
    'services.complete_service',
    'services.generate_revenue_allocation',
    'services.view_staff_earnings',
  ].includes(permission));
}

export function ServicesScreen() {
  const queryClient = useQueryClient();
  const shopBaseUrl = useClientUiStore((state) => state.shopBaseUrl);
  const deviceUuid = useClientUiStore((state) => state.deviceUuid);
  const shopStatus = useClientUiStore((state) => state.shopStatus);
  const permissions = useClientUiStore((state) => state.permissions);
  const currentUser = useClientUiStore((state) => state.currentUser);

  const sessionQuery = useQuery({
    queryKey: ['services-session'],
    enabled: Boolean(shopBaseUrl && deviceUuid),
    queryFn: async () => window.marketlensClient.session.load() as Promise<{ shopBaseUrl: string; token: string; deviceUuid: string; userUuid?: string }>,
  });

  const activeUserUuid = currentUser?.uuid ?? sessionQuery.data?.userUuid;

  const authContext = useMemo<ServicesAuth | null>(() => {
    const session = sessionQuery.data;
    if (!session || !shopBaseUrl || !deviceUuid) {
      return null;
    }

    return {
      shopBaseUrl: session.shopBaseUrl || shopBaseUrl,
      accessToken: session.token,
      deviceUuid,
      branchUuid: shopStatus?.branchUuid,
    };
  }, [deviceUuid, sessionQuery.data, shopBaseUrl, shopStatus?.branchUuid]);

  const [activePage, setActivePage] = useState<ServicesPageId>('dashboard');
  const [selectedOrderUuid, setSelectedOrderUuid] = useState('');
  const [selectedOrderItemUuid, setSelectedOrderItemUuid] = useState('');
  const [selectedStaffUserUuid, setSelectedStaffUserUuid] = useState('');
  const [selectedOrderStatus, setSelectedOrderStatus] = useState<string>('all');
  const [fromDate, setFromDate] = useState('');
  const [toDate, setToDate] = useState('');
  const [customerUuid, setCustomerUuid] = useState('');
  const [discountAmount, setDiscountAmount] = useState('0');
  const [paymentMethod, setPaymentMethod] = useState('cash');
  const [paymentAmount, setPaymentAmount] = useState('');
  const [paymentReference, setPaymentReference] = useState('');
  const [expenseCategory, setExpenseCategory] = useState('');
  const [expenseDescription, setExpenseDescription] = useState('');
  const [expenseAmount, setExpenseAmount] = useState('');
  const [expensePaidBy, setExpensePaidBy] = useState('');
  const [expensePaymentMethod, setExpensePaymentMethod] = useState('cash');
  const [expenseReceiptPath, setExpenseReceiptPath] = useState('');
  const [expenseApprovalStatus, setExpenseApprovalStatus] = useState('pending');
  const [settlementAmount, setSettlementAmount] = useState('');
  const [earningsPeriod, setEarningsPeriod] = useState<'day' | 'week' | 'month'>('month');
  const [adjustmentType, setAdjustmentType] = useState<'bonus' | 'adjustment' | 'deduction'>('bonus');
  const [adjustmentAmount, setAdjustmentAmount] = useState('');
  const [adjustmentNote, setAdjustmentNote] = useState('');
  const [orderNote, setOrderNote] = useState('');
  const [allocationTargetOrderUuid, setAllocationTargetOrderUuid] = useState('');
  const [allocationTargetOrderItemUuid, setAllocationTargetOrderItemUuid] = useState('');
  const [allocationRequestedByUserUuid, setAllocationRequestedByUserUuid] = useState('');
  const [allocationApprovedByUserUuid, setAllocationApprovedByUserUuid] = useState('');
  const [revenueAllocationStatus, setRevenueAllocationStatus] = useState('all');
    const [selectedAssignmentRole, setSelectedAssignmentRole] = useState('service_lead');
  const [newOrderItems, setNewOrderItems] = useState<CatalogItemDraft[]>([]);

  const moduleAccessQuery = useQuery({
    queryKey: ['services-module-access'],
    enabled: Boolean(authContext),
    queryFn: async () => getServicesModuleAccess(authContext!),
  });

  const moduleAccess = moduleAccessQuery.data;
  const showModule = Boolean(moduleAccess && authContext && canUseServices(moduleAccess, permissions));
  const hasPermission = (...keys: string[]) => permissions.some((permission) => keys.includes(permission));
  const canViewEarnings = hasPermission('services.view_staff_earnings', 'services.manage_services', 'services.manage', 'services.view', 'view-services');
  const canReviewAllocations = hasPermission('services.generate_revenue_allocation', 'services.manage_services', 'services.manage');
  const canApproveAllocations = hasPermission('services.approve_allocation', 'services.manage_services', 'services.manage');
  const canPrepareSettlement = hasPermission('services.settle_staff_earnings', 'services.view_staff_earnings', 'services.manage_services');
  const canRecordSettlement = hasPermission('services.settle_staff_earnings', 'services.manage_services');
  const canPrintSettlementStatement = canPrepareSettlement;

  const dashboardQuery = useQuery({
    queryKey: ['services-dashboard'],
    enabled: showModule,
    queryFn: async () => getServicesDashboard(authContext!),
  });

  const catalogQuery = useQuery({
    queryKey: ['services-catalog'],
    enabled: showModule,
    queryFn: async () => getServicesCatalog(authContext!),
  });

  const customersQuery = useQuery({
    queryKey: ['services-customers'],
    enabled: showModule,
    queryFn: async () => listServiceCustomers(authContext!),
  });

  const staffQuery = useQuery({
    queryKey: ['services-staff'],
    enabled: showModule,
    queryFn: async () => listServiceStaff(authContext!),
  });

  const ordersQuery = useQuery({
    queryKey: ['service-orders', selectedOrderStatus, fromDate, toDate, customerUuid],
    enabled: showModule,
    queryFn: async () => listServiceOrders({
      ...authContext!,
      status: selectedOrderStatus === 'all' ? undefined : selectedOrderStatus,
      customerUuid: customerUuid || undefined,
      from: fromDate || undefined,
      to: toDate || undefined,
    }),
  });

  const selectedOrderQuery = useQuery({
    queryKey: ['service-order-detail', selectedOrderUuid],
    enabled: showModule && Boolean(selectedOrderUuid),
    queryFn: async () => getServiceOrderDetail({ ...authContext!, orderUuid: selectedOrderUuid }),
  });

  const assignedServicesQuery = useQuery({
    queryKey: ['assigned-services', selectedStaffUserUuid],
    enabled: showModule,
    queryFn: async () => listServiceAssignments({ ...authContext!, staffUserUuid: selectedStaffUserUuid || undefined }),
  });

  const paymentsQuery = useQuery({
    queryKey: ['service-payments', selectedOrderUuid, fromDate, toDate],
    enabled: showModule,
    queryFn: async () => listServicePayments({ ...authContext!, orderUuid: selectedOrderUuid || undefined, from: fromDate || undefined, to: toDate || undefined }),
  });

  const expensesQuery = useQuery({
    queryKey: ['service-expenses', selectedOrderUuid, fromDate, toDate, expenseApprovalStatus],
    enabled: showModule,
    queryFn: async () => listServiceExpenses({ ...authContext!, orderUuid: selectedOrderUuid || undefined, from: fromDate || undefined, to: toDate || undefined, approvalStatus: expenseApprovalStatus === 'all' ? undefined : expenseApprovalStatus }),
  });

  const allocationsQuery = useQuery({
    queryKey: ['service-revenue-allocations', selectedOrderUuid, revenueAllocationStatus, fromDate, toDate],
    enabled: showModule,
    queryFn: async () => listRevenueAllocations({ ...authContext!, orderUuid: selectedOrderUuid || undefined, status: revenueAllocationStatus === 'all' ? undefined : revenueAllocationStatus, from: fromDate || undefined, to: toDate || undefined }),
  });

  const earningsQuery = useQuery({
    queryKey: ['staff-earnings'],
    enabled: showModule,
    queryFn: async () => listStaffEarnings(authContext!),
  });

  const earningsOverviewQuery = useQuery({
    queryKey: ['staff-earnings-overview', selectedStaffUserUuid, fromDate, toDate, earningsPeriod],
    enabled: showModule && canViewEarnings,
    queryFn: async () => getStaffEarningsOverview({
      ...authContext!,
      staffUserUuid: selectedStaffUserUuid || undefined,
      from: fromDate || undefined,
      to: toDate || undefined,
      period: earningsPeriod,
    }),
  });

  const settlementsQuery = useQuery({
    queryKey: ['staff-settlements', selectedStaffUserUuid, fromDate, toDate],
    enabled: showModule,
    queryFn: async () => listStaffSettlements({ ...authContext!, staffUserUuid: selectedStaffUserUuid || undefined, from: fromDate || undefined, to: toDate || undefined }),
  });

  const settlementPreviewQuery = useQuery({
    queryKey: ['staff-settlement-preview', selectedStaffUserUuid, settlementAmount],
    enabled: showModule && canPrepareSettlement && Boolean(selectedStaffUserUuid),
    queryFn: async () => previewStaffSettlement({
      ...authContext!,
      staffUserUuid: selectedStaffUserUuid,
      amount: settlementAmount || undefined,
    }),
  });

  const settlementStatementQuery = useQuery({
    queryKey: ['staff-settlement-statement', selectedStaffUserUuid, fromDate, toDate],
    enabled: showModule && canPrintSettlementStatement && Boolean(selectedStaffUserUuid),
    queryFn: async () => getStaffSettlementStatement({
      ...authContext!,
      staffUserUuid: selectedStaffUserUuid,
      from: fromDate || undefined,
      to: toDate || undefined,
    }),
  });

  const staffPerformanceQuery = useQuery({
    queryKey: ['staff-performance-combined', fromDate, toDate],
    enabled: showModule,
    queryFn: async () => getCombinedStaffPerformance({
      ...authContext!,
      from: fromDate || undefined,
      to: toDate || undefined,
    }),
  });

  const previewQuery = useQuery({
    queryKey: ['service-allocation-preview', allocationTargetOrderUuid],
    enabled: showModule && Boolean(allocationTargetOrderUuid),
    queryFn: async () => getServiceAllocationPreview({ ...authContext!, orderUuid: allocationTargetOrderUuid }),
  });

  const createOrderMutation = useMutation({
    mutationFn: async () => {
      if (!authContext) throw new Error('Missing services session');
      if (newOrderItems.length === 0) throw new Error('Add at least one service item');
      return createServiceOrder({
        ...authContext,
        customerUuid: customerUuid || undefined,
        branchUuid: authContext.branchUuid ?? shopStatus?.branchUuid ?? '',
        discountAmount: discountAmount || '0',
        items: newOrderItems.map((item) => ({
          serviceUuid: item.serviceUuid,
          quantity: item.quantity,
          unitPrice: item.unitPrice,
        })),
      });
    },
    onSuccess: async () => {
      setNewOrderItems([]);
      await queryClient.invalidateQueries({ queryKey: ['service-orders'] });
      await queryClient.invalidateQueries({ queryKey: ['services-dashboard'] });
    },
  });

  const assignStaffMutation = useMutation({
    mutationFn: async () => {
      if (!selectedOrderItemUuid) throw new Error('Select an order item first');
      return assignServiceStaff({
        ...authContext!,
        orderItemUuid: selectedOrderItemUuid,
        assignments: newOrderItems.flatMap((item) => item.assignedStaff).map((assignment) => ({
          staffUserUuid: assignment.staffUserUuid,
          contributionPercentage: assignment.contributionPercentage,
          assignmentRole: assignment.assignmentRole,
        })),
      });
    },
  });

  const recordPaymentMutation = useMutation({
    mutationFn: async () => {
      if (!selectedOrderUuid) throw new Error('Select an order first');
      if (!paymentAmount.trim()) throw new Error('Payment amount is required');
      return recordServicePayment({
        ...authContext!,
        orderUuid: selectedOrderUuid,
        amount: paymentAmount,
        paymentMethod,
        reference: paymentReference || undefined,
      });
    },
    onSuccess: async () => {
      setPaymentAmount('');
      setPaymentReference('');
      await queryClient.invalidateQueries({ queryKey: ['service-orders'] });
      await queryClient.invalidateQueries({ queryKey: ['service-payments'] });
      await queryClient.invalidateQueries({ queryKey: ['services-dashboard'] });
    },
  });

  const recordExpenseMutation = useMutation({
    mutationFn: async () => {
      if (!selectedOrderUuid) throw new Error('Select an order first');
      if (!expenseAmount.trim()) throw new Error('Expense amount is required');
      return recordServiceExpense({
        ...authContext!,
        orderUuid: selectedOrderUuid,
        category: expenseCategory || 'Direct expense',
        description: expenseDescription,
        amount: expenseAmount,
        paidBy: expensePaidBy || 'shop',
        paymentMethod: expensePaymentMethod,
        receiptAttachmentPath: expenseReceiptPath || undefined,
        approvalStatus: expenseApprovalStatus,
      });
    },
    onSuccess: async () => {
      setExpenseCategory('');
      setExpenseDescription('');
      setExpenseAmount('');
      setExpensePaidBy('');
      setExpenseReceiptPath('');
      await queryClient.invalidateQueries({ queryKey: ['service-expenses'] });
      await queryClient.invalidateQueries({ queryKey: ['services-dashboard'] });
    },
  });

  const requestAllocationMutation = useMutation({
    mutationFn: async () => {
      if (!selectedOrderUuid) throw new Error('Select a completed order first');
      return requestServiceAllocation({
        ...authContext!,
        orderUuid: selectedOrderUuid,
        requestedByUserUuid: allocationRequestedByUserUuid || activeUserUuid,
      });
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['service-revenue-allocations'] });
      await queryClient.invalidateQueries({ queryKey: ['services-dashboard'] });
    },
  });

  const approveAllocationMutation = useMutation({
    mutationFn: async () => {
      if (!selectedOrderUuid) throw new Error('Select an order first');
      return approveServiceAllocation({
        ...authContext!,
        orderUuid: selectedOrderUuid,
        approvedByUserUuid: allocationApprovedByUserUuid || activeUserUuid || '',
      });
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['service-revenue-allocations'] });
      await queryClient.invalidateQueries({ queryKey: ['staff-earnings'] });
      await queryClient.invalidateQueries({ queryKey: ['services-dashboard'] });
    },
  });

  const startOrderMutation = useMutation({
    mutationFn: async (orderUuid: string) => startServiceOrder({ ...authContext!, orderUuid }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['service-orders'] });
      await queryClient.invalidateQueries({ queryKey: ['services-dashboard'] });
    },
  });

  const completeOrderMutation = useMutation({
    mutationFn: async (orderUuid: string) => completeServiceOrder({ ...authContext!, orderUuid }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['service-orders'] });
      await queryClient.invalidateQueries({ queryKey: ['services-dashboard'] });
    },
  });

  const confirmPaymentMutation = useMutation({
    mutationFn: async (orderUuid: string) => confirmServiceOrderPayment({ ...authContext!, orderUuid }),
  });

  const cancelOrderMutation = useMutation({
    mutationFn: async (orderUuid: string) => cancelServiceOrder({ ...authContext!, orderUuid, reason: 'Cancelled from Services screen' }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['service-orders'] });
      await queryClient.invalidateQueries({ queryKey: ['services-dashboard'] });
    },
  });

  const settleMutation = useMutation({
    mutationFn: async () => {
      if (!selectedStaffUserUuid) throw new Error('Select a staff member first');
      if (!settlementAmount.trim()) throw new Error('Settlement amount is required');
      return settleStaffEarnings({ ...authContext!, staffUserUuid: selectedStaffUserUuid, amount: settlementAmount });
    },
    onSuccess: async () => {
      setSettlementAmount('');
      await queryClient.invalidateQueries({ queryKey: ['staff-settlements'] });
      await queryClient.invalidateQueries({ queryKey: ['staff-earnings'] });
      await queryClient.invalidateQueries({ queryKey: ['staff-earnings-overview'] });
      await queryClient.invalidateQueries({ queryKey: ['staff-settlement-preview'] });
      await queryClient.invalidateQueries({ queryKey: ['staff-settlement-statement'] });
      await queryClient.invalidateQueries({ queryKey: ['staff-performance-combined'] });
    },
  });

  const adjustmentMutation = useMutation({
    mutationFn: async () => {
      if (!selectedStaffUserUuid) throw new Error('Select a staff member first');
      if (!adjustmentAmount.trim()) throw new Error('Adjustment amount is required');
      return addStaffEarningAdjustment({
        ...authContext!,
        staffUserUuid: selectedStaffUserUuid,
        type: adjustmentType,
        amount: adjustmentAmount,
        note: adjustmentNote || undefined,
      });
    },
    onSuccess: async () => {
      setAdjustmentAmount('');
      setAdjustmentNote('');
      await queryClient.invalidateQueries({ queryKey: ['staff-earnings'] });
      await queryClient.invalidateQueries({ queryKey: ['staff-earnings-overview'] });
      await queryClient.invalidateQueries({ queryKey: ['staff-performance-combined'] });
    },
  });

  if (!showModule) {
    return (
      <div className="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
        Services is hidden until the plan, business setting, branch setting, and user permission all enable it.
      </div>
    );
  }

  const dashboard = dashboardQuery.data;
  const orders = ordersQuery.data ?? [];
  const allocations = allocationsQuery.data ?? [];
  const payments = paymentsQuery.data ?? [];
  const expenses = expensesQuery.data ?? [];
  const staffEarnings = earningsQuery.data ?? [];
  const settlements = settlementsQuery.data ?? [];
  const selectedOrder = selectedOrderQuery.data;
  const preview = previewQuery.data;

  const addCatalogItem = (serviceUuid: string, unitPrice: string) => {
    setNewOrderItems((current) => [
      ...current,
      { serviceUuid, quantity: '1', unitPrice, assignmentRole: 'service_lead', assignedStaff: [] },
    ]);
  };

  return (
    <div className="space-y-6">
      <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
          <div>
            <h2 className="text-xl font-semibold text-slate-900">Services Dashboard</h2>
            <p className="text-sm text-slate-500">Service order workflow, allocation, expenses, and staff earnings.</p>
          </div>
          <div className="flex flex-wrap gap-2 text-sm">
            {pages.map((page) => (
              <button
                key={page.id}
                type="button"
                className={`rounded-md border px-3 py-1.5 ${activePage === page.id ? 'border-blue-600 bg-blue-50 text-blue-900' : 'border-slate-200 bg-white text-slate-700'}`}
                onClick={() => setActivePage(page.id)}
              >
                {page.label}
              </button>
            ))}
          </div>
        </div>

        <div className="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
          {[
            ['Pending', dashboard?.pending ?? 0],
            ['Assigned', dashboard?.assigned ?? 0],
            ['In progress', dashboard?.inProgress ?? 0],
            ['Completed', dashboard?.completed ?? 0],
            ['Gross revenue', formatMoney(dashboard?.grossRevenue ?? 0)],
            ['Outstanding earnings', formatMoney(dashboard?.outstandingStaffEarnings ?? 0)],
          ].map(([label, value]) => (
            <div key={String(label)} className="rounded-lg bg-slate-50 p-3">
              <div className="text-xs uppercase tracking-wide text-slate-500">{label}</div>
              <div className="mt-1 text-lg font-semibold text-slate-900">{String(value)}</div>
            </div>
          ))}
        </div>
      </section>

      {activePage === 'dashboard' ? (
        <section className="grid gap-4 lg:grid-cols-3">
          <Panel title="Service States" icon={<BadgeInfo className="h-4 w-4" />}>
            <KeyValue label="pending" value={dashboard?.pending ?? 0} />
            <KeyValue label="assigned" value={dashboard?.assigned ?? 0} />
            <KeyValue label="in_progress" value={dashboard?.inProgress ?? 0} />
            <KeyValue label="completed" value={dashboard?.completed ?? 0} />
            <KeyValue label="cancelled" value={dashboard?.cancelled ?? 0} />
            <KeyValue label="refunded" value={dashboard?.refunded ?? 0} />
          </Panel>
          <Panel title="Revenue Allocation Preview" icon={<DollarSign className="h-4 w-4" />}>
            {preview ? (
              <div className="space-y-2 text-sm">
                <KeyValue label="Gross amount" value={formatMoney(preview.grossAmount)} />
                <KeyValue label="Discount" value={formatMoney(preview.discountAmount)} />
                <KeyValue label="Direct expenses" value={formatMoney(preview.directExpenses)} />
                <KeyValue label="Calculation basis" value={preview.calculationBasis} />
                <KeyValue label="Distributable amount" value={formatMoney(preview.distributableAmount)} />
                <KeyValue label="Staff share" value={formatMoney(preview.staffShare)} />
                <KeyValue label="Business share" value={formatMoney(preview.businessShare)} />
                <KeyValue label="Expense-reserve share" value={formatMoney(preview.expenseReserveShare)} />
              </div>
            ) : (
              <div className="text-sm text-slate-500">Select an order to preview the allocation result.</div>
            )}
          </Panel>
          <Panel title="Module Gate" icon={<Check className="h-4 w-4" />}>
            <KeyValue label="Plan allowed" value={String(moduleAccess?.planAllowed ?? false)} />
            <KeyValue label="Business enabled" value={String(moduleAccess?.businessEnabled ?? false)} />
            <KeyValue label="Branch enabled" value={String(moduleAccess?.branchEnabled ?? false)} />
            <KeyValue label="Permission granted" value={String(permissions.some((permission) => ['services.view', 'services.manage', 'view-services'].includes(permission)))} />
          </Panel>
        </section>
      ) : null}

      {activePage === 'service-orders' ? (
        <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <PageHeader title="Service Orders" description="Track pending, assigned, in-progress, completed, cancelled, and refunded orders." />
          <div className="mb-4 flex flex-wrap gap-2 text-sm">
            {['all', 'pending', 'assigned', 'in_progress', 'completed', 'cancelled', 'refunded'].map((status) => (
              <button
                key={status}
                type="button"
                className={`rounded-md border px-3 py-1.5 ${selectedOrderStatus === status ? 'border-blue-600 bg-blue-50 text-blue-900' : 'border-slate-200 bg-white text-slate-700'}`}
                onClick={() => setSelectedOrderStatus(status)}
              >
                {status}
              </button>
            ))}
          </div>
          <OrderTable orders={orders} onSelect={setSelectedOrderUuid} onStart={(orderUuid) => startOrderMutation.mutate(orderUuid)} onComplete={(orderUuid) => completeOrderMutation.mutate(orderUuid)} onCancel={(orderUuid) => cancelOrderMutation.mutate(orderUuid)} />
        </section>
      ) : null}

      {activePage === 'new-service-order' ? (
        <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <PageHeader title="New Service Order" description="Select customer, add service items, assign staff, set percentages, then capture payment and complete the order." />
          <div className="grid gap-4 lg:grid-cols-2">
            <div className="space-y-3">
              <label className="flex flex-col gap-1 text-sm text-slate-700">
                Customer
                <select className="rounded-md border border-slate-300 bg-white px-3 py-2" value={customerUuid} onChange={(event) => setCustomerUuid(event.target.value)}>
                  <option value="">Select customer</option>
                  {(customersQuery.data ?? []).map((customer) => <option key={customer.uuid} value={customer.uuid}>{customer.name}</option>)}
                </select>
              </label>

              <label className="flex flex-col gap-1 text-sm text-slate-700">
                Discount amount
                <input className="rounded-md border border-slate-300 px-3 py-2" value={discountAmount} onChange={(event) => setDiscountAmount(event.target.value)} />
              </label>

              <div className="rounded-lg border border-slate-200 bg-slate-50 p-3">
                <div className="mb-2 text-sm font-semibold text-slate-900">Service catalog</div>
                <div className="space-y-2">
                  {(catalogQuery.data?.services ?? []).slice(0, 10).map((service) => (
                    <button key={service.uuid} type="button" className="flex w-full items-center justify-between rounded-md border border-slate-200 bg-white px-3 py-2 text-left text-sm" onClick={() => addCatalogItem(service.uuid, service.currentPrice ?? '0')}>
                      <span>{service.name}</span>
                      <span className="text-slate-500">{formatMoney(service.currentPrice ?? '0')}</span>
                    </button>
                  ))}
                </div>
              </div>
            </div>

            <div className="space-y-3">
              <div className="text-sm font-semibold text-slate-900">Draft order items</div>
              <label className="flex flex-col gap-1 text-sm text-slate-700">
                Selected order item UUID for assignment
                <input className="rounded-md border border-slate-300 px-3 py-2 text-sm" value={selectedOrderItemUuid} onChange={(event) => setSelectedOrderItemUuid(event.target.value)} placeholder="Paste the order item UUID" />
              </label>
              <label className="flex flex-col gap-1 text-sm text-slate-700">
                Default assignment role
                <input className="rounded-md border border-slate-300 px-3 py-2 text-sm" value={selectedAssignmentRole} onChange={(event) => setSelectedAssignmentRole(event.target.value)} />
              </label>
              <div className="space-y-2">
                {newOrderItems.map((item, index) => {
                  const service = catalogQuery.data?.services.find((candidate) => candidate.uuid === item.serviceUuid);
                  return (
                    <div key={`${item.serviceUuid}-${index}`} className="rounded-lg border border-slate-200 p-3">
                      <div className="flex items-center justify-between">
                        <div className="font-medium text-slate-900">{service?.name ?? item.serviceUuid}</div>
                        <button type="button" className="text-sm text-rose-700" onClick={() => setNewOrderItems((current) => current.filter((_, currentIndex) => currentIndex !== index))}>Remove</button>
                      </div>
                      <div className="mt-2 grid gap-2 md:grid-cols-3">
                        <input className="rounded-md border border-slate-300 px-3 py-2 text-sm" value={item.quantity} onChange={(event) => setNewOrderItems((current) => current.map((currentItem, currentIndex) => currentIndex === index ? { ...currentItem, quantity: event.target.value } : currentItem))} placeholder="Quantity" />
                        <input className="rounded-md border border-slate-300 px-3 py-2 text-sm" value={item.unitPrice} onChange={(event) => setNewOrderItems((current) => current.map((currentItem, currentIndex) => currentIndex === index ? { ...currentItem, unitPrice: event.target.value } : currentItem))} placeholder="Unit price" />
                        <input className="rounded-md border border-slate-300 px-3 py-2 text-sm" value={item.assignmentRole} onChange={(event) => setNewOrderItems((current) => current.map((currentItem, currentIndex) => currentIndex === index ? { ...currentItem, assignmentRole: event.target.value } : currentItem))} placeholder="Assignment role" />
                      </div>
                      <div className="mt-3 space-y-2">
                        <div className="text-xs uppercase tracking-wide text-slate-500">Assigned staff</div>
                        {(staffQuery.data ?? []).slice(0, 3).map((staff) => (
                          <div key={staff.uuid} className="grid grid-cols-[1fr_120px_140px] gap-2">
                            <label className="text-sm text-slate-700">
                              <input type="checkbox" className="mr-2" checked={item.assignedStaff.some((assignment) => assignment.staffUserUuid === staff.uuid)} onChange={(event) => setNewOrderItems((current) => current.map((currentItem, currentIndex) => currentIndex === index ? {
                                ...currentItem,
                                assignedStaff: event.target.checked
                                  ? [...currentItem.assignedStaff.filter((assignment) => assignment.staffUserUuid !== staff.uuid), { staffUserUuid: staff.uuid, contributionPercentage: '100', assignmentRole: item.assignmentRole }]
                                  : currentItem.assignedStaff.filter((assignment) => assignment.staffUserUuid !== staff.uuid),
                              } : currentItem))} />
                              {staff.displayName ?? staff.username}
                            </label>
                            <input className="rounded-md border border-slate-300 px-3 py-2 text-sm" value={item.assignedStaff.find((assignment) => assignment.staffUserUuid === staff.uuid)?.contributionPercentage ?? ''} onChange={(event) => setNewOrderItems((current) => current.map((currentItem, currentIndex) => currentIndex === index ? {
                              ...currentItem,
                              assignedStaff: currentItem.assignedStaff.map((assignment) => assignment.staffUserUuid === staff.uuid ? { ...assignment, contributionPercentage: event.target.value } : assignment),
                            } : currentItem))} placeholder="% contribution" />
                            <input className="rounded-md border border-slate-300 px-3 py-2 text-sm" value={item.assignedStaff.find((assignment) => assignment.staffUserUuid === staff.uuid)?.assignmentRole ?? item.assignmentRole} onChange={(event) => setNewOrderItems((current) => current.map((currentItem, currentIndex) => currentIndex === index ? {
                              ...currentItem,
                              assignedStaff: currentItem.assignedStaff.map((assignment) => assignment.staffUserUuid === staff.uuid ? { ...assignment, assignmentRole: event.target.value } : assignment),
                            } : currentItem))} placeholder="Role" />
                          </div>
                        ))}
                      </div>
                    </div>
                  );
                })}
              </div>
              <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600">
                Contribution percentages must total 100% for the staff allocation on each item.
              </div>
              <button type="button" className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white" onClick={() => createOrderMutation.mutate()}>
                Create Service Order
              </button>
            </div>
          </div>
        </section>
      ) : null}

      {activePage === 'assigned-services' ? (
        <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <PageHeader title="Assigned Services" description="View service assignments with roles, percentages, timing, and notes." />
          <div className="mb-3 flex items-center gap-2">
            <select className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm" value={selectedStaffUserUuid} onChange={(event) => setSelectedStaffUserUuid(event.target.value)}>
              <option value="">All staff</option>
              {(staffQuery.data ?? []).map((staff) => <option key={staff.uuid} value={staff.uuid}>{staff.displayName ?? staff.username}</option>)}
            </select>
          </div>
          <SimpleTable
            rows={assignedServicesQuery.data ?? []}
            columns={['orderNumber', 'serviceName', 'staffName', 'contributionPercentage', 'assignmentRole', 'startedAt', 'completedAt', 'notes']}
            emptyMessage="No assignments available."
          />
        </section>
      ) : null}

      {activePage === 'service-payments' ? (
        <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <PageHeader title="Service Payments" description="Record deposits or payments and confirm receipt before allocation." />
          <div className="grid gap-3 md:grid-cols-4">
            <select className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm" value={selectedOrderUuid} onChange={(event) => setSelectedOrderUuid(event.target.value)}>
              <option value="">Select order</option>
              {orders.map((order) => <option key={order.uuid} value={order.uuid}>{order.orderNumber}</option>)}
            </select>
            <input className="rounded-md border border-slate-300 px-3 py-2 text-sm" value={paymentAmount} onChange={(event) => setPaymentAmount(event.target.value)} placeholder="Amount" />
            <select className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm" value={paymentMethod} onChange={(event) => setPaymentMethod(event.target.value)}>
              <option value="cash">Cash</option>
              <option value="bank_transfer">Bank transfer</option>
              <option value="pos_terminal">POS terminal</option>
              <option value="mobile_money">Mobile money</option>
              <option value="credit">Credit</option>
            </select>
            <input className="rounded-md border border-slate-300 px-3 py-2 text-sm" value={paymentReference} onChange={(event) => setPaymentReference(event.target.value)} placeholder="Reference" />
          </div>
          <div className="mt-3 flex gap-2">
            <button type="button" className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white" onClick={() => recordPaymentMutation.mutate()}>Record Payment</button>
            <button type="button" className="rounded-md border border-slate-300 px-4 py-2 text-sm" onClick={() => confirmPaymentMutation.mutate(selectedOrderUuid)}>Confirm Payment</button>
          </div>
          <SimpleTable rows={payments} columns={['orderNumber', 'amount', 'paymentMethod', 'reference', 'createdAt']} emptyMessage="No payments recorded." />
        </section>
      ) : null}

      {activePage === 'service-expenses' ? (
        <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <PageHeader title="Service Expenses" description="Capture direct service expenses and keep them separate from general expenses." />
          <div className="grid gap-3 md:grid-cols-4">
            <input className="rounded-md border border-slate-300 px-3 py-2 text-sm" value={expenseCategory} onChange={(event) => setExpenseCategory(event.target.value)} placeholder="Category" />
            <input className="rounded-md border border-slate-300 px-3 py-2 text-sm" value={expenseDescription} onChange={(event) => setExpenseDescription(event.target.value)} placeholder="Description" />
            <input className="rounded-md border border-slate-300 px-3 py-2 text-sm" value={expenseAmount} onChange={(event) => setExpenseAmount(event.target.value)} placeholder="Amount" />
            <input className="rounded-md border border-slate-300 px-3 py-2 text-sm" value={expensePaidBy} onChange={(event) => setExpensePaidBy(event.target.value)} placeholder="Paid by" />
            <select className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm" value={expensePaymentMethod} onChange={(event) => setExpensePaymentMethod(event.target.value)}>
              <option value="cash">Cash</option>
              <option value="bank">Bank</option>
            </select>
            <select className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm" value={expenseApprovalStatus} onChange={(event) => setExpenseApprovalStatus(event.target.value)}>
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
              <option value="all">All</option>
            </select>
            <input className="rounded-md border border-slate-300 px-3 py-2 text-sm md:col-span-2" value={expenseReceiptPath} onChange={(event) => setExpenseReceiptPath(event.target.value)} placeholder="Receipt attachment" />
          </div>
          <div className="mt-3 flex items-center gap-2">
            <button type="button" className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white" onClick={() => recordExpenseMutation.mutate()}>Record Service Expense</button>
          </div>
          <SimpleTable rows={expenses} columns={['orderNumber', 'category', 'description', 'amount', 'paidBy', 'paymentMethod', 'approvalStatus', 'createdAt']} emptyMessage="No service expenses recorded." />
        </section>
      ) : null}

      {activePage === 'revenue-allocations' ? (
        <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <PageHeader title="Revenue Allocations" description="Preview distributable amounts and submit for approval when required." />
          <div className="grid gap-3 md:grid-cols-4">
            <select className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm" value={allocationTargetOrderUuid} onChange={(event) => setAllocationTargetOrderUuid(event.target.value)}>
              <option value="">Select order</option>
              {orders.map((order) => <option key={order.uuid} value={order.uuid}>{order.orderNumber}</option>)}
            </select>
            <select className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm" value={revenueAllocationStatus} onChange={(event) => setRevenueAllocationStatus(event.target.value)}>
              <option value="all">All statuses</option>
              <option value="pending">Pending</option>
              <option value="pending_approval">Pending approval</option>
              <option value="approved">Approved</option>
            </select>
            <input className="rounded-md border border-slate-300 px-3 py-2 text-sm" value={allocationRequestedByUserUuid} onChange={(event) => setAllocationRequestedByUserUuid(event.target.value)} placeholder="Requested by UUID" />
            <input className="rounded-md border border-slate-300 px-3 py-2 text-sm" value={allocationApprovedByUserUuid} onChange={(event) => setAllocationApprovedByUserUuid(event.target.value)} placeholder="Approved by UUID" />
          </div>
          <div className="mt-3 flex flex-wrap gap-2">
            <button type="button" className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50" disabled={!canReviewAllocations} onClick={() => requestAllocationMutation.mutate()}>Review Allocation</button>
            <button type="button" className="rounded-md border border-slate-300 px-4 py-2 text-sm disabled:opacity-50" disabled={!canApproveAllocations} onClick={() => approveAllocationMutation.mutate()}>Approve Allocation</button>
          </div>
          {preview ? (
            <div className="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">
              <div className="grid gap-2 md:grid-cols-2 xl:grid-cols-4">
                <KeyValue label="Gross amount" value={formatMoney(preview.grossAmount)} />
                <KeyValue label="Discount" value={formatMoney(preview.discountAmount)} />
                <KeyValue label="Direct expenses" value={formatMoney(preview.directExpenses)} />
                <KeyValue label="Distributable amount" value={formatMoney(preview.distributableAmount)} />
                <KeyValue label="Staff share" value={formatMoney(preview.staffShare)} />
                <KeyValue label="Business share" value={formatMoney(preview.businessShare)} />
                <KeyValue label="Expense-reserve share" value={formatMoney(preview.expenseReserveShare)} />
                <KeyValue label="Rule used" value={preview.rule.basis} />
              </div>
            </div>
          ) : null}
          <SimpleTable rows={allocations as RevenueAllocation[]} columns={['orderNumber', 'componentType', 'staffName', 'amount', 'status', 'createdAt']} emptyMessage="No allocations yet." />
        </section>
      ) : null}

      {activePage === 'staff-earnings' ? (
        <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <PageHeader title="Staff Earnings" description="Totals, ledger activity, period trends, and service breakdown for staff earnings and settlements." />
          <div className="mb-4 grid gap-3 md:grid-cols-3 lg:grid-cols-6">
            <input className="rounded-md border border-slate-300 px-3 py-2 text-sm" value={fromDate} onChange={(event) => setFromDate(event.target.value)} placeholder="From (YYYY-MM-DD)" />
            <input className="rounded-md border border-slate-300 px-3 py-2 text-sm" value={toDate} onChange={(event) => setToDate(event.target.value)} placeholder="To (YYYY-MM-DD)" />
            <select className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm" value={earningsPeriod} onChange={(event) => setEarningsPeriod(event.target.value as 'day' | 'week' | 'month')}>
              <option value="day">Daily</option>
              <option value="week">Weekly</option>
              <option value="month">Monthly</option>
            </select>
            <select className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm" value={selectedStaffUserUuid} onChange={(event) => setSelectedStaffUserUuid(event.target.value)}>
              <option value="">All staff</option>
              {(staffQuery.data ?? []).map((staff) => <option key={staff.uuid} value={staff.uuid}>{staff.displayName ?? staff.username}</option>)}
            </select>
            <button type="button" className="rounded-md border border-slate-300 px-3 py-2 text-sm" onClick={() => earningsOverviewQuery.refetch()}>Refresh Overview</button>
            <div className="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">Ledger types: {earningsOverviewQuery.data?.ledgerTypes.join(', ') ?? 'service_share, bonus, adjustment, deduction, settlement_payment, reversal'}</div>
          </div>

          <div className="mb-4 grid gap-3 md:grid-cols-3 xl:grid-cols-6">
            <MetricTile label="Total earned" value={formatMoney(earningsOverviewQuery.data?.totals.totalEarned ?? 0)} />
            <MetricTile label="Approved earnings" value={formatMoney(earningsOverviewQuery.data?.totals.approvedEarnings ?? 0)} />
            <MetricTile label="Pending earnings" value={formatMoney(earningsOverviewQuery.data?.totals.pendingEarnings ?? 0)} />
            <MetricTile label="Settled amount" value={formatMoney(earningsOverviewQuery.data?.totals.settledAmount ?? 0)} />
            <MetricTile label="Outstanding balance" value={formatMoney(earningsOverviewQuery.data?.totals.outstandingBalance ?? 0)} />
            <MetricTile label="Reversed amount" value={formatMoney(earningsOverviewQuery.data?.totals.reversedAmount ?? 0)} />
          </div>

          <div className="mb-4 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
            <div className="font-semibold text-slate-900">Authorized actions</div>
            <div className="mt-2 grid gap-1 md:grid-cols-2 xl:grid-cols-3">
              <span>View earnings: {canViewEarnings ? 'Yes' : 'No'}</span>
              <span>Review allocations: {canReviewAllocations ? 'Yes' : 'No'}</span>
              <span>Approve allocation: {canApproveAllocations ? 'Yes' : 'No'}</span>
              <span>Prepare settlement: {canPrepareSettlement ? 'Yes' : 'No'}</span>
              <span>Record settlement: {canRecordSettlement ? 'Yes' : 'No'}</span>
              <span>Print settlement statement: {canPrintSettlementStatement ? 'Yes' : 'No'}</span>
            </div>
          </div>

          <div className="grid gap-4 xl:grid-cols-2">
            <div>
              <h4 className="mb-2 text-sm font-semibold text-slate-900">Earnings by service</h4>
              <SimpleTable
                rows={(earningsOverviewQuery.data?.earningsByService ?? []) as Array<Record<string, unknown>>}
                columns={['serviceName', 'totalEarned', 'approvedEarnings', 'pendingEarnings', 'reversedAmount']}
                emptyMessage="No service earnings available for the selected range."
              />
            </div>
            <div>
              <h4 className="mb-2 text-sm font-semibold text-slate-900">Earnings by period</h4>
              <SimpleTable
                rows={(earningsOverviewQuery.data?.earningsByPeriod ?? []) as Array<Record<string, unknown>>}
                columns={['period', 'totalEarned', 'approvedEarnings', 'pendingEarnings', 'settledAmount', 'reversedAmount']}
                emptyMessage="No period earnings available for the selected range."
              />
            </div>
          </div>

          <div className="mt-4">
            <h4 className="mb-2 text-sm font-semibold text-slate-900">Earnings ledger</h4>
            <SimpleTable
              rows={(earningsOverviewQuery.data?.ledger ?? []) as Array<Record<string, unknown>>}
              columns={['staffName', 'username', 'type', 'amount', 'orderNumber', 'createdAt']}
              emptyMessage="No earning ledger rows found."
            />
          </div>

          <div className="mt-4">
            <h4 className="mb-2 text-sm font-semibold text-slate-900">Settlement history</h4>
            <SimpleTable
              rows={(earningsOverviewQuery.data?.settlementHistory ?? settlements) as Array<Record<string, unknown>>}
              columns={['staffName', 'amount', 'status', 'createdAt']}
              emptyMessage="No settlement history available."
            />
          </div>

          <div className="mt-4 rounded-lg border border-slate-200 p-3">
            <h4 className="mb-2 text-sm font-semibold text-slate-900">Manual ledger entry</h4>
            <div className="grid gap-3 md:grid-cols-4">
              <select className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm" value={selectedStaffUserUuid} onChange={(event) => setSelectedStaffUserUuid(event.target.value)}>
                <option value="">Select staff</option>
                {(staffQuery.data ?? []).map((staff) => <option key={staff.uuid} value={staff.uuid}>{staff.displayName ?? staff.username}</option>)}
              </select>
              <select className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm" value={adjustmentType} onChange={(event) => setAdjustmentType(event.target.value as 'bonus' | 'adjustment' | 'deduction')}>
                <option value="bonus">bonus</option>
                <option value="adjustment">adjustment</option>
                <option value="deduction">deduction</option>
              </select>
              <input className="rounded-md border border-slate-300 px-3 py-2 text-sm" value={adjustmentAmount} onChange={(event) => setAdjustmentAmount(event.target.value)} placeholder="Amount" />
              <input className="rounded-md border border-slate-300 px-3 py-2 text-sm" value={adjustmentNote} onChange={(event) => setAdjustmentNote(event.target.value)} placeholder="Optional note" />
            </div>
            <div className="mt-3">
              <button
                type="button"
                className="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                disabled={!canRecordSettlement || adjustmentMutation.isPending}
                onClick={() => adjustmentMutation.mutate()}
              >
                Post Ledger Entry
              </button>
            </div>
          </div>

          <div className="mt-4">
            <SimpleTable rows={staffEarnings as Array<Record<string, unknown>>} columns={['staffName', 'username', 'currentBalance', 'updatedAt']} emptyMessage="No staff earnings available." />
          </div>
        </section>
      ) : null}

      {activePage === 'staff-settlements' ? (
        <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <PageHeader title="Staff Settlements" description="Prepare, validate, record, and print settlement statements from approved outstanding earnings." />
          <div className="grid gap-3 md:grid-cols-4">
            <select className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm" value={selectedStaffUserUuid} onChange={(event) => setSelectedStaffUserUuid(event.target.value)}>
              <option value="">Select staff</option>
              {(staffQuery.data ?? []).map((staff) => <option key={staff.uuid} value={staff.uuid}>{staff.displayName ?? staff.username}</option>)}
            </select>
            <input className="rounded-md border border-slate-300 px-3 py-2 text-sm" value={settlementAmount} onChange={(event) => setSettlementAmount(event.target.value)} placeholder="Settlement amount" />
            <button type="button" className="rounded-md border border-slate-300 px-4 py-2 text-sm" disabled={!canPrepareSettlement} onClick={() => settlementPreviewQuery.refetch()}>Prepare Settlement</button>
            <button type="button" className="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50" disabled={!canRecordSettlement || settleMutation.isPending} onClick={() => settleMutation.mutate()}>Record Settlement</button>
          </div>

          {settlementPreviewQuery.data ? (
            <div className="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">
              <KeyValue label="Requested amount" value={formatMoney(settlementPreviewQuery.data.requestedAmount)} />
              <KeyValue label="Approved outstanding" value={formatMoney(settlementPreviewQuery.data.approvedOutstanding)} />
              <KeyValue label="Can settle" value={settlementPreviewQuery.data.canSettle ? 'Yes' : 'No'} />
              <div className="mt-2 text-slate-600">{settlementPreviewQuery.data.message}</div>
            </div>
          ) : null}

          {settlementStatementQuery.data ? (
            <div className="mt-3 rounded-lg border border-slate-200 p-3">
              <div className="mb-2 flex items-center justify-between gap-2">
                <h4 className="text-sm font-semibold text-slate-900">Settlement statement</h4>
                <button type="button" className="rounded-md border border-slate-300 px-3 py-1.5 text-xs" disabled={!canPrintSettlementStatement} onClick={() => window.print()}>Print Settlement Statement</button>
              </div>
              <div className="grid gap-2 md:grid-cols-3 text-sm">
                <KeyValue label="Staff" value={settlementStatementQuery.data.staff.staffName ?? settlementStatementQuery.data.staff.username} />
                <KeyValue label="Generated" value={settlementStatementQuery.data.generatedAt} />
                <KeyValue label="Period" value={`${settlementStatementQuery.data.period.from ?? 'Start'} to ${settlementStatementQuery.data.period.to ?? 'Now'}`} />
                <KeyValue label="Total earned" value={formatMoney(settlementStatementQuery.data.totals.totalEarned)} />
                <KeyValue label="Settled" value={formatMoney(settlementStatementQuery.data.totals.settledAmount)} />
                <KeyValue label="Outstanding" value={formatMoney(settlementStatementQuery.data.totals.outstandingBalance)} />
              </div>
            </div>
          ) : null}

          <SimpleTable rows={settlements} columns={['staffName', 'amount', 'status', 'createdAt']} emptyMessage="No settlements recorded." />
        </section>
      ) : null}

      {activePage === 'staff-performance' ? (
        <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <PageHeader title="Staff Performance" description="Combined sales, service, and operational metrics with neutral monitoring labels." />
          <div className="mb-4 grid gap-3 md:grid-cols-2 lg:grid-cols-4">
            <input className="rounded-md border border-slate-300 px-3 py-2 text-sm" value={fromDate} onChange={(event) => setFromDate(event.target.value)} placeholder="From (YYYY-MM-DD)" />
            <input className="rounded-md border border-slate-300 px-3 py-2 text-sm" value={toDate} onChange={(event) => setToDate(event.target.value)} placeholder="To (YYYY-MM-DD)" />
            <button type="button" className="rounded-md border border-slate-300 px-3 py-2 text-sm" onClick={() => staffPerformanceQuery.refetch()}>Refresh Performance</button>
            <div className="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">{staffPerformanceQuery.data?.disclaimer ?? 'Monitoring indicators are neutral and require review.'}</div>
          </div>

          <div className="mb-4 rounded-lg border border-slate-200 bg-slate-50 p-3">
            <div className="mb-2 text-sm font-semibold text-slate-900">Neutral labels</div>
            <div className="flex flex-wrap gap-2 text-xs">
              {(staffPerformanceQuery.data?.labels ?? ['Requires review', 'Higher than usual', 'Lower than expected', 'Outside expected range']).map((label) => (
                <span key={label} className="rounded-full bg-white px-2 py-1 border border-slate-200">{label}</span>
              ))}
            </div>
          </div>

          <SimpleTable
            rows={(staffPerformanceQuery.data?.rows ?? []) as Array<Record<string, unknown>>}
            columns={[
              'staffName',
              'username',
              'salesCount',
              'salesValue',
              'discounts',
              'returns',
              'cancellations',
              'expectedCash',
              'actualCash',
              'cashDelta',
              'servicesAssigned',
              'servicesCompleted',
              'servicesCancelled',
              'serviceRevenue',
              'directExpenses',
              'staffShareEarned',
              'staffShareSettled',
              'outstandingEarnings',
              'averageCompletionTimeMinutes',
              'purchases',
              'stockCounts',
              'stockAdjustments',
              'expenses',
              'approvals',
              'deviceActivity',
              'reviewLabels',
            ]}
            emptyMessage="No staff performance rows available for the selected period."
          />
        </section>
      ) : null}

      {activePage === 'new-service-order' ? null : null}
    </div>
  );
}

function MetricTile({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-lg border border-slate-200 bg-slate-50 p-3">
      <div className="text-xs uppercase tracking-wide text-slate-500">{label}</div>
      <div className="mt-1 text-base font-semibold text-slate-900">{value}</div>
    </div>
  );
}

function Panel({ title, icon, children }: { title: string; icon: JSX.Element; children: React.ReactNode }) {
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
      <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-900">
        {icon}
        {title}
      </div>
      <div className="space-y-2">{children}</div>
    </div>
  );
}

function PageHeader({ title, description }: { title: string; description: string }) {
  return (
    <div className="mb-4">
      <h3 className="text-lg font-semibold text-slate-900">{title}</h3>
      <p className="text-sm text-slate-500">{description}</p>
    </div>
  );
}

function KeyValue({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex items-center justify-between rounded-md bg-slate-50 px-3 py-2 text-sm">
      <span className="text-slate-600">{label}</span>
      <span className="font-medium text-slate-900">{value}</span>
    </div>
  );
}

function SimpleTable({ rows, columns, emptyMessage }: { rows: Array<Record<string, unknown>>; columns: string[]; emptyMessage: string }) {
  if (rows.length === 0) {
    return <div className="mt-3 rounded-lg border border-dashed border-slate-300 p-4 text-sm text-slate-500">{emptyMessage}</div>;
  }

  return (
    <div className="mt-3 overflow-x-auto rounded-lg border border-slate-200">
      <table className="min-w-full divide-y divide-slate-200 text-sm">
        <thead className="bg-slate-50">
          <tr>
            {columns.map((column) => (
              <th key={column} className="px-3 py-2 text-left font-semibold text-slate-600">{column}</th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-200 bg-white">
          {rows.map((row, rowIndex) => (
            <tr key={rowIndex}>
              {columns.map((column) => (
                <td key={column} className="px-3 py-2 text-slate-800">{renderValue(row[column])}</td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function OrderTable({
  orders,
  onSelect,
  onStart,
  onComplete,
  onCancel,
}: {
  orders: ServiceOrder[];
  onSelect(orderUuid: string): void;
  onStart(orderUuid: string): void;
  onComplete(orderUuid: string): void;
  onCancel(orderUuid: string): void;
}) {
  if (orders.length === 0) {
    return <div className="rounded-lg border border-dashed border-slate-300 p-4 text-sm text-slate-500">No service orders found.</div>;
  }

  return (
    <div className="overflow-x-auto rounded-lg border border-slate-200">
      <table className="min-w-full divide-y divide-slate-200 text-sm">
        <thead className="bg-slate-50">
          <tr>
            <th className="px-3 py-2 text-left font-semibold text-slate-600">Order</th>
            <th className="px-3 py-2 text-left font-semibold text-slate-600">Customer</th>
            <th className="px-3 py-2 text-left font-semibold text-slate-600">Status</th>
            <th className="px-3 py-2 text-left font-semibold text-slate-600">Gross</th>
            <th className="px-3 py-2 text-left font-semibold text-slate-600">Paid</th>
            <th className="px-3 py-2 text-left font-semibold text-slate-600">Direct expenses</th>
            <th className="px-3 py-2 text-left font-semibold text-slate-600">Actions</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-200 bg-white">
          {orders.map((order) => (
            <tr key={order.uuid}>
              <td className="px-3 py-2 font-medium text-slate-900">
                <button type="button" className="text-left hover:text-blue-700" onClick={() => onSelect(order.uuid)}>
                  {order.orderNumber}
                </button>
              </td>
              <td className="px-3 py-2 text-slate-700">{order.customerName ?? 'Walk-in customer'}</td>
              <td className="px-3 py-2">
                <span className={`rounded-full px-2 py-1 text-xs font-semibold ${stateTone[order.status] ?? 'bg-slate-100 text-slate-700'}`}>{order.status}</span>
              </td>
              <td className="px-3 py-2 text-slate-700">{formatMoney(order.grossAmount ?? 0)}</td>
              <td className="px-3 py-2 text-slate-700">{formatMoney(order.paidAmount ?? 0)}</td>
              <td className="px-3 py-2 text-slate-700">{formatMoney(order.directExpenses ?? 0)}</td>
              <td className="px-3 py-2">
                <div className="flex flex-wrap gap-2">
                  <button type="button" className="rounded-md border border-slate-300 px-2 py-1" onClick={() => onStart(order.uuid)}>Start</button>
                  <button type="button" className="rounded-md border border-slate-300 px-2 py-1" onClick={() => onComplete(order.uuid)}>Complete</button>
                  <button type="button" className="rounded-md border border-slate-300 px-2 py-1" onClick={() => onCancel(order.uuid)}>Cancel</button>
                </div>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function renderValue(value: unknown): React.ReactNode {
  if (value === null || value === undefined || value === '') {
    return '—';
  }

  if (typeof value === 'object') {
    return JSON.stringify(value);
  }

  return String(value);
}
