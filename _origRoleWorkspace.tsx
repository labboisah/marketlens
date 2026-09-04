import { useQuery } from '@tanstack/react-query';
import { Button, ShiftStatusIndicator, StatusBadge, SynchronizationStatusIndicator } from '@marketlens/ui';
import { Bell, ChevronDown, RefreshCw, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
  resolveSyncState,
  roleLabel,
  startingRolePresets,
} from '../features/navigation/application-shell-navigation';
import { asNumber, asText, classifyPaymentMethod, findRowByUserUuid, loadRoleDashboardSnapshot } from '../services/dashboard-summary-service';
import { useClientUiStore } from '../state/client-ui-store';
import { ConnectionStatusIndicator } from '../components/ConnectionStatusIndicator';

function updateBadge(updateRequired: boolean) {
  return updateRequired ? 'Update required' : 'Up to date';
}

function formatNumber(value: number): string {
  return new Intl.NumberFormat('en-US').format(value);
}

function formatCurrency(value: number): string {
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);
}

function formatTimestamp(value?: string | null): string {
  if (!value) {
    return 'Not available';
  }

  const parsed = new Date(value);
  return Number.isNaN(parsed.getTime()) ? value : parsed.toLocaleString();
}

function dashboardTone(value: string): 'success' | 'warning' | 'danger' | 'information' | 'neutral' {
  if (value === 'critical' || value === 'closed' || value === 'blocked') {
    return 'danger';
  }

  if (value === 'open' || value === 'healthy' || value === 'connected' || value === 'synced') {
    return 'success';
  }

  if (value === 'pending' || value === 'delayed' || value === 'review') {
    return 'warning';
  }

  if (value === 'unknown') {
    return 'neutral';
  }

  return 'information';
}

function valueOrDash(value: unknown): string {
  if (typeof value === 'number') {
    return formatNumber(value);
  }

  if (typeof value === 'string' && value.trim().length > 0) {
    return value;
  }

  return 'GÇö';
}

function keyLabel(key: string): string {
  return key
    .replace(/([a-z])([A-Z])/g, '$1 $2')
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (match) => match.toUpperCase());
}

function MetricCard(props: { label: string; value: string; note?: string; tone?: 'success' | 'warning' | 'danger' | 'information' | 'neutral' }) {
  return (
    <article className="rounded-xl border border-border/70 bg-surface p-4 shadow-sm">
      <div className="flex items-center justify-between gap-3">
        <p className="text-xs uppercase tracking-wide text-muted">{props.label}</p>
        {props.tone ? <StatusBadge tone={props.tone}>{props.tone}</StatusBadge> : null}
      </div>
      <p className="mt-3 text-2xl font-semibold text-foreground">{props.value}</p>
      {props.note ? <p className="mt-2 text-xs text-muted">{props.note}</p> : null}
    </article>
  );
}

function ListCard(props: { title: string; items: Array<{ primary: string; secondary?: string; value?: string }>; emptyText: string }) {
  return (
    <section className="rounded-xl border border-border/70 bg-surface p-4 shadow-sm">
      <h3 className="text-sm font-semibold text-foreground">{props.title}</h3>
      <div className="mt-3 space-y-3">
        {props.items.length > 0 ? (
          props.items.map((item) => (
            <div key={`${item.primary}-${item.secondary ?? item.value ?? ''}`} className="rounded-lg border border-border bg-background p-3">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="text-sm font-medium">{item.primary}</p>
                  {item.secondary ? <p className="mt-1 text-xs text-muted">{item.secondary}</p> : null}
                </div>
                {item.value ? <StatusBadge tone="information">{item.value}</StatusBadge> : null}
              </div>
            </div>
          ))
        ) : (
          <p className="text-sm text-muted">{props.emptyText}</p>
        )}
      </div>
    </section>
  );
}

export function RoleWorkspace() {
  const activeRole = useClientUiStore((state) => state.activeRole);
  const permissions = useClientUiStore((state) => state.permissions);
  const roles = useClientUiStore((state) => state.roles);
  const username = useClientUiStore((state) => state.username);
  const shiftStatus = useClientUiStore((state) => state.shiftStatus);
  const shopStatus = useClientUiStore((state) => state.shopStatus);
  const connectionState = useClientUiStore((state) => state.connectionState);
  const updateRequired = useClientUiStore((state) => state.updateRequired);
  const notifications = useClientUiStore((state) => state.notifications);
  const applicationStateMessage = useClientUiStore((state) => state.applicationStateMessage);
  const setScreen = useClientUiStore((state) => state.setScreen);
  const setActiveRole = useClientUiStore((state) => state.setActiveRole);
  const [theme, setTheme] = useState<string>(document.documentElement.getAttribute('data-theme') ?? 'light');
  const [searchText, setSearchText] = useState('');

  const dashboardQuery = useQuery({
    queryKey: ['role-dashboard', shopStatus?.shopDeviceUuid ?? shopStatus?.shopServerName ?? 'unknown', activeRole, username, useClientUiStore.getState().currentUser?.uuid ?? 'unknown'],
    queryFn: async () => {
      const session = await window.marketlensClient.session.load();

      if (!session) {
        throw new Error('Session not available.');
      }

      return loadRoleDashboardSnapshot({
        shopBaseUrl: session.shopBaseUrl,
        accessToken: session.token,
        role: activeRole,
        currentUserUuid: useClientUiStore.getState().currentUser?.uuid,
      });
    },
    enabled: Boolean(shopStatus && connectionState === 'connected' && username),
    staleTime: 30_000,
    refetchOnWindowFocus: false,
  });

  const syncState = resolveSyncState(connectionState, shopStatus);
  const dashboard = dashboardQuery.data;
  const currentUserUuid = useClientUiStore((state) => state.currentUser?.uuid);
  const currentStaffRow = useMemo(() => findRowByUserUuid(dashboard?.staffPerformanceRows ?? [], currentUserUuid), [currentUserUuid, dashboard?.staffPerformanceRows]);
  const paymentBreakdown = dashboard?.summary.paymentBreakdown ?? [];
  const cashPayment = paymentBreakdown.find((item) => classifyPaymentMethod(item.paymentMethod) === 'cash');
  const transferPayment = paymentBreakdown.find((item) => classifyPaymentMethod(item.paymentMethod) === 'transfer');
  const posPayment = paymentBreakdown.find((item) => classifyPaymentMethod(item.paymentMethod) === 'pos');
  const creditPayment = paymentBreakdown.find((item) => classifyPaymentMethod(item.paymentMethod) === 'credit');

  const lowStockProducts = (dashboard?.inventoryRows ?? [])
    .filter((row) => asNumber(row.quantity) > 0 && asNumber(row.quantity) <= asNumber(row.reorderLevel))
    .slice(0, 6);
  const outOfStockProducts = (dashboard?.inventoryRows ?? [])
    .filter((row) => asNumber(row.quantity) <= 0)
    .slice(0, 6);
  const purchasesAwaitingReceiving = (dashboard?.purchasesRows ?? [])
    .filter((row) => asText(row.status, '').toLowerCase() !== 'received')
    .slice(0, 6);
  const recentStockMovements = (dashboard?.stockMovementRows ?? []).slice(0, 6);
  const damagedStock = (dashboard?.stockMovementRows ?? [])
    .filter((row) => asText(row.movementType, '').toLowerCase().includes('damage'))
    .slice(0, 6);
  const expiringProducts = (dashboard?.inventoryRows ?? [])
    .filter((row) => Boolean(row.expiryAt))
    .slice(0, 6);
  const pendingStockCounts: Array<Record<string, unknown>> = [];

  const pendingExpenses = (dashboard?.expensesRows ?? []).filter((row) => asText(row.status, '').toLowerCase().includes('pending'));
  const pendingServiceOrders = (dashboard?.serviceRevenueRows ?? []).filter((row) => !['completed', 'refunded'].includes(asText(row.status, '').toLowerCase()));
  const pendingCustomerPayments = (dashboard?.customerDebtRows ?? []).slice(0, 6);
  const pendingStaffSettlements = (dashboard?.staffSettlementRows ?? []).filter((row) => asText(row.status, '').toLowerCase() !== 'completed');

  const applyTheme = (nextTheme: string) => {
    document.documentElement.setAttribute('data-theme', nextTheme);
    localStorage.setItem('marketlens.client.theme', nextTheme);
    setTheme(nextTheme);
  };

  const selectRolePreset = async (nextRole: typeof activeRole) => {
    setActiveRole(nextRole);
    const preferences = await window.marketlensClient.preferences.load();
    await window.marketlensClient.preferences.save({
      ...preferences,
      preferredRole: nextRole,
    });
  };

  const roleTitle = `${roleLabel(activeRole)} dashboard`;
  const dashboardReady = dashboardQuery.isSuccess && dashboard !== undefined;

  return (
    <section className="min-w-0 bg-background">
      <header className="sticky top-0 z-20 border-b border-border bg-background/95 px-6 py-4 backdrop-blur">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 className="text-xl font-semibold">{roleTitle}</h1>
            <p className="mt-1 text-sm text-muted">
              {shopStatus?.businessName ?? 'Unknown business'} / {shopStatus?.branchName ?? 'Unknown branch'}
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <ShiftStatusIndicator status={shiftStatus} />
            <ConnectionStatusIndicator />
            <SynchronizationStatusIndicator state={syncState} lastSyncedAt={shopStatus?.lastSynchronization ?? undefined} />
            <StatusBadge tone={updateRequired ? 'warning' : 'success'}>{updateBadge(updateRequired)}</StatusBadge>
            <div className="flex items-center gap-2 rounded-md border border-border px-2 py-1">
              <label htmlFor="theme-select" className="text-xs text-muted">Theme</label>
              <select
                id="theme-select"
                value={theme}
                onChange={(event) => applyTheme(event.target.value)}
                className="rounded bg-transparent text-sm"
              >
                <option value="light">Light</option>
                <option value="dark">Dark</option>
              </select>
            </div>
            <StatusBadge tone="information">
              <Bell className="h-4 w-4" />
              {notifications.length} notifications
            </StatusBadge>
            <details className="group relative">
              <summary className="flex cursor-pointer list-none items-center gap-2 rounded-md border border-border px-3 py-2 text-sm hover:bg-surface-muted">
                <span>{username ?? 'User'}</span>
                <ChevronDown className="h-4 w-4" />
              </summary>
              <div className="absolute right-0 mt-2 w-72 rounded-md border border-border bg-surface p-3 shadow-md">
                <p className="text-xs text-muted">Signed in roles</p>
                <p className="text-sm">{roles.length > 0 ? roles.join(', ') : 'No role claims returned'}</p>
                <p className="mt-2 text-xs text-muted">Starting role preset</p>
                <select
                  className="mt-1 w-full rounded-md border border-border bg-background px-2 py-2 text-sm"
                  value={activeRole}
                  onChange={(event) => {
                    void selectRolePreset(event.target.value as typeof activeRole);
                  }}
                >
                  {startingRolePresets.map((preset) => (
                    <option key={preset.value} value={preset.value}>{preset.label}</option>
                  ))}
                </select>
                <p className="mt-2 text-xs text-muted">Current preset: {roleLabel(activeRole)}</p>
              </div>
            </details>
          </div>
        </div>

        <div className="mt-3 flex flex-wrap items-center gap-3">
          <p className="text-xs text-muted">Dashboard / {roleLabel(activeRole)}</p>
          <div className="flex items-center gap-2">
            <Button variant="ghost" onClick={() => void dashboardQuery.refetch()} disabled={dashboardQuery.isFetching}>
              <RefreshCw className={`h-4 w-4 ${dashboardQuery.isFetching ? 'animate-spin' : ''}`} />
              Refresh dashboard
            </Button>
            <Button variant="ghost" onClick={() => setScreen('connection')}>Open connection manager</Button>
          </div>
          <div className="relative max-w-lg flex-1">
            <Search className="pointer-events-none absolute left-2 top-2.5 h-4 w-4 text-muted" />
            <input
              value={searchText}
              onChange={(event) => setSearchText(event.target.value)}
              placeholder="Search dashboard notes, summaries, and row labels"
              className="w-full rounded-md border border-border bg-surface py-2 pl-8 pr-3 text-sm"
            />
          </div>
        </div>
      </header>

      <div className="p-6">
        <section className="rounded-2xl border border-border bg-surface p-5 shadow-sm">
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div>
              <h2 className="text-lg font-semibold">Role snapshot</h2>
              <p className="mt-1 text-sm text-muted">{applicationStateMessage}</p>
            </div>
            <div className="flex flex-wrap gap-2">
              <StatusBadge tone={dashboardQuery.isError ? 'danger' : dashboardReady ? 'success' : 'warning'}>
                {dashboardQuery.isError ? 'Summary error' : dashboardReady ? 'Summary ready' : 'Loading summary'}
              </StatusBadge>
              <StatusBadge tone={dashboardTone(connectionState)}>{connectionState}</StatusBadge>
              <StatusBadge tone={dashboardTone(syncState)}>{syncState}</StatusBadge>
            </div>
          </div>

          {dashboardQuery.isError ? (
            <div className="mt-4 rounded-xl border border-border bg-background p-4 text-sm text-muted">
              {dashboardQuery.error instanceof Error ? dashboardQuery.error.message : 'Unable to load the Business Server summary.'}
            </div>
          ) : null}

          <div className="mt-4 grid gap-4 lg:grid-cols-4">
            <MetricCard label="Business" value={shopStatus?.businessName ?? 'Unknown'} note={shopStatus?.branchName ?? 'Unknown branch'} tone="information" />
            <MetricCard label="Business server" value={shopStatus?.shopServerName ?? 'Unknown'} note={`${shopStatus?.serverIp ?? 'Unknown'}:${shopStatus?.serverPort ?? 'GÇö'}`} tone="neutral" />
            <MetricCard label="Last sync" value={formatTimestamp(shopStatus?.lastSynchronization)} note={`Platform: ${shopStatus?.platformConnectivity ?? 'unknown'}`} tone={dashboardTone(shopStatus?.platformConnectivity ?? 'unknown')} />
            <MetricCard label="Client apps" value={formatNumber(dashboard?.summary.authorizedClientApps ?? 0)} note="Authorized clients on the Business Server" tone="success" />
          </div>
        </section>

        {activeRole === 'cashier' ? (
          <section className="mt-4 grid gap-4 xl:grid-cols-3">
            <div className="grid gap-4 lg:grid-cols-2 xl:col-span-2">
              <MetricCard label="Current shift status" value={(dashboard?.summary.openCashierShifts ?? 0) > 0 ? 'Open' : 'Closed'} note={`${formatNumber(dashboard?.summary.openCashierShifts ?? 0)} open shift(s)`} tone={(dashboard?.summary.openCashierShifts ?? 0) > 0 ? 'success' : 'warning'} />
              <MetricCard label="Opening cash" value={valueOrDash(currentStaffRow?.openingCash)} note="Loaded from the Business Server summary" tone="information" />
              <MetricCard label="Sales count" value={valueOrDash(currentStaffRow?.transactionCount)} note="Completed sales in the selected window" />
              <MetricCard label="Sales value" value={formatCurrency(asNumber(currentStaffRow?.salesValue ?? 0))} note="Completed sales value from Business Server" />
              <MetricCard label="Cash collected" value={valueOrDash(cashPayment?.amount)} note={cashPayment ? cashPayment.paymentMethod : 'No cash payments'} />
              <MetricCard label="Transfer payments" value={valueOrDash(transferPayment?.amount)} note={transferPayment ? transferPayment.paymentMethod : 'No transfer payments'} />
              <MetricCard label="POS payments" value={valueOrDash(posPayment?.amount)} note={posPayment ? posPayment.paymentMethod : 'No POS payments'} />
              <MetricCard label="Credit sales" value={valueOrDash(creditPayment?.amount)} note={creditPayment ? creditPayment.paymentMethod : 'No credit payments'} />
              <MetricCard label="Returns" value={valueOrDash(currentStaffRow?.returns)} note="Cashier-linked returns from Business Server" />
              <MetricCard label="Expected cash" value={valueOrDash(currentStaffRow?.expectedCash)} note="Authoritative shift expectation" />
            </div>
            <ListCard
              title="Connection status"
              emptyText="Connection state unavailable"
              items={[
                { primary: 'Connection', secondary: connectionState },
                { primary: 'Sync state', secondary: syncState },
                { primary: 'Business server', secondary: shopStatus?.shopServerName ?? 'Unknown' },
                { primary: 'Branch', secondary: shopStatus?.branchName ?? 'Unknown' },
              ]}
            />
          </section>
        ) : null}

        {activeRole === 'storekeeper' ? (
          <section className="mt-4 grid gap-4 xl:grid-cols-2">
            <ListCard
              title="Low-stock products"
              emptyText="No low-stock products found"
              items={lowStockProducts.map((row) => ({
                primary: asText(row.product, 'Unknown product'),
                secondary: `Quantity ${valueOrDash(row.quantity)} / reorder level ${valueOrDash(row.reorderLevel)}`,
                value: 'Low',
              }))}
            />
            <ListCard
              title="Out-of-stock products"
              emptyText="No out-of-stock products found"
              items={outOfStockProducts.map((row) => ({
                primary: asText(row.product, 'Unknown product'),
                secondary: asText(row.sku, 'No SKU'),
                value: 'Out',
              }))}
            />
            <ListCard
              title="Purchases awaiting receiving"
              emptyText="No pending purchases found"
              items={purchasesAwaitingReceiving.map((row) => ({
                primary: asText(row.invoiceNumber, 'Draft purchase'),
                secondary: `${asText(row.supplier, 'Unknown supplier')} -+ ${asText(row.status, 'pending')}`,
                value: valueOrDash(row.totalAmount),
              }))}
            />
            <ListCard
              title="Recent stock movements"
              emptyText="No stock movements found"
              items={recentStockMovements.map((row) => ({
                primary: asText(row.product, 'Unknown product'),
                secondary: `${asText(row.movementType, 'movement')} -+ ${formatTimestamp(asText(row.date, ''))}`,
                value: valueOrDash(row.quantity),
              }))}
            />
            <ListCard
              title="Damaged stock"
              emptyText="No damaged stock movements found"
              items={damagedStock.map((row) => ({
                primary: asText(row.product, 'Unknown product'),
                secondary: asText(row.reference, 'No reference'),
                value: valueOrDash(row.quantity),
              }))}
            />
            <ListCard
              title="Expiring products"
              emptyText="No expiring products found in current summary"
              items={expiringProducts.map((row) => ({
                primary: asText(row.product, 'Unknown product'),
                secondary: `Expiry ${valueOrDash(row.expiryAt)}`,
                value: valueOrDash(row.quantity),
              }))}
            />
            <ListCard
              title="Pending stock counts"
              emptyText="No stock count summary is exposed yet"
              items={pendingStockCounts.map((row) => ({
                primary: asText(row.name, 'Stock count'),
                secondary: asText(row.status, 'Pending'),
              }))}
            />
          </section>
        ) : null}

        {activeRole === 'manager' ? (
          <section className="mt-4 grid gap-4 xl:grid-cols-3">
            <div className="grid gap-4 lg:grid-cols-2 xl:col-span-2">
              <MetricCard label="Sales summary" value={formatCurrency(dashboard?.summary.todaySales ?? 0)} note={`Gross profit ${formatCurrency(dashboard?.summary.grossProfit ?? 0)} -+ Net ${formatCurrency(dashboard?.summary.estimatedNetProfit ?? 0)}`} tone="success" />
              <MetricCard label="Expenses" value={formatCurrency(dashboard?.summary.expenses ?? 0)} note={`${pendingExpenses.length} pending approval item(s)`} tone={pendingExpenses.length > 0 ? 'warning' : 'information'} />
              <MetricCard label="Pending approvals" value={formatNumber(pendingExpenses.length)} note="Expenses awaiting review" tone={pendingExpenses.length > 0 ? 'warning' : 'success'} />
              <MetricCard label="Cashier shortages" value={formatCurrency(dashboard?.summary.cashierShortages ?? 0)} note="From cashier settlements" tone={dashboard?.summary.cashierShortages ? 'warning' : 'success'} />
              <MetricCard label="Returns and cancellations" value={valueOrDash(currentStaffRow?.returns)} note={`${valueOrDash(currentStaffRow?.cancellations)} cancellation(s)`} />
              <MetricCard label="Service orders" value={formatNumber(dashboard?.summary.servicesInProgress ?? 0)} note={`${pendingServiceOrders.length} non-completed order(s)`} />
              <MetricCard label="Staff settlements" value={formatNumber(pendingStaffSettlements.length)} note="Recorded settlement rows in the current summary" />
              <MetricCard label="Connection and server health" value={shopStatus?.serverStatus ?? 'Unknown'} note={`${shopStatus?.platformConnectivity ?? 'unknown'} -+ ${connectionState}`} tone={dashboardTone(shopStatus?.serverStatus ?? 'unknown')} />
            </div>
            <ListCard
              title="Manager focus"
              emptyText="No manager focus items"
              items={[
                { primary: 'Pending approvals', secondary: `${pendingExpenses.length} expense item(s)` },
                { primary: 'Service orders in progress', secondary: formatNumber(dashboard?.summary.servicesInProgress ?? 0) },
                { primary: 'Cashier shortages', secondary: formatCurrency(dashboard?.summary.cashierShortages ?? 0) },
                { primary: 'Sync health', secondary: shopStatus?.platformConnectivity ?? 'unknown' },
              ]}
            />
          </section>
        ) : null}

        {activeRole === 'accountant' ? (
          <section className="mt-4 grid gap-4 xl:grid-cols-3">
            <div className="grid gap-4 lg:grid-cols-2 xl:col-span-2">
              <MetricCard label="Customer debt" value={formatCurrency(dashboard?.summary.customerDebt ?? 0)} note="Outstanding customer balances" tone="warning" />
              <MetricCard label="Supplier balances" value={formatCurrency(dashboard?.summary.supplierBalance ?? 0)} note="Outstanding supplier balances" tone="warning" />
              <MetricCard label="Expenses" value={formatCurrency(dashboard?.summary.expenses ?? 0)} note="Approved expenses from Business Server" />
              <MetricCard label="Cashier settlements" value={formatNumber(dashboard?.cashierSettlementRows.length ?? 0)} note="Settlement rows loaded from the summary" />
              <MetricCard label="Staff earnings" value={formatNumber(dashboard?.staffEarningsRows.length ?? 0)} note="Earning accounts with balances" />
              <MetricCard label="Shareholder operational payments" value={formatNumber((dashboard?.revenueAllocationRows ?? []).length)} note="Shown when shareholder operations are enabled" />
            </div>
            <ListCard
              title="Payment-method breakdown"
              emptyText="No payment breakdown available"
              items={paymentBreakdown.map((item) => ({
                primary: item.paymentMethod,
                secondary: 'Authoritative Business Server aggregate',
                value: valueOrDash(item.amount),
              }))}
            />
          </section>
        ) : null}

        {activeRole === 'service_staff' ? (
          <section className="mt-4 grid gap-4 xl:grid-cols-3">
            <div className="grid gap-4 lg:grid-cols-2 xl:col-span-2">
              <MetricCard label="Assigned services" value={valueOrDash(currentStaffRow?.servicesAssigned)} note="Service assignments linked to your user" />
              <MetricCard label="Services in progress" value={formatNumber(Math.max(asNumber(currentStaffRow?.servicesAssigned ?? 0) - asNumber(currentStaffRow?.servicesCompleted ?? 0) - asNumber(currentStaffRow?.servicesCancelled ?? 0), 0))} note="Derived from assigned minus completed and cancelled service work" tone="warning" />
              <MetricCard label="Services completed" value={valueOrDash(currentStaffRow?.servicesCompleted)} note="Completed service orders in the window" tone="success" />
              <MetricCard label="Pending customer payments" value={formatNumber(pendingCustomerPayments.length)} note="Customer debt rows from the Business Server summary" />
              <MetricCard label="Staff earnings" value={valueOrDash(currentStaffRow?.staffShareEarned)} note="Loaded from Business Server service earnings" />
              <MetricCard label="Outstanding settlements" value={valueOrDash(currentStaffRow?.outstandingEarnings)} note="Remaining settlement balance" tone="warning" />
            </div>
            <ListCard
              title="Service staff queue"
              emptyText="No service staff rows available"
              items={pendingServiceOrders.map((row) => ({
                primary: asText(row.orderNumber, 'Service order'),
                secondary: `${asText(row.status, 'pending')} -+ ${asText(row.revenue, '0')}`,
                value: asText(row.orderNumber, 'Open'),
              }))}
            />
          </section>
        ) : null}

        <section className="mt-4 rounded-2xl border border-border bg-surface p-5 shadow-sm">
          <h2 className="text-base font-semibold">Summary source</h2>
          <p className="mt-2 text-sm text-muted">
            This dashboard reads Business Server summary and report endpoints through TanStack Query. Authoritative financial totals stay in the Business Server; the renderer only presents server-authored values and lists.
          </p>
          <div className="mt-3 flex flex-wrap gap-2">
            <StatusBadge tone="information">Role: {roleLabel(activeRole)}</StatusBadge>
            <StatusBadge tone="neutral">Permissions: {permissions.length}</StatusBadge>
            <StatusBadge tone={dashboardTone(shopStatus?.subscriptionStatus ?? 'unknown')}>Subscription: {shopStatus?.subscriptionStatus ?? 'unknown'}</StatusBadge>
            <StatusBadge tone={dashboardTone(shopStatus?.shopActivationStatus ?? 'unknown')}>Shop: {shopStatus?.shopActivationStatus ?? 'unknown'}</StatusBadge>
          </div>
        </section>

        {searchText.trim().length > 0 ? (
          <section className="mt-4 rounded-2xl border border-border bg-surface p-5 shadow-sm">
            <h2 className="text-base font-semibold">Search results</h2>
            <div className="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
              {Object.entries({
                business: shopStatus?.businessName,
                branch: shopStatus?.branchName,
                server: shopStatus?.shopServerName,
                role: roleLabel(activeRole),
                date: dashboard?.summary.date,
              })
                .filter(([, value]) => value?.toLowerCase().includes(searchText.trim().toLowerCase()))
                .map(([label, value]) => (
                  <article key={label} className="rounded-xl border border-border bg-background p-4">
                    <p className="text-xs uppercase tracking-wide text-muted">{keyLabel(label)}</p>
                    <p className="mt-2 text-sm font-medium">{value}</p>
                  </article>
                ))}
              {dashboard?.summary.paymentBreakdown
                .filter((item) => item.paymentMethod.toLowerCase().includes(searchText.trim().toLowerCase()))
                .map((item) => (
                  <article key={item.paymentMethod} className="rounded-xl border border-border bg-background p-4">
                    <p className="text-xs uppercase tracking-wide text-muted">Payment method</p>
                    <p className="mt-2 text-sm font-medium">{item.paymentMethod}</p>
                    <p className="mt-1 text-xs text-muted">{valueOrDash(item.amount)}</p>
                  </article>
                ))}
            </div>
          </section>
        ) : null}
      </div>

    </section>
  );
}
