import { type FormEvent, useEffect, useRef, useState } from 'react';
import { ServerConnectionIndicator, SynchronizationStatusIndicator } from '@marketlens/ui';
import '../styles/app.css';

const dashboardCards = [
  'Today\'s sales', 'Expenses', 'Gross profit', 'Estimated net profit', 'Customer debt',
  'Supplier balance', 'Stock value', 'Low-stock count', 'Open cashier shifts', 'Cashier shortages',
  'Services in progress', 'Service revenue', 'Pending staff earnings', 'Last Platform synchronization', 'Authorized Client Apps',
];

const reports = [
  'Daily sales', 'Sales by product', 'Sales by category', 'Sales by cashier', 'Payment breakdown',
  'Inventory', 'Stock movement', 'Purchases', 'Customer debt', 'Supplier balances', 'Expenses',
  'Cashier settlements', 'Service revenue', 'Service staff performance', 'Revenue allocations',
  'Staff earnings', 'Staff settlements',
];

const reportTypeByLabel: Record<string, string> = {
  'Daily sales': 'daily-sales', 'Sales by product': 'sales-by-product', 'Sales by category': 'sales-by-category', 'Sales by cashier': 'sales-by-cashier', 'Payment breakdown': 'payment-breakdown', Inventory: 'inventory', 'Stock movement': 'stock-movement', Purchases: 'purchases', 'Customer debt': 'customer-debt', 'Supplier balances': 'supplier-balances', Expenses: 'expenses', 'Cashier settlements': 'cashier-settlements', 'Service revenue': 'service-revenue', 'Service staff performance': 'service-staff-performance', 'Revenue allocations': 'revenue-allocations', 'Staff earnings': 'staff-earnings', 'Staff settlements': 'staff-settlements',
};

export function App() {
  const [activeView, setActiveView] = useState<'dashboard' | 'performance' | 'reports' | 'synchronization'>('dashboard');
  const [selectedReport, setSelectedReport] = useState(reports[0]);
  const [accessToken, setAccessToken] = useState<string>();
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string>();
  const [dashboard, setDashboard] = useState<Record<string, unknown>>({});
  const [performance, setPerformance] = useState<Array<Record<string, unknown>>>([]);
  const [reportRows, setReportRows] = useState<Array<Record<string, unknown>>>([]);
  const [synchronization, setSynchronization] = useState<Record<string, unknown>>({});
  const inactivityTimer = useRef<ReturnType<typeof setTimeout>>();
  const today = new Date().toISOString().slice(0, 10);

  useEffect(() => {
    if (!accessToken) return;
    void window.marketlensShop.reporting.dashboard(accessToken, today).then(setDashboard).catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'Dashboard could not be loaded'));
  }, [accessToken, today]);

  useEffect(() => {
    if (!accessToken || activeView === 'dashboard') return;
    if (activeView === 'synchronization') { void window.marketlensShop.synchronization.status(accessToken).then(setSynchronization).catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'Synchronization status could not be loaded')); return; }
    const filters = { from: today, to: today };
    const request = activeView === 'performance'
      ? window.marketlensShop.reporting.staffPerformance(accessToken, filters).then(setPerformance)
      : window.marketlensShop.reporting.report(accessToken, reportTypeByLabel[selectedReport], filters).then(setReportRows);
    void request.catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'Report could not be loaded'));
  }, [accessToken, activeView, selectedReport, today]);

  useEffect(() => {
    if (!accessToken) return;
    const resetLockTimer = () => {
      if (inactivityTimer.current) clearTimeout(inactivityTimer.current);
      inactivityTimer.current = setTimeout(() => { setAccessToken(undefined); setPassword(''); setError('Application locked after inactivity. Sign in to continue.'); }, 15 * 60 * 1000);
    };
    const events: Array<keyof WindowEventMap> = ['pointerdown', 'keydown', 'touchstart'];
    events.forEach((event) => window.addEventListener(event, resetLockTimer));
    resetLockTimer();
    return () => { events.forEach((event) => window.removeEventListener(event, resetLockTimer)); if (inactivityTimer.current) clearTimeout(inactivityTimer.current); };
  }, [accessToken]);

  async function login(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(undefined);
    const result = await window.marketlensShop.authentication.login(username, password);
    if (!result.authenticated || !result.accessToken) { setError('Login was rejected or this account lacks access.'); return; }
    setAccessToken(result.accessToken);
  }

  if (!accessToken) return <Login username={username} password={password} error={error} onUsername={setUsername} onPassword={setPassword} onSubmit={login} />;

  return (
    <main className="min-h-screen bg-background text-foreground">
      <header className="border-b border-border bg-surface px-6 py-4">
        <div className="mx-auto flex max-w-7xl items-center justify-between gap-4">
          <div>
            <p className="text-sm font-semibold text-teal-700">MARKETLENS / SHOP ADMIN</p>
            <h1 className="text-xl font-bold">Local operations</h1>
          </div>
          <div className="flex items-center gap-2">
            <ServerConnectionIndicator state="connected" />
            <SynchronizationStatusIndicator state="delayed" />
          </div>
        </div>
      </header>

      <div className="mx-auto grid max-w-7xl gap-6 p-6 lg:grid-cols-[13rem_1fr]">
        <aside className="border-b border-border pb-4 lg:border-b-0 lg:border-r lg:pb-0 lg:pr-4">
          <nav className="grid gap-1" aria-label="Shop administration">
            {([
              ['dashboard', 'Overview'],
              ['performance', 'Staff performance'],
              ['reports', 'Reports'],
              ['synchronization', 'Synchronization'],
            ] as const).map(([view, label]) => (
              <button key={view} className={`rounded-md px-3 py-2 text-left text-sm font-medium ${activeView === view ? 'bg-teal-700 text-white' : 'hover:bg-surface-muted'}`} onClick={() => setActiveView(view)}>
                {label}
              </button>
            ))}
          </nav>
          <div className="mt-6 border-t border-border pt-4 text-sm text-muted">
            <p>Branch-local data</p>
            <p className="mt-1">Last Platform sync is shown in Overview.</p>
          </div>
        </aside>

        <section>
          {error && <p className="mb-3 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800">{error}</p>}
          {activeView === 'dashboard' && <Dashboard data={dashboard} />}
          {activeView === 'performance' && <StaffPerformance rows={performance} />}
          {activeView === 'reports' && <Reports accessToken={accessToken} rows={reportRows} selectedReport={selectedReport} onSelectReport={setSelectedReport} />}
          {activeView === 'synchronization' && <Synchronization accessToken={accessToken} data={synchronization} onRefresh={() => window.marketlensShop.synchronization.status(accessToken).then(setSynchronization)} />}
        </section>
      </div>
    </main>
  );
}

function Login({ username, password, error, onUsername, onPassword, onSubmit }: { username: string; password: string; error?: string; onUsername: (value: string) => void; onPassword: (value: string) => void; onSubmit: (event: FormEvent<HTMLFormElement>) => void }) {
  return <main className="grid min-h-screen place-items-center bg-background p-6 text-foreground"><form className="w-full max-w-sm rounded-md border border-border bg-surface p-6" onSubmit={onSubmit}><p className="text-sm font-semibold text-teal-700">MARKETLENS / SHOP ADMIN</p><h1 className="mt-1 text-xl font-bold">Sign in</h1><label className="mt-5 block text-sm font-medium">Username<input className="mt-1 w-full rounded-md border border-border bg-background px-3 py-2" value={username} onChange={(event) => onUsername(event.target.value)} autoComplete="username" /></label><label className="mt-3 block text-sm font-medium">Password<input className="mt-1 w-full rounded-md border border-border bg-background px-3 py-2" type="password" value={password} onChange={(event) => onPassword(event.target.value)} autoComplete="current-password" /></label>{error && <p className="mt-3 text-sm text-red-700">{error}</p>}<button className="mt-5 w-full rounded-md bg-teal-700 px-3 py-2 text-sm font-semibold text-white" type="submit">Sign in</button></form></main>;
}

function Dashboard({ data }: { data: Record<string, unknown> }) {
  return <>
    <div className="flex flex-wrap items-end justify-between gap-3">
      <div><h2 className="text-lg font-bold">Today at a glance</h2><p className="text-sm text-muted">Securely derived from the local ledger and operational records.</p></div>
      <input aria-label="Dashboard date" className="rounded-md border border-border bg-surface px-3 py-2 text-sm" type="date" defaultValue={new Date().toISOString().slice(0, 10)} />
    </div>
    <div className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      {dashboardCards.map((label) => <article key={label} className="rounded-md border border-border bg-surface p-4"><p className="text-sm text-muted">{label}</p><p className="mt-2 text-2xl font-bold tabular-nums">{display(data[dashboardKey(label)])}</p></article>)}
    </div>
    <div className="mt-5 grid gap-4 xl:grid-cols-2">
      <article className="rounded-md border border-border bg-surface p-4"><h3 className="font-semibold">Payment breakdown</h3><p className="mt-3 text-sm text-muted">Cash, bank, POS, mobile money, and credit totals appear here after authorized data retrieval.</p></article>
      <article className="rounded-md border border-border bg-surface p-4"><h3 className="font-semibold">Synchronization</h3><p className="mt-3 text-sm text-muted">Last Platform synchronization and pending local synchronization records appear here.</p></article>
    </div>
  </>;
}

function StaffPerformance({ rows }: { rows: Array<Record<string, unknown>> }) {
  return <>
    <div className="flex flex-wrap items-end justify-between gap-3"><div><h2 className="text-lg font-bold">Staff performance</h2><p className="text-sm text-muted">Sales, service delivery, cash variance, and operational activity.</p></div><DateRange /></div>
    <div className="mt-5 overflow-x-auto rounded-md border border-border bg-surface">
      <table className="w-full min-w-[48rem] text-left text-sm"><thead className="border-b border-border text-muted"><tr>{['Staff', 'Sales value', 'Services completed', 'Outstanding earnings', 'Cash variance', 'Review indicators'].map((header) => <th className="px-4 py-3 font-medium" key={header}>{header}</th>)}</tr></thead><tbody>{rows.length === 0 ? <tr><td colSpan={6} className="px-4 py-8 text-center text-muted">No staff activity exists in this date range.</td></tr> : rows.map((row) => <tr className="border-t border-border" key={String(row.staffUserUuid)}><td className="px-4 py-3">{display(row.staffName)}</td><td className="px-4 py-3">{display(row.salesValue)}</td><td className="px-4 py-3">{display(row.servicesCompleted)}</td><td className="px-4 py-3">{display(row.outstandingEarnings)}</td><td className="px-4 py-3">{display(row.cashVariance)}</td><td className="px-4 py-3">{Array.isArray(row.indicators) ? row.indicators.join(', ') : '--'}</td></tr>)}</tbody></table>
    </div>
  </>;
}

function Reports({ accessToken, rows, selectedReport, onSelectReport }: { accessToken: string; rows: Array<Record<string, unknown>>; selectedReport: string; onSelectReport: (value: string) => void }) {
  const today = new Date().toISOString().slice(0, 10);
  const exportReport = (format: 'csv' | 'pdf') => void window.marketlensShop.reporting.export(accessToken, reportTypeByLabel[selectedReport], { from: today, to: today }, format);
  return <>
    <div className="flex flex-wrap items-end justify-between gap-3"><div><h2 className="text-lg font-bold">Local reports</h2><p className="text-sm text-muted">Exportable local operational reports for this Shop App branch.</p></div><DateRange /></div>
    <div className="mt-5 grid gap-4 lg:grid-cols-[17rem_1fr]">
      <div className="grid max-h-[34rem] gap-1 overflow-y-auto rounded-md border border-border bg-surface p-2">{reports.map((report) => <button key={report} onClick={() => onSelectReport(report)} className={`rounded px-3 py-2 text-left text-sm ${selectedReport === report ? 'bg-teal-700 text-white' : 'hover:bg-surface-muted'}`}>{report}</button>)}</div>
      <article className="rounded-md border border-border bg-surface p-4"><div className="flex flex-wrap items-center justify-between gap-3"><h3 className="font-semibold">{selectedReport}</h3><div className="flex gap-2"><button onClick={() => exportReport('csv')} className="rounded-md border border-border px-3 py-2 text-sm">Export CSV</button><button onClick={() => exportReport('pdf')} className="rounded-md border border-border px-3 py-2 text-sm">Queue PDF</button></div></div>{rows.length === 0 ? <p className="mt-10 text-center text-sm text-muted">No records match this report filter.</p> : <pre className="mt-4 max-h-[28rem] overflow-auto bg-surface-muted p-3 text-xs">{JSON.stringify(rows, null, 2)}</pre>}</article>
    </div>
  </>;
}

function Synchronization({ accessToken, data, onRefresh }: { accessToken: string; data: Record<string, unknown>; onRefresh: () => Promise<void> }) {
  const retry = async (queueUuid?: string) => { await window.marketlensShop.synchronization.retry(accessToken, queueUuid); await onRefresh(); };
  const metrics = [['Platform status', data.platformConnectivity], ['Last successful sync', data.lastSynchronization], ['Last push', data.lastPush], ['Last pull', data.lastPull], ['Pending records', data.pendingRecords], ['Failed records', data.failedRecords], ['Conflict records', data.conflictRecords], ['Protocol version', data.protocolVersion], ['Device status', data.deviceStatus]];
  const failures = Array.isArray(data.failures) ? data.failures as Array<Record<string, unknown>> : [];
  const conflicts = Array.isArray(data.conflicts) ? data.conflicts as Array<Record<string, unknown>> : [];
  return <><div className="flex flex-wrap items-end justify-between gap-3"><div><h2 className="text-lg font-bold">Synchronization</h2><p className="text-sm text-muted">Signed, queued synchronization with the Platform.</p></div><button onClick={() => void retry()} className="rounded-md border border-border px-3 py-2 text-sm">Retry eligible records</button></div><div className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">{metrics.map(([label, value]) => <article key={label} className="rounded-md border border-border bg-surface p-4"><p className="text-sm text-muted">{label}</p><p className="mt-2 font-semibold">{display(value)}</p></article>)}</div><div className="mt-5 grid gap-4 xl:grid-cols-2"><article className="rounded-md border border-border bg-surface p-4"><h3 className="font-semibold">Recent failures</h3>{failures.length === 0 ? <p className="mt-3 text-sm text-muted">No failed synchronization records.</p> : failures.map((failure) => <div key={String(failure.queueUuid)} className="mt-3 border-t border-border pt-3 text-sm"><p>{display(failure.errorCode)}</p><p className="text-muted">{display(failure.detail)}</p><button onClick={() => void retry(String(failure.queueUuid))} className="mt-2 text-teal-700">Retry record</button></div>)}</article><article className="rounded-md border border-border bg-surface p-4"><h3 className="font-semibold">Open conflicts</h3>{conflicts.length === 0 ? <p className="mt-3 text-sm text-muted">No open conflicts.</p> : conflicts.map((conflict) => <div key={String(conflict.uuid)} className="mt-3 border-t border-border pt-3 text-sm"><p>{display(conflict.aggregateType)} / {display(conflict.aggregateUuid)}</p><p className="text-muted">{display(conflict.rule)}</p></div>)}</article></div></>;
}

function DateRange() { return <div className="flex gap-2"><input aria-label="From date" className="rounded-md border border-border bg-surface px-3 py-2 text-sm" type="date" /><input aria-label="To date" className="rounded-md border border-border bg-surface px-3 py-2 text-sm" type="date" /></div>; }

function display(value: unknown) { return value === undefined || value === null || value === '' ? '--' : String(value); }
function dashboardKey(label: string) { return ({ "Today's sales": 'todaySales', Expenses: 'expenses', 'Gross profit': 'grossProfit', 'Estimated net profit': 'estimatedNetProfit', 'Customer debt': 'customerDebt', 'Supplier balance': 'supplierBalance', 'Stock value': 'stockValue', 'Low-stock count': 'lowStockCount', 'Open cashier shifts': 'openCashierShifts', 'Cashier shortages': 'cashierShortages', 'Services in progress': 'servicesInProgress', 'Service revenue': 'serviceRevenue', 'Pending staff earnings': 'pendingStaffEarnings', 'Last Platform synchronization': 'lastPlatformSynchronization', 'Authorized Client Apps': 'authorizedClientApps' } as Record<string, string>)[label]; }
