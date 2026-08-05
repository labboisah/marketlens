import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Button, Input, Select, StatusBadge } from '@marketlens/ui';
import { useMemo, useState } from 'react';
import { useClientUiStore } from '../state/client-ui-store';
import {
  approveStockAdjustment,
  approveStockCountSession,
  approveStockTransfer,
  createStockAdjustment,
  createStockCountSession,
  createStockTransfer,
  getStockCountSession,
  listDamagedStock,
  listDetailedStock,
  listExpiredStock,
  listInventoryProducts,
  listProductBatches,
  listSerialNumbers,
  listStockAdjustments,
  listStockCountSessions,
  listStockMovements,
  listStockTransfers,
  listWarehouses,
  saveStockCountItem,
  submitStockCountSession,
  submitStockTransfer,
} from '../services/inventory-service';

type InventoryTab =
  | 'product-list'
  | 'current-stock'
  | 'low-stock'
  | 'out-of-stock'
  | 'stock-movements'
  | 'stock-count'
  | 'stock-adjustments'
  | 'damaged-stock'
  | 'expired-stock'
  | 'product-batches'
  | 'serial-numbers'
  | 'stock-transfers';

function formatQty(value: string | number): string {
  const num = Number(value ?? 0);
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 3 }).format(Number.isFinite(num) ? num : 0);
}

function tabLabel(tab: InventoryTab): string {
  switch (tab) {
    case 'product-list': return 'Product list';
    case 'current-stock': return 'Current stock';
    case 'low-stock': return 'Low stock';
    case 'out-of-stock': return 'Out of stock';
    case 'stock-movements': return 'Stock movements';
    case 'stock-count': return 'Stock count';
    case 'stock-adjustments': return 'Stock adjustments';
    case 'damaged-stock': return 'Damaged stock';
    case 'expired-stock': return 'Expired stock';
    case 'product-batches': return 'Product batches';
    case 'serial-numbers': return 'Serial numbers';
    case 'stock-transfers': return 'Stock transfers';
  }
}

function toneForStatus(value: string): 'success' | 'warning' | 'danger' | 'information' | 'neutral' {
  const normalized = value.toLowerCase();
  if (normalized.includes('approved') || normalized.includes('open') || normalized.includes('increase')) return 'success';
  if (normalized.includes('pending') || normalized.includes('submitted') || normalized.includes('low')) return 'warning';
  if (normalized.includes('rejected') || normalized.includes('out') || normalized.includes('decrease')) return 'danger';
  if (normalized.includes('draft')) return 'neutral';
  return 'information';
}

export function InventoryScreen() {
  const queryClient = useQueryClient();
  const deviceUuid = useClientUiStore((state) => state.deviceUuid);
  const connectionState = useClientUiStore((state) => state.connectionState);
  const notify = useClientUiStore((state) => state.notify);
  const currentUserUuid = useClientUiStore((state) => state.currentUser?.uuid);

  const [tab, setTab] = useState<InventoryTab>('product-list');
  const [warehouseFilter, setWarehouseFilter] = useState('');

  const [countWarehouseUuid, setCountWarehouseUuid] = useState('');
  const [countNotes, setCountNotes] = useState('');
  const [activeCountSessionUuid, setActiveCountSessionUuid] = useState('');
  const [countProductUuid, setCountProductUuid] = useState('');
  const [countUnitUuid, setCountUnitUuid] = useState('');
  const [countBarcode, setCountBarcode] = useState('');
  const [countedQuantity, setCountedQuantity] = useState('0');
  const [countItemNote, setCountItemNote] = useState('');

  const [adjustmentWarehouseUuid, setAdjustmentWarehouseUuid] = useState('');
  const [adjustmentProductUuid, setAdjustmentProductUuid] = useState('');
  const [adjustmentUnitUuid, setAdjustmentUnitUuid] = useState('');
  const [adjustmentType, setAdjustmentType] = useState<'increase' | 'decrease'>('decrease');
  const [adjustmentQuantity, setAdjustmentQuantity] = useState('0');
  const [adjustmentReason, setAdjustmentReason] = useState('');
  const [adjustmentNote, setAdjustmentNote] = useState('');
  const [adjustmentApprovalRequested, setAdjustmentApprovalRequested] = useState(true);

  const [transferFromWarehouseUuid, setTransferFromWarehouseUuid] = useState('');
  const [transferToWarehouseUuid, setTransferToWarehouseUuid] = useState('');
  const [transferReason, setTransferReason] = useState('');
  const [transferApprovalRequested, setTransferApprovalRequested] = useState(true);
  const [transferProductUuid, setTransferProductUuid] = useState('');
  const [transferUnitUuid, setTransferUnitUuid] = useState('');
  const [transferQuantity, setTransferQuantity] = useState('0');

  const canQuery = Boolean(deviceUuid && connectionState === 'connected');

  const sessionLoader = async () => {
    const session = await window.marketlensClient.session.load();
    if (!session || !deviceUuid) throw new Error('Session not available.');
    return session;
  };

  const productsQuery = useQuery({
    queryKey: ['inventory-products'],
    queryFn: async () => {
      const session = await sessionLoader();
      return listInventoryProducts({ shopBaseUrl: session.shopBaseUrl, accessToken: session.token, deviceUuid: deviceUuid! });
    },
    enabled: canQuery,
    staleTime: 20_000,
    refetchOnWindowFocus: false,
  });

  const warehousesQuery = useQuery({
    queryKey: ['inventory-warehouses'],
    queryFn: async () => {
      const session = await sessionLoader();
      return listWarehouses({ shopBaseUrl: session.shopBaseUrl, accessToken: session.token, deviceUuid: deviceUuid! });
    },
    enabled: canQuery,
    staleTime: 20_000,
    refetchOnWindowFocus: false,
  });

  const currentStockQuery = useQuery({
    queryKey: ['inventory-current-stock', warehouseFilter],
    queryFn: async () => {
      const session = await sessionLoader();
      return listDetailedStock({ shopBaseUrl: session.shopBaseUrl, accessToken: session.token, deviceUuid: deviceUuid!, warehouseUuid: warehouseFilter || undefined, status: 'all' });
    },
    enabled: canQuery,
    staleTime: 15_000,
    refetchOnWindowFocus: false,
  });

  const lowStockQuery = useQuery({
    queryKey: ['inventory-low-stock', warehouseFilter],
    queryFn: async () => {
      const session = await sessionLoader();
      return listDetailedStock({ shopBaseUrl: session.shopBaseUrl, accessToken: session.token, deviceUuid: deviceUuid!, warehouseUuid: warehouseFilter || undefined, status: 'low' });
    },
    enabled: canQuery,
    staleTime: 15_000,
    refetchOnWindowFocus: false,
  });

  const outStockQuery = useQuery({
    queryKey: ['inventory-out-stock', warehouseFilter],
    queryFn: async () => {
      const session = await sessionLoader();
      return listDetailedStock({ shopBaseUrl: session.shopBaseUrl, accessToken: session.token, deviceUuid: deviceUuid!, warehouseUuid: warehouseFilter || undefined, status: 'out' });
    },
    enabled: canQuery,
    staleTime: 15_000,
    refetchOnWindowFocus: false,
  });

  const movementQuery = useQuery({
    queryKey: ['inventory-movements'],
    queryFn: async () => {
      const session = await sessionLoader();
      return listStockMovements({ shopBaseUrl: session.shopBaseUrl, accessToken: session.token, deviceUuid: deviceUuid! });
    },
    enabled: canQuery,
    staleTime: 15_000,
    refetchOnWindowFocus: false,
  });

  const damagedQuery = useQuery({
    queryKey: ['inventory-damaged'],
    queryFn: async () => {
      const session = await sessionLoader();
      return listDamagedStock({ shopBaseUrl: session.shopBaseUrl, accessToken: session.token, deviceUuid: deviceUuid! });
    },
    enabled: canQuery,
    staleTime: 15_000,
    refetchOnWindowFocus: false,
  });

  const expiredQuery = useQuery({
    queryKey: ['inventory-expired'],
    queryFn: async () => {
      const session = await sessionLoader();
      return listExpiredStock({ shopBaseUrl: session.shopBaseUrl, accessToken: session.token, deviceUuid: deviceUuid! });
    },
    enabled: canQuery,
    staleTime: 15_000,
    refetchOnWindowFocus: false,
  });

  const batchesQuery = useQuery({
    queryKey: ['inventory-batches'],
    queryFn: async () => {
      const session = await sessionLoader();
      return listProductBatches({ shopBaseUrl: session.shopBaseUrl, accessToken: session.token, deviceUuid: deviceUuid! });
    },
    enabled: canQuery,
    staleTime: 15_000,
    refetchOnWindowFocus: false,
  });

  const serialsQuery = useQuery({
    queryKey: ['inventory-serials'],
    queryFn: async () => {
      const session = await sessionLoader();
      return listSerialNumbers({ shopBaseUrl: session.shopBaseUrl, accessToken: session.token, deviceUuid: deviceUuid! });
    },
    enabled: canQuery,
    staleTime: 15_000,
    refetchOnWindowFocus: false,
  });

  const stockCountSessionsQuery = useQuery({
    queryKey: ['inventory-stock-count-sessions'],
    queryFn: async () => {
      const session = await sessionLoader();
      return listStockCountSessions({ shopBaseUrl: session.shopBaseUrl, accessToken: session.token, deviceUuid: deviceUuid! });
    },
    enabled: canQuery,
    staleTime: 5000,
    refetchOnWindowFocus: false,
  });

  const stockCountSessionDetailQuery = useQuery({
    queryKey: ['inventory-stock-count-session-detail', activeCountSessionUuid],
    queryFn: async () => {
      const session = await sessionLoader();
      return getStockCountSession({ shopBaseUrl: session.shopBaseUrl, accessToken: session.token, deviceUuid: deviceUuid!, sessionUuid: activeCountSessionUuid });
    },
    enabled: canQuery && Boolean(activeCountSessionUuid),
    staleTime: 2000,
    refetchOnWindowFocus: false,
  });

  const adjustmentsQuery = useQuery({
    queryKey: ['inventory-stock-adjustments'],
    queryFn: async () => {
      const session = await sessionLoader();
      return listStockAdjustments({ shopBaseUrl: session.shopBaseUrl, accessToken: session.token, deviceUuid: deviceUuid! });
    },
    enabled: canQuery,
    staleTime: 5000,
    refetchOnWindowFocus: false,
  });

  const transfersQuery = useQuery({
    queryKey: ['inventory-stock-transfers'],
    queryFn: async () => {
      const session = await sessionLoader();
      return listStockTransfers({ shopBaseUrl: session.shopBaseUrl, accessToken: session.token, deviceUuid: deviceUuid! });
    },
    enabled: canQuery,
    staleTime: 5000,
    refetchOnWindowFocus: false,
  });

  const createCountSessionMutation = useMutation({
    mutationFn: async () => {
      const session = await sessionLoader();
      return createStockCountSession({
        shopBaseUrl: session.shopBaseUrl,
        accessToken: session.token,
        deviceUuid: deviceUuid!,
        warehouseUuid: countWarehouseUuid,
        notes: countNotes || undefined,
        createdByUserUuid: currentUserUuid,
      });
    },
    onSuccess: async (result) => {
      setActiveCountSessionUuid(result.uuid);
      notify({ tone: 'success', title: 'Count session created', message: 'Draft stock count session created in Shop App.' });
      await queryClient.invalidateQueries({ queryKey: ['inventory-stock-count-sessions'] });
    },
    onError: (error) => {
      notify({ tone: 'danger', title: 'Count session failed', message: error instanceof Error ? error.message : 'Unable to create stock count session.' });
    },
  });

  const saveCountItemMutation = useMutation({
    mutationFn: async () => {
      const session = await sessionLoader();
      return saveStockCountItem({
        shopBaseUrl: session.shopBaseUrl,
        accessToken: session.token,
        deviceUuid: deviceUuid!,
        sessionUuid: activeCountSessionUuid,
        productUuid: countProductUuid,
        unitUuid: countUnitUuid,
        barcode: countBarcode || undefined,
        countedQuantity,
        note: countItemNote || undefined,
      });
    },
    onSuccess: async () => {
      notify({ tone: 'success', title: 'Count item saved', message: 'Counted quantity saved as draft in Shop App.' });
      await queryClient.invalidateQueries({ queryKey: ['inventory-stock-count-session-detail', activeCountSessionUuid] });
    },
    onError: (error) => {
      notify({ tone: 'danger', title: 'Save failed', message: error instanceof Error ? error.message : 'Unable to save count item.' });
    },
  });

  const submitCountMutation = useMutation({
    mutationFn: async () => {
      const session = await sessionLoader();
      return submitStockCountSession({
        shopBaseUrl: session.shopBaseUrl,
        accessToken: session.token,
        deviceUuid: deviceUuid!,
        sessionUuid: activeCountSessionUuid,
      });
    },
    onSuccess: async () => {
      notify({ tone: 'success', title: 'Count submitted', message: 'Stock count submitted for approval.' });
      await queryClient.invalidateQueries({ queryKey: ['inventory-stock-count-sessions'] });
      await queryClient.invalidateQueries({ queryKey: ['inventory-stock-count-session-detail', activeCountSessionUuid] });
    },
    onError: (error) => {
      notify({ tone: 'danger', title: 'Submit failed', message: error instanceof Error ? error.message : 'Unable to submit stock count.' });
    },
  });

  const approveCountMutation = useMutation({
    mutationFn: async () => {
      const session = await sessionLoader();
      return approveStockCountSession({
        shopBaseUrl: session.shopBaseUrl,
        accessToken: session.token,
        deviceUuid: deviceUuid!,
        sessionUuid: activeCountSessionUuid,
        approvedByUserUuid: currentUserUuid,
      });
    },
    onSuccess: async () => {
      notify({ tone: 'success', title: 'Count approved', message: 'Shop App applied variances and recorded stock movements.' });
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['inventory-stock-count-sessions'] }),
        queryClient.invalidateQueries({ queryKey: ['inventory-stock-count-session-detail', activeCountSessionUuid] }),
        queryClient.invalidateQueries({ queryKey: ['inventory-current-stock'] }),
        queryClient.invalidateQueries({ queryKey: ['inventory-movements'] }),
      ]);
    },
    onError: (error) => {
      notify({ tone: 'danger', title: 'Approval failed', message: error instanceof Error ? error.message : 'Unable to approve stock count.' });
    },
  });

  const createAdjustmentMutation = useMutation({
    mutationFn: async () => {
      const session = await sessionLoader();
      return createStockAdjustment({
        shopBaseUrl: session.shopBaseUrl,
        accessToken: session.token,
        deviceUuid: deviceUuid!,
        warehouseUuid: adjustmentWarehouseUuid,
        productUuid: adjustmentProductUuid,
        unitUuid: adjustmentUnitUuid,
        adjustmentType,
        quantity: adjustmentQuantity,
        reason: adjustmentReason,
        supportingNote: adjustmentNote || undefined,
        approvalRequested: adjustmentApprovalRequested,
        createdByUserUuid: currentUserUuid,
      });
    },
    onSuccess: async () => {
      notify({ tone: 'success', title: 'Adjustment request saved', message: 'Stock adjustment submitted to Shop App.' });
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['inventory-stock-adjustments'] }),
        queryClient.invalidateQueries({ queryKey: ['inventory-current-stock'] }),
        queryClient.invalidateQueries({ queryKey: ['inventory-movements'] }),
      ]);
    },
    onError: (error) => {
      notify({ tone: 'danger', title: 'Adjustment failed', message: error instanceof Error ? error.message : 'Unable to create adjustment.' });
    },
  });

  const approveAdjustmentMutation = useMutation({
    mutationFn: async (adjustmentUuid: string) => {
      const session = await sessionLoader();
      return approveStockAdjustment({
        shopBaseUrl: session.shopBaseUrl,
        accessToken: session.token,
        deviceUuid: deviceUuid!,
        adjustmentUuid,
        approvedByUserUuid: currentUserUuid,
      });
    },
    onSuccess: async () => {
      notify({ tone: 'success', title: 'Adjustment approved', message: 'Shop App applied stock adjustment and movement.' });
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['inventory-stock-adjustments'] }),
        queryClient.invalidateQueries({ queryKey: ['inventory-current-stock'] }),
        queryClient.invalidateQueries({ queryKey: ['inventory-movements'] }),
      ]);
    },
    onError: (error) => {
      notify({ tone: 'danger', title: 'Approval failed', message: error instanceof Error ? error.message : 'Unable to approve adjustment.' });
    },
  });

  const createTransferMutation = useMutation({
    mutationFn: async () => {
      const session = await sessionLoader();
      return createStockTransfer({
        shopBaseUrl: session.shopBaseUrl,
        accessToken: session.token,
        deviceUuid: deviceUuid!,
        fromWarehouseUuid: transferFromWarehouseUuid,
        toWarehouseUuid: transferToWarehouseUuid,
        reason: transferReason || undefined,
        approvalRequested: transferApprovalRequested,
        requestedByUserUuid: currentUserUuid,
        items: [{ productUuid: transferProductUuid, unitUuid: transferUnitUuid, quantity: transferQuantity }],
      });
    },
    onSuccess: async () => {
      notify({ tone: 'success', title: 'Transfer created', message: 'Stock transfer request sent to Shop App.' });
      await queryClient.invalidateQueries({ queryKey: ['inventory-stock-transfers'] });
    },
    onError: (error) => {
      notify({ tone: 'danger', title: 'Transfer failed', message: error instanceof Error ? error.message : 'Unable to create transfer.' });
    },
  });

  const submitTransferMutation = useMutation({
    mutationFn: async (transferUuid: string) => {
      const session = await sessionLoader();
      return submitStockTransfer({ shopBaseUrl: session.shopBaseUrl, accessToken: session.token, deviceUuid: deviceUuid!, transferUuid });
    },
    onSuccess: async () => {
      notify({ tone: 'success', title: 'Transfer submitted', message: 'Transfer submitted for approval.' });
      await queryClient.invalidateQueries({ queryKey: ['inventory-stock-transfers'] });
    },
  });

  const approveTransferMutation = useMutation({
    mutationFn: async (transferUuid: string) => {
      const session = await sessionLoader();
      return approveStockTransfer({
        shopBaseUrl: session.shopBaseUrl,
        accessToken: session.token,
        deviceUuid: deviceUuid!,
        transferUuid,
        approvedByUserUuid: currentUserUuid,
      });
    },
    onSuccess: async () => {
      notify({ tone: 'success', title: 'Transfer approved', message: 'Shop App executed stock transfer balances and movement.' });
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['inventory-stock-transfers'] }),
        queryClient.invalidateQueries({ queryKey: ['inventory-current-stock'] }),
        queryClient.invalidateQueries({ queryKey: ['inventory-movements'] }),
      ]);
    },
  });

  const productUnitOptions = useMemo(() => {
    const firstStock = (currentStockQuery.data ?? [])[0];
    if (!firstStock) return [];
    return [{ value: firstStock.unitUuid, label: `${firstStock.unitCode ?? firstStock.unitName ?? firstStock.unitUuid}` }];
  }, [currentStockQuery.data]);

  const tabs: InventoryTab[] = [
    'product-list',
    'current-stock',
    'low-stock',
    'out-of-stock',
    'stock-movements',
    'stock-count',
    'stock-adjustments',
    'damaged-stock',
    'expired-stock',
    'product-batches',
    'serial-numbers',
    'stock-transfers',
  ];

  return (
    <section className="min-h-screen bg-background p-6 text-foreground">
      <div className="mx-auto max-w-7xl space-y-5">
        <header className="rounded-2xl border border-border bg-surface p-5 shadow-sm">
          <h1 className="text-2xl font-semibold">Inventory and stockkeeper interface</h1>
          <p className="mt-1 text-sm text-muted">Client actions submit requests to Shop App. Stock balances are updated only by Shop App workflows.</p>
        </header>

        <div className="rounded-2xl border border-border bg-surface p-4 shadow-sm">
          <div className="flex flex-wrap gap-2">
            {tabs.map((value) => (
              <Button key={value} variant={tab === value ? 'secondary' : 'ghost'} onClick={() => setTab(value)}>
                {tabLabel(value)}
              </Button>
            ))}
          </div>
          <div className="mt-3 max-w-sm">
            <Select
              label="Warehouse filter"
              value={warehouseFilter}
              onChange={(event) => setWarehouseFilter(event.target.value)}
              options={[{ value: '', label: 'All warehouses' }, ...(warehousesQuery.data ?? []).map((warehouse) => ({ value: warehouse.uuid, label: `${warehouse.name} (${warehouse.code})` }))]}
            />
          </div>
        </div>

        {tab === 'product-list' ? (
          <ListPanel title="Product list" rows={(productsQuery.data ?? []).map((product) => ({
            id: product.uuid,
            primary: `${product.name} (${product.sku})`,
            secondary: `Reorder: ${product.reorderLevel ?? 'n/a'} · Max: ${product.maxStock ?? 'n/a'} · Active: ${product.active}`,
          }))} />
        ) : null}

        {tab === 'current-stock' ? (
          <ListPanel title="Current stock" rows={(currentStockQuery.data ?? []).map((row) => ({
            id: row.uuid,
            primary: `${row.name} (${row.sku})`,
            secondary: `Qty: ${formatQty(row.quantity)} ${row.unitCode ?? row.unitName ?? ''} · Warehouse: ${row.warehouseName} · Reorder: ${row.reorderLevel ?? 'n/a'}`,
          }))} />
        ) : null}

        {tab === 'low-stock' ? (
          <ListPanel title="Low stock" rows={(lowStockQuery.data ?? []).map((row) => ({
            id: row.uuid,
            primary: `${row.name} (${row.sku})`,
            secondary: `Qty: ${formatQty(row.quantity)} · Reorder: ${row.reorderLevel ?? 'n/a'} · Warehouse: ${row.warehouseName}`,
          }))} />
        ) : null}

        {tab === 'out-of-stock' ? (
          <ListPanel title="Out of stock" rows={(outStockQuery.data ?? []).map((row) => ({
            id: row.uuid,
            primary: `${row.name} (${row.sku})`,
            secondary: `Qty: ${formatQty(row.quantity)} · Warehouse: ${row.warehouseName}`,
          }))} />
        ) : null}

        {tab === 'stock-movements' ? (
          <ListPanel title="Stock movements" rows={(movementQuery.data ?? []).map((row) => ({
            id: row.uuid,
            primary: `${row.movementType} · Product ${row.productUuid}`,
            secondary: `Qty: ${formatQty(row.quantityBase)} · Ref: ${row.reference ?? 'n/a'} · ${new Date(row.createdAt).toLocaleString()}`,
            badge: row.movementType,
          }))} />
        ) : null}

        {tab === 'damaged-stock' ? (
          <ListPanel title="Damaged stock" rows={(damagedQuery.data ?? []).map((row) => ({
            id: row.uuid,
            primary: `${row.productUuid} · ${row.movementType}`,
            secondary: `Qty: ${formatQty(row.quantityBase)} · ${new Date(row.createdAt).toLocaleString()}`,
          }))} />
        ) : null}

        {tab === 'expired-stock' ? (
          <ListPanel title="Expired stock" rows={(expiredQuery.data ?? []).map((row) => ({
            id: String(row.uuid),
            primary: `${String(row.name)} (${String(row.sku)})`,
            secondary: `Batch: ${String(row.batchNumber)} · Expiry: ${String(row.expiryAt)} · Qty: ${formatQty(String(row.quantity))}`,
          }))} />
        ) : null}

        {tab === 'product-batches' ? (
          <ListPanel title="Product batches" rows={(batchesQuery.data ?? []).map((row) => ({
            id: row.uuid,
            primary: `${row.productUuid} · ${row.batchNumber}`,
            secondary: `Expiry: ${row.expiryAt ?? 'n/a'} · Qty: ${formatQty(row.quantity)}`,
          }))} />
        ) : null}

        {tab === 'serial-numbers' ? (
          <ListPanel title="Serial numbers" rows={(serialsQuery.data ?? []).map((row) => ({
            id: row.uuid,
            primary: row.serial,
            secondary: `Product: ${row.productUuid} · Sold: ${row.sold}`,
          }))} />
        ) : null}

        {tab === 'stock-count' ? (
          <section className="grid gap-4 xl:grid-cols-2">
            <article className="rounded-2xl border border-border bg-surface p-4 shadow-sm">
              <h2 className="text-base font-semibold">Create count session</h2>
              <div className="mt-3 grid gap-2">
                <Select
                  label="Warehouse"
                  value={countWarehouseUuid}
                  onChange={(event) => setCountWarehouseUuid(event.target.value)}
                  options={(warehousesQuery.data ?? []).map((warehouse) => ({ value: warehouse.uuid, label: `${warehouse.name} (${warehouse.code})` }))}
                />
                <Input label="Session note" value={countNotes} onChange={(event) => setCountNotes(event.target.value)} />
                <Button disabled={!countWarehouseUuid || createCountSessionMutation.isPending} onClick={() => createCountSessionMutation.mutate()}>
                  {createCountSessionMutation.isPending ? 'Creating...' : 'Create session'}
                </Button>
              </div>

              <h3 className="mt-4 text-sm font-semibold">Sessions</h3>
              <div className="mt-2 max-h-60 space-y-2 overflow-auto">
                {(stockCountSessionsQuery.data ?? []).map((session) => (
                  <button key={session.uuid} type="button" className={`block w-full rounded-lg border border-border px-3 py-2 text-left ${activeCountSessionUuid === session.uuid ? 'bg-primary/10' : 'hover:bg-surface-muted'}`} onClick={() => setActiveCountSessionUuid(session.uuid)}>
                    <p className="text-sm font-medium">{session.uuid.slice(0, 8)} · {session.warehouseName ?? session.warehouseUuid}</p>
                    <p className="text-xs text-muted">Status: {session.status} · Approval: {session.approvalState}</p>
                  </button>
                ))}
              </div>
            </article>

            <article className="rounded-2xl border border-border bg-surface p-4 shadow-sm">
              <h2 className="text-base font-semibold">Count lines and variance</h2>
              <div className="mt-3 grid gap-2">
                <Select
                  label="Product"
                  value={countProductUuid}
                  onChange={(event) => setCountProductUuid(event.target.value)}
                  options={(productsQuery.data ?? []).map((product) => ({ value: product.uuid, label: `${product.name} (${product.sku})` }))}
                />
                <Select
                  label="Unit"
                  value={countUnitUuid}
                  onChange={(event) => setCountUnitUuid(event.target.value)}
                  options={productUnitOptions}
                />
                <Input label="Barcode scanning" value={countBarcode} onChange={(event) => setCountBarcode(event.target.value)} placeholder="Scan or type barcode" />
                <Input label="Counted quantity" value={countedQuantity} onChange={(event) => setCountedQuantity(event.target.value)} inputMode="decimal" />
                <Input label="Line note" value={countItemNote} onChange={(event) => setCountItemNote(event.target.value)} />
                <Button disabled={!activeCountSessionUuid || !countProductUuid || !countUnitUuid || saveCountItemMutation.isPending} onClick={() => saveCountItemMutation.mutate()}>
                  {saveCountItemMutation.isPending ? 'Saving...' : 'Save draft line'}
                </Button>
              </div>

              <div className="mt-3 rounded-xl border border-border bg-background p-3">
                <p className="text-sm font-semibold">Session lines</p>
                <div className="mt-2 max-h-48 space-y-2 overflow-auto">
                  {(stockCountSessionDetailQuery.data?.items ?? []).map((item) => (
                    <div key={item.uuid} className="rounded-md border border-border px-2 py-2 text-xs">
                      <p>Product: {item.productUuid}</p>
                      <p>System: {formatQty(item.systemQuantity)} · Counted: {formatQty(item.countedQuantity)} · Variance: {formatQty(item.varianceQuantity)}</p>
                      <p>Barcode: {item.barcode ?? 'n/a'}</p>
                    </div>
                  ))}
                </div>
              </div>

              <div className="mt-3 flex gap-2">
                <Button variant="secondary" disabled={!activeCountSessionUuid || submitCountMutation.isPending} onClick={() => submitCountMutation.mutate()}>
                  {submitCountMutation.isPending ? 'Submitting...' : 'Submit count'}
                </Button>
                <Button variant="secondary" disabled={!activeCountSessionUuid || approveCountMutation.isPending} onClick={() => approveCountMutation.mutate()}>
                  {approveCountMutation.isPending ? 'Approving...' : 'Approve count'}
                </Button>
              </div>
            </article>
          </section>
        ) : null}

        {tab === 'stock-adjustments' ? (
          <section className="grid gap-4 xl:grid-cols-2">
            <article className="rounded-2xl border border-border bg-surface p-4 shadow-sm">
              <h2 className="text-base font-semibold">Stock adjustments</h2>
              <div className="mt-3 grid gap-2">
                <Select
                  label="Warehouse"
                  value={adjustmentWarehouseUuid}
                  onChange={(event) => setAdjustmentWarehouseUuid(event.target.value)}
                  options={(warehousesQuery.data ?? []).map((warehouse) => ({ value: warehouse.uuid, label: `${warehouse.name} (${warehouse.code})` }))}
                />
                <Select
                  label="Product"
                  value={adjustmentProductUuid}
                  onChange={(event) => setAdjustmentProductUuid(event.target.value)}
                  options={(productsQuery.data ?? []).map((product) => ({ value: product.uuid, label: `${product.name} (${product.sku})` }))}
                />
                <Select
                  label="Unit"
                  value={adjustmentUnitUuid}
                  onChange={(event) => setAdjustmentUnitUuid(event.target.value)}
                  options={productUnitOptions}
                />
                <Select
                  label="Adjustment type"
                  value={adjustmentType}
                  onChange={(event) => setAdjustmentType(event.target.value as 'increase' | 'decrease')}
                  options={[{ value: 'increase', label: 'Increase' }, { value: 'decrease', label: 'Decrease' }]}
                />
                <Input label="Quantity" value={adjustmentQuantity} onChange={(event) => setAdjustmentQuantity(event.target.value)} inputMode="decimal" />
                <Input label="Reason" value={adjustmentReason} onChange={(event) => setAdjustmentReason(event.target.value)} />
                <Input label="Supporting note" value={adjustmentNote} onChange={(event) => setAdjustmentNote(event.target.value)} />
                <label className="inline-flex items-center gap-2 text-sm">
                  <input type="checkbox" checked={adjustmentApprovalRequested} onChange={(event) => setAdjustmentApprovalRequested(event.target.checked)} />
                  Approval request
                </label>
                <Button disabled={!adjustmentWarehouseUuid || !adjustmentProductUuid || !adjustmentUnitUuid || !adjustmentReason || createAdjustmentMutation.isPending} onClick={() => createAdjustmentMutation.mutate()}>
                  {createAdjustmentMutation.isPending ? 'Submitting...' : 'Submit adjustment'}
                </Button>
              </div>
            </article>

            <article className="rounded-2xl border border-border bg-surface p-4 shadow-sm">
              <h2 className="text-base font-semibold">Adjustment workflow</h2>
              <div className="mt-3 max-h-80 space-y-2 overflow-auto">
                {(adjustmentsQuery.data ?? []).map((adjustment) => (
                  <div key={adjustment.uuid} className="rounded-lg border border-border bg-background p-3 text-xs">
                    <p className="font-medium">{adjustment.adjustmentType} · {adjustment.name} ({adjustment.sku})</p>
                    <p>Qty: {formatQty(adjustment.quantity)} · Reason: {adjustment.reason}</p>
                    <p>Warehouse: {adjustment.warehouseName}</p>
                    <StatusBadge tone={toneForStatus(adjustment.approvalState)}>{adjustment.approvalState}</StatusBadge>
                    {adjustment.approvalState === 'pending' ? (
                      <div className="mt-2">
                        <Button size="sm" variant="secondary" onClick={() => approveAdjustmentMutation.mutate(adjustment.uuid)}>Approve</Button>
                      </div>
                    ) : null}
                  </div>
                ))}
              </div>
            </article>
          </section>
        ) : null}

        {tab === 'stock-transfers' ? (
          <section className="grid gap-4 xl:grid-cols-2">
            <article className="rounded-2xl border border-border bg-surface p-4 shadow-sm">
              <h2 className="text-base font-semibold">Stock transfers</h2>
              <div className="mt-3 grid gap-2">
                <Select
                  label="From warehouse"
                  value={transferFromWarehouseUuid}
                  onChange={(event) => setTransferFromWarehouseUuid(event.target.value)}
                  options={(warehousesQuery.data ?? []).map((warehouse) => ({ value: warehouse.uuid, label: `${warehouse.name} (${warehouse.code})` }))}
                />
                <Select
                  label="To warehouse"
                  value={transferToWarehouseUuid}
                  onChange={(event) => setTransferToWarehouseUuid(event.target.value)}
                  options={(warehousesQuery.data ?? []).map((warehouse) => ({ value: warehouse.uuid, label: `${warehouse.name} (${warehouse.code})` }))}
                />
                <Select
                  label="Product"
                  value={transferProductUuid}
                  onChange={(event) => setTransferProductUuid(event.target.value)}
                  options={(productsQuery.data ?? []).map((product) => ({ value: product.uuid, label: `${product.name} (${product.sku})` }))}
                />
                <Select
                  label="Unit"
                  value={transferUnitUuid}
                  onChange={(event) => setTransferUnitUuid(event.target.value)}
                  options={productUnitOptions}
                />
                <Input label="Quantity" value={transferQuantity} onChange={(event) => setTransferQuantity(event.target.value)} inputMode="decimal" />
                <Input label="Reason" value={transferReason} onChange={(event) => setTransferReason(event.target.value)} />
                <label className="inline-flex items-center gap-2 text-sm">
                  <input type="checkbox" checked={transferApprovalRequested} onChange={(event) => setTransferApprovalRequested(event.target.checked)} />
                  Approval request
                </label>
                <Button disabled={!transferFromWarehouseUuid || !transferToWarehouseUuid || !transferProductUuid || !transferUnitUuid || createTransferMutation.isPending} onClick={() => createTransferMutation.mutate()}>
                  {createTransferMutation.isPending ? 'Creating...' : 'Create transfer'}
                </Button>
              </div>
            </article>

            <article className="rounded-2xl border border-border bg-surface p-4 shadow-sm">
              <h2 className="text-base font-semibold">Transfer approval workflow</h2>
              <div className="mt-3 max-h-80 space-y-2 overflow-auto">
                {(transfersQuery.data ?? []).map((transfer) => (
                  <div key={transfer.uuid} className="rounded-lg border border-border bg-background p-3 text-xs">
                    <p className="font-medium">{transfer.fromWarehouseName} → {transfer.toWarehouseName}</p>
                    <p>Status: {transfer.status} · Approval: {transfer.approvalState}</p>
                    <p>Reason: {transfer.reason ?? 'n/a'}</p>
                    <div className="mt-2 space-y-1">
                      {transfer.items.map((item) => (
                        <p key={item.uuid}>- {item.name} ({item.sku}) · Qty: {formatQty(item.quantity)}</p>
                      ))}
                    </div>
                    <div className="mt-2 flex gap-2">
                      {transfer.status === 'draft' ? <Button size="sm" variant="secondary" onClick={() => submitTransferMutation.mutate(transfer.uuid)}>Submit</Button> : null}
                      {(transfer.status === 'submitted' || transfer.status === 'draft') ? <Button size="sm" variant="secondary" onClick={() => approveTransferMutation.mutate(transfer.uuid)}>Approve</Button> : null}
                    </div>
                  </div>
                ))}
              </div>
            </article>
          </section>
        ) : null}
      </div>
    </section>
  );
}

function ListPanel(props: { title: string; rows: Array<{ id: string; primary: string; secondary: string; badge?: string }> }) {
  return (
    <section className="rounded-2xl border border-border bg-surface p-4 shadow-sm">
      <h2 className="text-base font-semibold">{props.title}</h2>
      <div className="mt-3 max-h-[36rem] space-y-2 overflow-auto">
        {props.rows.map((row) => (
          <div key={row.id} className="rounded-lg border border-border bg-background px-3 py-2 text-sm">
            <div className="flex items-center justify-between gap-2">
              <p className="font-medium">{row.primary}</p>
              {row.badge ? <StatusBadge tone={toneForStatus(row.badge)}>{row.badge}</StatusBadge> : null}
            </div>
            <p className="text-xs text-muted">{row.secondary}</p>
          </div>
        ))}
        {props.rows.length === 0 ? <p className="text-sm text-muted">No records.</p> : null}
      </div>
    </section>
  );
}
