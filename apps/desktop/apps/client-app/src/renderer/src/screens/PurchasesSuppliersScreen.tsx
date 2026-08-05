import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Button, Input, Select, StatusBadge } from '@marketlens/ui';
import { useMemo, useState } from 'react';
import { useClientUiStore } from '../state/client-ui-store';
import {
  createPurchaseEntry,
  createPurchaseReturn,
  createSupplierProfile,
  fetchPurchaseDetails,
  fetchSupplierStatement,
  listProductsForPurchase,
  listPurchasePayments,
  listPurchaseReturns,
  listPurchases,
  listSupplierBalances,
  listUnitsForPurchase,
  listWarehousesForPurchase,
  receivePurchaseStock,
  recordPurchasePayment,
  searchSuppliers,
} from '../services/purchases-suppliers-service';

type PurchaseSupplierTab =
  | 'suppliers'
  | 'purchase-entry'
  | 'draft-purchases'
  | 'completed-purchases'
  | 'stock-receiving'
  | 'supplier-balances'
  | 'supplier-statements'
  | 'purchase-payments'
  | 'purchase-returns';

function formatMoney(value: string | number): string {
  const numeric = Number(value ?? 0);
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number.isFinite(numeric) ? numeric : 0);
}

function toneForStatus(value: string): 'success' | 'warning' | 'danger' | 'information' | 'neutral' {
  const normalized = value.toLowerCase();
  if (normalized.includes('paid') || normalized.includes('received') || normalized.includes('completed')) return 'success';
  if (normalized.includes('draft') || normalized.includes('partial')) return 'warning';
  if (normalized.includes('return') || normalized.includes('cancel')) return 'danger';
  return 'information';
}

function hasAnyPermission(granted: string[], required: string[]): boolean {
  return required.some((permission) => granted.includes(permission));
}

export function PurchasesSuppliersScreen(props: { initialTab?: 'procurement' | 'suppliers' }) {
  const queryClient = useQueryClient();
  const deviceUuid = useClientUiStore((state) => state.deviceUuid);
  const connectionState = useClientUiStore((state) => state.connectionState);
  const currentUserUuid = useClientUiStore((state) => state.currentUser?.uuid);
  const permissions = useClientUiStore((state) => state.permissions);
  const notify = useClientUiStore((state) => state.notify);

  const canCreateSupplier = hasAnyPermission(permissions, ['suppliers.manage']);
  const canCreatePurchase = hasAnyPermission(permissions, ['purchases.create']);
  const canReceivePurchase = hasAnyPermission(permissions, ['purchases.receive']);

  const [tab, setTab] = useState<PurchaseSupplierTab>(props.initialTab === 'suppliers' ? 'suppliers' : 'purchase-entry');
  const [supplierSearch, setSupplierSearch] = useState('');
  const [purchaseSearch, setPurchaseSearch] = useState('');

  const [selectedSupplierUuid, setSelectedSupplierUuid] = useState('');
  const [selectedPurchaseUuid, setSelectedPurchaseUuid] = useState('');

  const [supplierCode, setSupplierCode] = useState('');
  const [supplierName, setSupplierName] = useState('');
  const [supplierContactName, setSupplierContactName] = useState('');
  const [supplierPhone, setSupplierPhone] = useState('');
  const [supplierEmail, setSupplierEmail] = useState('');
  const [supplierAddress, setSupplierAddress] = useState('');
  const [supplierTaxNumber, setSupplierTaxNumber] = useState('');
  const [supplierTerms, setSupplierTerms] = useState('');

  const [invoiceNumber, setInvoiceNumber] = useState('');
  const [invoiceDate, setInvoiceDate] = useState('');
  const [transportCost, setTransportCost] = useState('0');
  const [loadingCost, setLoadingCost] = useState('0');
  const [otherLandedCost, setOtherLandedCost] = useState('0');
  const [tax, setTax] = useState('0');
  const [discount, setDiscount] = useState('0');
  const [purchaseNotes, setPurchaseNotes] = useState('');
  const [creditPurchase, setCreditPurchase] = useState(true);
  const [initialPayment, setInitialPayment] = useState('0');

  const [itemProductUuid, setItemProductUuid] = useState('');
  const [itemUnitUuid, setItemUnitUuid] = useState('');
  const [itemQuantity, setItemQuantity] = useState('1');
  const [itemCost, setItemCost] = useState('0');
  const [itemBatchNumber, setItemBatchNumber] = useState('');
  const [itemExpiryDate, setItemExpiryDate] = useState('');
  const [itemSerialNumbers, setItemSerialNumbers] = useState('');
  const [draftItems, setDraftItems] = useState<Array<{
    productUuid: string;
    purchaseUnitUuid: string;
    quantity: string;
    unitCost: string;
    batchNumber?: string;
    expiryDate?: string;
    serialNumbers?: string[];
    baseUnitMultiplier?: string;
  }>>([]);

  const [receivingWarehouseUuid, setReceivingWarehouseUuid] = useState('');
  const [paymentAmount, setPaymentAmount] = useState('0');
  const [paymentReference, setPaymentReference] = useState('');
  const [paymentMethod, setPaymentMethod] = useState('bank_transfer');

  const [returnWarehouseUuid, setReturnWarehouseUuid] = useState('');
  const [returnProductUuid, setReturnProductUuid] = useState('');
  const [returnUnitUuid, setReturnUnitUuid] = useState('');
  const [returnQuantity, setReturnQuantity] = useState('1');
  const [returnReason, setReturnReason] = useState('');
  const [returnNote, setReturnNote] = useState('');

  const canQuery = Boolean(deviceUuid && connectionState === 'connected');

  const sessionLoader = async () => {
    const session = await window.marketlensClient.session.load();
    if (!session || !deviceUuid) throw new Error('Session not available.');
    return session;
  };

  const suppliersQuery = useQuery({
    queryKey: ['purchases-suppliers', supplierSearch],
    queryFn: async () => {
      const session = await sessionLoader();
      return searchSuppliers({ shopBaseUrl: session.shopBaseUrl, accessToken: session.token, deviceUuid: deviceUuid!, search: supplierSearch });
    },
    enabled: canQuery,
    staleTime: 10_000,
    refetchOnWindowFocus: false,
  });

  const productsQuery = useQuery({
    queryKey: ['purchases-products'],
    queryFn: async () => {
      const session = await sessionLoader();
      return listProductsForPurchase({ shopBaseUrl: session.shopBaseUrl, accessToken: session.token, deviceUuid: deviceUuid! });
    },
    enabled: canQuery,
    staleTime: 20_000,
    refetchOnWindowFocus: false,
  });

  const unitsQuery = useQuery({
    queryKey: ['purchases-units'],
    queryFn: async () => {
      const session = await sessionLoader();
      return listUnitsForPurchase({ shopBaseUrl: session.shopBaseUrl, accessToken: session.token, deviceUuid: deviceUuid! });
    },
    enabled: canQuery,
    staleTime: 20_000,
    refetchOnWindowFocus: false,
  });

  const warehousesQuery = useQuery({
    queryKey: ['purchases-warehouses'],
    queryFn: async () => {
      const session = await sessionLoader();
      return listWarehousesForPurchase({ shopBaseUrl: session.shopBaseUrl, accessToken: session.token, deviceUuid: deviceUuid! });
    },
    enabled: canQuery,
    staleTime: 20_000,
    refetchOnWindowFocus: false,
  });

  const draftPurchasesQuery = useQuery({
    queryKey: ['purchases-draft', purchaseSearch],
    queryFn: async () => {
      const session = await sessionLoader();
      return listPurchases({ shopBaseUrl: session.shopBaseUrl, accessToken: session.token, deviceUuid: deviceUuid!, status: 'draft', search: purchaseSearch });
    },
    enabled: canQuery,
    staleTime: 5000,
    refetchOnWindowFocus: false,
  });

  const completedPurchasesQuery = useQuery({
    queryKey: ['purchases-completed', purchaseSearch],
    queryFn: async () => {
      const session = await sessionLoader();
      return listPurchases({ shopBaseUrl: session.shopBaseUrl, accessToken: session.token, deviceUuid: deviceUuid!, status: 'completed', search: purchaseSearch });
    },
    enabled: canQuery,
    staleTime: 5000,
    refetchOnWindowFocus: false,
  });

  const purchaseDetailsQuery = useQuery({
    queryKey: ['purchase-details', selectedPurchaseUuid],
    queryFn: async () => {
      const session = await sessionLoader();
      return fetchPurchaseDetails({ shopBaseUrl: session.shopBaseUrl, accessToken: session.token, deviceUuid: deviceUuid!, purchaseUuid: selectedPurchaseUuid });
    },
    enabled: canQuery && Boolean(selectedPurchaseUuid),
    staleTime: 3000,
    refetchOnWindowFocus: false,
  });

  const supplierBalancesQuery = useQuery({
    queryKey: ['supplier-balances', supplierSearch],
    queryFn: async () => {
      const session = await sessionLoader();
      return listSupplierBalances({ shopBaseUrl: session.shopBaseUrl, accessToken: session.token, deviceUuid: deviceUuid!, search: supplierSearch });
    },
    enabled: canQuery,
    staleTime: 5000,
    refetchOnWindowFocus: false,
  });

  const supplierStatementQuery = useQuery({
    queryKey: ['supplier-statement', selectedSupplierUuid],
    queryFn: async () => {
      const session = await sessionLoader();
      return fetchSupplierStatement({ shopBaseUrl: session.shopBaseUrl, accessToken: session.token, deviceUuid: deviceUuid!, supplierUuid: selectedSupplierUuid });
    },
    enabled: canQuery && Boolean(selectedSupplierUuid),
    staleTime: 5000,
    refetchOnWindowFocus: false,
  });

  const purchasePaymentsQuery = useQuery({
    queryKey: ['purchase-payments', selectedSupplierUuid],
    queryFn: async () => {
      const session = await sessionLoader();
      return listPurchasePayments({ shopBaseUrl: session.shopBaseUrl, accessToken: session.token, deviceUuid: deviceUuid!, supplierUuid: selectedSupplierUuid || undefined });
    },
    enabled: canQuery,
    staleTime: 5000,
    refetchOnWindowFocus: false,
  });

  const purchaseReturnsQuery = useQuery({
    queryKey: ['purchase-returns', selectedSupplierUuid],
    queryFn: async () => {
      const session = await sessionLoader();
      return listPurchaseReturns({ shopBaseUrl: session.shopBaseUrl, accessToken: session.token, deviceUuid: deviceUuid!, supplierUuid: selectedSupplierUuid || undefined });
    },
    enabled: canQuery,
    staleTime: 5000,
    refetchOnWindowFocus: false,
  });

  const createSupplierMutation = useMutation({
    mutationFn: async () => {
      const session = await sessionLoader();
      return createSupplierProfile({
        shopBaseUrl: session.shopBaseUrl,
        accessToken: session.token,
        deviceUuid: deviceUuid!,
        code: supplierCode,
        name: supplierName,
        contactName: supplierContactName || undefined,
        contactPhone: supplierPhone || undefined,
        contactEmail: supplierEmail || undefined,
        address: supplierAddress || undefined,
        taxNumber: supplierTaxNumber || undefined,
        paymentTerms: supplierTerms || undefined,
        preferred: false,
      });
    },
    onSuccess: async (supplier) => {
      setSelectedSupplierUuid(supplier.uuid);
      setSupplierCode('');
      setSupplierName('');
      setSupplierContactName('');
      setSupplierPhone('');
      setSupplierEmail('');
      setSupplierAddress('');
      setSupplierTaxNumber('');
      setSupplierTerms('');
      notify({ tone: 'success', title: 'Supplier created', message: 'Shop App created supplier profile.' });
      await queryClient.invalidateQueries({ queryKey: ['purchases-suppliers'] });
      await queryClient.invalidateQueries({ queryKey: ['supplier-balances'] });
    },
    onError: (error) => {
      notify({ tone: 'danger', title: 'Supplier failed', message: error instanceof Error ? error.message : 'Unable to create supplier.' });
    },
  });

  const createPurchaseMutation = useMutation({
    mutationFn: async () => {
      const session = await sessionLoader();
      const purchase = await createPurchaseEntry({
        shopBaseUrl: session.shopBaseUrl,
        accessToken: session.token,
        deviceUuid: deviceUuid!,
        supplierUuid: selectedSupplierUuid,
        invoiceNumber: invoiceNumber || undefined,
        invoiceDate: invoiceDate || undefined,
        currency: 'NGN',
        transportCost,
        loadingCost,
        otherLandedCost,
        tax,
        discount,
        notes: purchaseNotes || undefined,
        items: draftItems,
      });

      if (!creditPurchase && Number(initialPayment) > 0 && typeof purchase?.uuid === 'string') {
        await recordPurchasePayment({
          shopBaseUrl: session.shopBaseUrl,
          accessToken: session.token,
          deviceUuid: deviceUuid!,
          purchaseUuid: purchase.uuid,
          amount: initialPayment,
          reference: 'Initial purchase payment',
          paymentMethod: 'cash',
        });
      }

      return purchase;
    },
    onSuccess: async (purchase) => {
      if (typeof purchase?.uuid === 'string') {
        setSelectedPurchaseUuid(purchase.uuid);
      }
      setDraftItems([]);
      setInvoiceNumber('');
      setInvoiceDate('');
      setTransportCost('0');
      setLoadingCost('0');
      setOtherLandedCost('0');
      setTax('0');
      setDiscount('0');
      setPurchaseNotes('');
      setInitialPayment('0');
      notify({ tone: 'success', title: 'Purchase saved', message: 'Shop App created draft purchase entry.' });
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['purchases-draft'] }),
        queryClient.invalidateQueries({ queryKey: ['purchases-completed'] }),
        queryClient.invalidateQueries({ queryKey: ['supplier-balances'] }),
      ]);
    },
    onError: (error) => {
      notify({ tone: 'danger', title: 'Purchase failed', message: error instanceof Error ? error.message : 'Unable to create purchase.' });
    },
  });

  const receiveMutation = useMutation({
    mutationFn: async () => {
      const session = await sessionLoader();
      if (!selectedPurchaseUuid) throw new Error('Select a purchase.');
      const details = await fetchPurchaseDetails({ shopBaseUrl: session.shopBaseUrl, accessToken: session.token, deviceUuid: deviceUuid!, purchaseUuid: selectedPurchaseUuid });
      return receivePurchaseStock({
        shopBaseUrl: session.shopBaseUrl,
        accessToken: session.token,
        deviceUuid: deviceUuid!,
        purchaseUuid: selectedPurchaseUuid,
        warehouseUuid: receivingWarehouseUuid,
        receivedItems: details.items.map((item) => ({
          productUuid: item.productUuid,
          quantity: String(Math.max(0, Number(item.quantity) - Number(item.receivedQuantity))),
          batchNumber: item.batchNumber ?? undefined,
          expiryDate: item.expiryDate ?? undefined,
          serialNumbers: item.serialNumbersJson ? JSON.parse(item.serialNumbersJson) as string[] : undefined,
        })).filter((item) => Number(item.quantity) > 0),
      });
    },
    onSuccess: async () => {
      notify({ tone: 'success', title: 'Stock received', message: 'Shop App recorded stock receiving movement.' });
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['purchases-completed'] }),
        queryClient.invalidateQueries({ queryKey: ['purchases-draft'] }),
        queryClient.invalidateQueries({ queryKey: ['purchase-details', selectedPurchaseUuid] }),
      ]);
    },
    onError: (error) => {
      notify({ tone: 'danger', title: 'Receiving failed', message: error instanceof Error ? error.message : 'Unable to receive stock.' });
    },
  });

  const paymentMutation = useMutation({
    mutationFn: async () => {
      const session = await sessionLoader();
      if (!selectedPurchaseUuid) throw new Error('Select a purchase.');
      return recordPurchasePayment({
        shopBaseUrl: session.shopBaseUrl,
        accessToken: session.token,
        deviceUuid: deviceUuid!,
        purchaseUuid: selectedPurchaseUuid,
        amount: paymentAmount,
        reference: paymentReference || undefined,
        paymentMethod,
      });
    },
    onSuccess: async () => {
      setPaymentAmount('0');
      setPaymentReference('');
      notify({ tone: 'success', title: 'Payment recorded', message: 'Shop App updated purchase payment and supplier ledger.' });
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['purchase-payments'] }),
        queryClient.invalidateQueries({ queryKey: ['purchase-details', selectedPurchaseUuid] }),
        queryClient.invalidateQueries({ queryKey: ['supplier-balances'] }),
      ]);
    },
    onError: (error) => {
      notify({ tone: 'danger', title: 'Payment failed', message: error instanceof Error ? error.message : 'Unable to record payment.' });
    },
  });

  const returnMutation = useMutation({
    mutationFn: async () => {
      const session = await sessionLoader();
      if (!selectedPurchaseUuid) throw new Error('Select a purchase.');
      return createPurchaseReturn({
        shopBaseUrl: session.shopBaseUrl,
        accessToken: session.token,
        deviceUuid: deviceUuid!,
        purchaseUuid: selectedPurchaseUuid,
        warehouseUuid: returnWarehouseUuid,
        reason: returnReason || undefined,
        note: returnNote || undefined,
        items: [{ productUuid: returnProductUuid, quantity: returnQuantity, unitUuid: returnUnitUuid || undefined }],
      });
    },
    onSuccess: async () => {
      setReturnQuantity('1');
      setReturnReason('');
      setReturnNote('');
      notify({ tone: 'success', title: 'Return posted', message: 'Shop App recorded purchase return and stock movement.' });
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['purchase-returns'] }),
        queryClient.invalidateQueries({ queryKey: ['purchase-details', selectedPurchaseUuid] }),
        queryClient.invalidateQueries({ queryKey: ['supplier-balances'] }),
      ]);
    },
    onError: (error) => {
      notify({ tone: 'danger', title: 'Return failed', message: error instanceof Error ? error.message : 'Unable to post purchase return.' });
    },
  });

  const tabs: Array<{ id: PurchaseSupplierTab; label: string }> = [
    { id: 'suppliers', label: 'Supplier search and create' },
    { id: 'purchase-entry', label: 'Purchase entry' },
    { id: 'draft-purchases', label: 'Draft purchases' },
    { id: 'completed-purchases', label: 'Completed purchases' },
    { id: 'stock-receiving', label: 'Stock receiving' },
    { id: 'supplier-balances', label: 'Supplier balances' },
    { id: 'supplier-statements', label: 'Supplier statements' },
    { id: 'purchase-payments', label: 'Purchase payments' },
    { id: 'purchase-returns', label: 'Purchase returns' },
  ];

  const purchaseRows = useMemo(() => [...(draftPurchasesQuery.data ?? []), ...(completedPurchasesQuery.data ?? [])], [draftPurchasesQuery.data, completedPurchasesQuery.data]);

  return (
    <section className="min-h-screen bg-background p-6 text-foreground">
      <div className="mx-auto max-w-7xl space-y-5">
        <header className="rounded-2xl border border-border bg-surface p-5 shadow-sm">
          <h1 className="text-2xl font-semibold">Purchases and suppliers</h1>
          <p className="mt-1 text-sm text-muted">Client App submits procurement requests. Shop App remains authoritative for stock movements and supplier ledgers.</p>
        </header>

        <div className="rounded-2xl border border-border bg-surface p-4 shadow-sm">
          <div className="flex flex-wrap gap-2">
            {tabs.map((entry) => (
              <Button key={entry.id} variant={tab === entry.id ? 'secondary' : 'ghost'} onClick={() => setTab(entry.id)}>
                {entry.label}
              </Button>
            ))}
          </div>
          <div className="mt-3 grid gap-3 md:grid-cols-2">
            <Input label="Supplier search" value={supplierSearch} onChange={(event) => setSupplierSearch(event.target.value)} placeholder="Name, phone, or code" />
            <Input label="Purchase search" value={purchaseSearch} onChange={(event) => setPurchaseSearch(event.target.value)} placeholder="Invoice number or supplier" />
          </div>
        </div>

        {tab === 'suppliers' ? (
          <section className="grid gap-4 xl:grid-cols-2">
            <article className="rounded-2xl border border-border bg-surface p-4 shadow-sm">
              <h2 className="text-base font-semibold">Supplier search</h2>
              <div className="mt-3 max-h-[28rem] space-y-2 overflow-auto">
                {(suppliersQuery.data ?? []).map((supplier) => (
                  <button key={supplier.uuid} type="button" className={`block w-full rounded-lg border border-border px-3 py-2 text-left ${selectedSupplierUuid === supplier.uuid ? 'bg-primary/10' : 'hover:bg-surface-muted'}`} onClick={() => setSelectedSupplierUuid(supplier.uuid)}>
                    <p className="text-sm font-medium">{supplier.name} ({supplier.code})</p>
                    <p className="text-xs text-muted">{supplier.contactPhone ?? 'No phone'} · {supplier.contactEmail ?? 'No email'}</p>
                  </button>
                ))}
              </div>
            </article>

            <article className="rounded-2xl border border-border bg-surface p-4 shadow-sm">
              <h2 className="text-base font-semibold">Create supplier</h2>
              <div className="mt-3 grid gap-2">
                <Input label="Supplier code" value={supplierCode} onChange={(event) => setSupplierCode(event.target.value)} />
                <Input label="Supplier name" value={supplierName} onChange={(event) => setSupplierName(event.target.value)} />
                <Input label="Contact name" value={supplierContactName} onChange={(event) => setSupplierContactName(event.target.value)} />
                <Input label="Phone" value={supplierPhone} onChange={(event) => setSupplierPhone(event.target.value)} />
                <Input label="Email" value={supplierEmail} onChange={(event) => setSupplierEmail(event.target.value)} />
                <Input label="Address" value={supplierAddress} onChange={(event) => setSupplierAddress(event.target.value)} />
                <Input label="Tax number" value={supplierTaxNumber} onChange={(event) => setSupplierTaxNumber(event.target.value)} />
                <Input label="Payment terms" value={supplierTerms} onChange={(event) => setSupplierTerms(event.target.value)} />
                <Button disabled={!canCreateSupplier || !supplierCode || !supplierName || createSupplierMutation.isPending} onClick={() => createSupplierMutation.mutate()}>
                  {createSupplierMutation.isPending ? 'Saving...' : 'Create supplier'}
                </Button>
              </div>
            </article>
          </section>
        ) : null}

        {tab === 'purchase-entry' ? (
          <section className="grid gap-4 xl:grid-cols-2">
            <article className="rounded-2xl border border-border bg-surface p-4 shadow-sm">
              <h2 className="text-base font-semibold">Purchase header</h2>
              <div className="mt-3 grid gap-2">
                <Select
                  label="Supplier"
                  value={selectedSupplierUuid}
                  onChange={(event) => setSelectedSupplierUuid(event.target.value)}
                  options={(suppliersQuery.data ?? []).map((supplier) => ({ value: supplier.uuid, label: `${supplier.name} (${supplier.code})` }))}
                />
                <Input label="Invoice number" value={invoiceNumber} onChange={(event) => setInvoiceNumber(event.target.value)} />
                <Input label="Invoice date" type="date" value={invoiceDate} onChange={(event) => setInvoiceDate(event.target.value)} />
                <Input label="Transport cost" value={transportCost} onChange={(event) => setTransportCost(event.target.value)} inputMode="decimal" />
                <Input label="Loading cost" value={loadingCost} onChange={(event) => setLoadingCost(event.target.value)} inputMode="decimal" />
                <Input label="Other landed cost" value={otherLandedCost} onChange={(event) => setOtherLandedCost(event.target.value)} inputMode="decimal" />
                <Input label="Tax" value={tax} onChange={(event) => setTax(event.target.value)} inputMode="decimal" />
                <Input label="Discount" value={discount} onChange={(event) => setDiscount(event.target.value)} inputMode="decimal" />
                <Input label="Notes" value={purchaseNotes} onChange={(event) => setPurchaseNotes(event.target.value)} />

                <label className="inline-flex items-center gap-2 text-sm">
                  <input type="checkbox" checked={creditPurchase} onChange={(event) => setCreditPurchase(event.target.checked)} />
                  Credit purchase
                </label>
                {!creditPurchase ? (
                  <Input label="Initial partial payment" value={initialPayment} onChange={(event) => setInitialPayment(event.target.value)} inputMode="decimal" />
                ) : null}
              </div>
            </article>

            <article className="rounded-2xl border border-border bg-surface p-4 shadow-sm">
              <h2 className="text-base font-semibold">Purchase items</h2>
              <div className="mt-3 grid gap-2">
                <Select
                  label="Product"
                  value={itemProductUuid}
                  onChange={(event) => setItemProductUuid(event.target.value)}
                  options={(productsQuery.data ?? []).map((product) => ({ value: product.uuid, label: `${product.name} (${product.sku})` }))}
                />
                <Select
                  label="Purchase unit"
                  value={itemUnitUuid}
                  onChange={(event) => setItemUnitUuid(event.target.value)}
                  options={(unitsQuery.data ?? []).map((unit) => ({ value: unit.uuid, label: `${unit.name} (${unit.code})` }))}
                />
                <Input label="Quantity" value={itemQuantity} onChange={(event) => setItemQuantity(event.target.value)} inputMode="decimal" />
                <Input label="Unit cost" value={itemCost} onChange={(event) => setItemCost(event.target.value)} inputMode="decimal" />
                <Input label="Batch number" value={itemBatchNumber} onChange={(event) => setItemBatchNumber(event.target.value)} />
                <Input label="Expiry date" type="date" value={itemExpiryDate} onChange={(event) => setItemExpiryDate(event.target.value)} />
                <Input label="Serial numbers" value={itemSerialNumbers} onChange={(event) => setItemSerialNumbers(event.target.value)} placeholder="Comma-separated serial numbers" />

                <Button
                  variant="secondary"
                  disabled={!itemProductUuid || !itemUnitUuid || Number(itemQuantity) <= 0}
                  onClick={() => {
                    const serials = itemSerialNumbers.split(',').map((entry) => entry.trim()).filter(Boolean);
                    setDraftItems((previous) => [
                      ...previous,
                      {
                        productUuid: itemProductUuid,
                        purchaseUnitUuid: itemUnitUuid,
                        quantity: itemQuantity,
                        unitCost: itemCost,
                        batchNumber: itemBatchNumber || undefined,
                        expiryDate: itemExpiryDate || undefined,
                        serialNumbers: serials.length > 0 ? serials : undefined,
                        baseUnitMultiplier: '1',
                      },
                    ]);
                    setItemQuantity('1');
                    setItemCost('0');
                    setItemBatchNumber('');
                    setItemExpiryDate('');
                    setItemSerialNumbers('');
                  }}
                >
                  Add item
                </Button>
              </div>

              <div className="mt-3 rounded-xl border border-border bg-background p-3">
                <p className="text-sm font-semibold">Draft line items</p>
                <div className="mt-2 max-h-44 space-y-2 overflow-auto">
                  {draftItems.map((item, index) => (
                    <div key={`${item.productUuid}-${item.purchaseUnitUuid}-${index}`} className="rounded-lg border border-border px-2 py-2 text-xs">
                      <p>{item.productUuid}</p>
                      <p>Unit: {item.purchaseUnitUuid} · Qty: {item.quantity} · Cost: {formatMoney(item.unitCost)}</p>
                      <p>Batch: {item.batchNumber ?? 'n/a'} · Expiry: {item.expiryDate ?? 'n/a'}</p>
                    </div>
                  ))}
                  {draftItems.length === 0 ? <p className="text-xs text-muted">No draft items added.</p> : null}
                </div>
              </div>

              <Button className="mt-3" disabled={!canCreatePurchase || !selectedSupplierUuid || draftItems.length === 0 || createPurchaseMutation.isPending} onClick={() => createPurchaseMutation.mutate()}>
                {createPurchaseMutation.isPending ? 'Submitting...' : 'Create purchase entry'}
              </Button>
            </article>
          </section>
        ) : null}

        {tab === 'draft-purchases' ? (
          <RecordPanel
            title="Draft purchases"
            rows={(draftPurchasesQuery.data ?? []).map((purchase) => ({
              id: purchase.uuid,
              primary: `${purchase.invoiceNumber ?? purchase.uuid} · ${purchase.supplierName}`,
              secondary: `Total: ${formatMoney(purchase.totalAmount)} · Paid: ${formatMoney(purchase.amountPaid)} · Balance: ${formatMoney(purchase.balanceAmount)}`,
              badge: purchase.status,
              onSelect: () => setSelectedPurchaseUuid(purchase.uuid),
            }))}
          />
        ) : null}

        {tab === 'completed-purchases' ? (
          <RecordPanel
            title="Completed purchases"
            rows={(completedPurchasesQuery.data ?? []).map((purchase) => ({
              id: purchase.uuid,
              primary: `${purchase.invoiceNumber ?? purchase.uuid} · ${purchase.supplierName}`,
              secondary: `Total: ${formatMoney(purchase.totalAmount)} · Paid: ${formatMoney(purchase.amountPaid)} · Balance: ${formatMoney(purchase.balanceAmount)}`,
              badge: purchase.status,
              onSelect: () => setSelectedPurchaseUuid(purchase.uuid),
            }))}
          />
        ) : null}

        {tab === 'stock-receiving' ? (
          <section className="grid gap-4 xl:grid-cols-2">
            <article className="rounded-2xl border border-border bg-surface p-4 shadow-sm">
              <h2 className="text-base font-semibold">Select purchase and warehouse</h2>
              <div className="mt-3 grid gap-2">
                <Select
                  label="Purchase"
                  value={selectedPurchaseUuid}
                  onChange={(event) => setSelectedPurchaseUuid(event.target.value)}
                  options={purchaseRows.map((purchase) => ({ value: purchase.uuid, label: `${purchase.invoiceNumber ?? purchase.uuid} · ${purchase.supplierName}` }))}
                />
                <Select
                  label="Warehouse"
                  value={receivingWarehouseUuid}
                  onChange={(event) => setReceivingWarehouseUuid(event.target.value)}
                  options={(warehousesQuery.data ?? []).map((warehouse) => ({ value: warehouse.uuid, label: `${warehouse.name} (${warehouse.code})` }))}
                />
                <Button disabled={!canReceivePurchase || !selectedPurchaseUuid || !receivingWarehouseUuid || receiveMutation.isPending} onClick={() => receiveMutation.mutate()}>
                  {receiveMutation.isPending ? 'Receiving...' : 'Receive stock'}
                </Button>
              </div>
            </article>

            <article className="rounded-2xl border border-border bg-surface p-4 shadow-sm">
              <h2 className="text-base font-semibold">Selected purchase items</h2>
              <div className="mt-3 max-h-80 space-y-2 overflow-auto">
                {(purchaseDetailsQuery.data?.items ?? []).map((item) => (
                  <div key={item.uuid} className="rounded-lg border border-border bg-background p-3 text-xs">
                    <p>Product: {item.productUuid}</p>
                    <p>Quantity: {item.quantity} · Received: {item.receivedQuantity}</p>
                    <p>Batch: {item.batchNumber ?? 'n/a'} · Expiry: {item.expiryDate ?? 'n/a'}</p>
                  </div>
                ))}
              </div>
            </article>
          </section>
        ) : null}

        {tab === 'supplier-balances' ? (
          <RecordPanel
            title="Supplier balances"
            rows={(supplierBalancesQuery.data ?? []).map((balance) => ({
              id: balance.supplierUuid,
              primary: `${balance.name} (${balance.code})`,
              secondary: `Outstanding balance: ${formatMoney(balance.currentBalance)} · ${balance.contactPhone ?? 'No phone'}`,
              onSelect: () => setSelectedSupplierUuid(balance.supplierUuid),
            }))}
          />
        ) : null}

        {tab === 'supplier-statements' ? (
          <section className="rounded-2xl border border-border bg-surface p-4 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <h2 className="text-base font-semibold">Supplier statement</h2>
              <p className="text-sm text-muted">Current balance: {formatMoney(supplierStatementQuery.data?.account.currentBalance ?? '0')}</p>
            </div>
            <div className="mt-3 max-h-[32rem] space-y-2 overflow-auto">
              {(supplierStatementQuery.data?.transactions ?? []).map((entry) => (
                <div key={entry.uuid} className="rounded-lg border border-border bg-background p-3 text-xs">
                  <div className="flex items-center justify-between gap-2">
                    <p className="font-medium">{entry.entryType.toUpperCase()} · {entry.reference}</p>
                    <StatusBadge tone={toneForStatus(entry.entryType)}>{entry.entryType}</StatusBadge>
                  </div>
                  <p>Debit: {formatMoney(entry.debit)} · Credit: {formatMoney(entry.credit)}</p>
                  <p>Running balance: {formatMoney(entry.runningBalance)}</p>
                  <p>{new Date(entry.occurredAt).toLocaleString()}</p>
                </div>
              ))}
            </div>
          </section>
        ) : null}

        {tab === 'purchase-payments' ? (
          <section className="grid gap-4 xl:grid-cols-2">
            <article className="rounded-2xl border border-border bg-surface p-4 shadow-sm">
              <h2 className="text-base font-semibold">Record purchase payment</h2>
              <div className="mt-3 grid gap-2">
                <Select
                  label="Purchase"
                  value={selectedPurchaseUuid}
                  onChange={(event) => setSelectedPurchaseUuid(event.target.value)}
                  options={purchaseRows.map((purchase) => ({ value: purchase.uuid, label: `${purchase.invoiceNumber ?? purchase.uuid} · ${purchase.supplierName}` }))}
                />
                <Input label="Amount" value={paymentAmount} onChange={(event) => setPaymentAmount(event.target.value)} inputMode="decimal" />
                <Input label="Reference" value={paymentReference} onChange={(event) => setPaymentReference(event.target.value)} />
                <Select
                  label="Payment method"
                  value={paymentMethod}
                  onChange={(event) => setPaymentMethod(event.target.value)}
                  options={[
                    { value: 'cash', label: 'Cash' },
                    { value: 'bank_transfer', label: 'Bank transfer' },
                    { value: 'pos', label: 'POS terminal' },
                  ]}
                />
                <Button disabled={!selectedPurchaseUuid || Number(paymentAmount) <= 0 || paymentMutation.isPending} onClick={() => paymentMutation.mutate()}>
                  {paymentMutation.isPending ? 'Saving...' : 'Record payment'}
                </Button>
              </div>
            </article>

            <RecordPanel
              title="Purchase payments"
              rows={(purchasePaymentsQuery.data ?? []).map((payment) => ({
                id: payment.uuid,
                primary: `${payment.reference ?? payment.purchaseUuid}`,
                secondary: `Amount: ${formatMoney(payment.amount)} · Method: ${payment.paymentMethod ?? 'n/a'} · ${new Date(payment.createdAt).toLocaleString()}`,
              }))}
            />
          </section>
        ) : null}

        {tab === 'purchase-returns' ? (
          <section className="grid gap-4 xl:grid-cols-2">
            <article className="rounded-2xl border border-border bg-surface p-4 shadow-sm">
              <h2 className="text-base font-semibold">Create purchase return</h2>
              <div className="mt-3 grid gap-2">
                <Select
                  label="Purchase"
                  value={selectedPurchaseUuid}
                  onChange={(event) => setSelectedPurchaseUuid(event.target.value)}
                  options={purchaseRows.map((purchase) => ({ value: purchase.uuid, label: `${purchase.invoiceNumber ?? purchase.uuid} · ${purchase.supplierName}` }))}
                />
                <Select
                  label="Warehouse"
                  value={returnWarehouseUuid}
                  onChange={(event) => setReturnWarehouseUuid(event.target.value)}
                  options={(warehousesQuery.data ?? []).map((warehouse) => ({ value: warehouse.uuid, label: `${warehouse.name} (${warehouse.code})` }))}
                />
                <Select
                  label="Product"
                  value={returnProductUuid}
                  onChange={(event) => setReturnProductUuid(event.target.value)}
                  options={(purchaseDetailsQuery.data?.items ?? []).map((item) => ({ value: item.productUuid, label: item.productUuid }))}
                />
                <Select
                  label="Unit"
                  value={returnUnitUuid}
                  onChange={(event) => setReturnUnitUuid(event.target.value)}
                  options={(unitsQuery.data ?? []).map((unit) => ({ value: unit.uuid, label: `${unit.name} (${unit.code})` }))}
                />
                <Input label="Return quantity" value={returnQuantity} onChange={(event) => setReturnQuantity(event.target.value)} inputMode="decimal" />
                <Input label="Return reason" value={returnReason} onChange={(event) => setReturnReason(event.target.value)} />
                <Input label="Supporting note" value={returnNote} onChange={(event) => setReturnNote(event.target.value)} />
                <Button disabled={!selectedPurchaseUuid || !returnWarehouseUuid || !returnProductUuid || Number(returnQuantity) <= 0 || returnMutation.isPending} onClick={() => returnMutation.mutate()}>
                  {returnMutation.isPending ? 'Submitting...' : 'Submit purchase return'}
                </Button>
              </div>
            </article>

            <RecordPanel
              title="Purchase returns"
              rows={(purchaseReturnsQuery.data ?? []).map((entry) => ({
                id: entry.uuid,
                primary: `${entry.purchaseUuid} · Product ${entry.productUuid}`,
                secondary: `Qty: ${entry.quantity} · Amount: ${formatMoney(entry.amount)} · ${entry.reason ?? 'No reason'}`,
              }))}
            />
          </section>
        ) : null}
      </div>
    </section>
  );
}

function RecordPanel(props: {
  title: string;
  rows: Array<{ id: string; primary: string; secondary: string; badge?: string; onSelect?: () => void }>;
}) {
  return (
    <article className="rounded-2xl border border-border bg-surface p-4 shadow-sm">
      <h2 className="text-base font-semibold">{props.title}</h2>
      <div className="mt-3 max-h-[30rem] space-y-2 overflow-auto">
        {props.rows.map((row) => (
          <button key={row.id} type="button" className="block w-full rounded-lg border border-border bg-background px-3 py-2 text-left text-sm hover:bg-surface-muted" onClick={row.onSelect}>
            <div className="flex items-center justify-between gap-2">
              <p className="font-medium">{row.primary}</p>
              {row.badge ? <StatusBadge tone={toneForStatus(row.badge)}>{row.badge}</StatusBadge> : null}
            </div>
            <p className="text-xs text-muted">{row.secondary}</p>
          </button>
        ))}
        {props.rows.length === 0 ? <p className="text-sm text-muted">No records.</p> : null}
      </div>
    </article>
  );
}
