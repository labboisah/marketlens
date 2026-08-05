import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Button, Input, PosKeypad, ProductSearch, Select, StatusBadge } from '@marketlens/ui';
import { ChevronLeft, ChevronRight, Search, ShoppingCart } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useVirtualizer } from '@tanstack/react-virtual';
import { useClientUiStore } from '../state/client-ui-store';
import { loadCashierShiftContext } from '../services/cashier-shift-service';
import { fetchCreditSaleEligibility } from '../services/customer-account-service';
import {
  cancelPosSale,
  createPosSaleReturn,
  fetchReceiptDetails,
  fetchSaleReturnDetails,
  loadPosCatalog,
  reprintReceipt,
  searchCustomers,
  searchPosSales,
  submitPosSale,
  type PosProduct,
  type PosSaleLookupResult,
} from '../services/pos-service';

type SearchMode = 'all' | 'recent' | 'frequent' | 'category' | 'brand';
type PriceLevel = 'retail' | 'wholesale' | 'manual';

type PaymentMode = 'cash' | 'bank_transfer' | 'pos_terminal' | 'mobile_money' | 'credit';

type PosPaymentDraft = {
  id: string;
  paymentMethodUuid: string;
  paymentMethodName: string;
  paymentMethodType: PaymentMode;
  amount: string;
  amountTendered: string;
  reference: string;
  transferVerified: boolean;
  creditAmount: string;
  customerCreditSelection: string;
};

type PosCartLine = {
  id: string;
  productUuid: string;
  sku: string;
  name: string;
  categoryName?: string | null;
  brandName?: string | null;
  unitUuid: string;
  unitName: string;
  unitCode: string;
  quantity: number;
  unitPrice: string;
  lineDiscount: string;
  note: string;
  minSellingPrice?: string | null;
  retailPrice?: string | null;
  wholesalePrice?: string | null;
  stockAvailable: string;
};

function formatMoney(value: number): string {
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);
}

function parseMoney(value: string): number {
  return Number(value || '0');
}

function clampQuantity(value: number): number {
  return Number.isFinite(value) && value > 0 ? value : 1;
}

function productStockForUnit(product: PosProduct, unitUuid: string): string {
  return product.stockByUnit.find((stock) => stock.unitUuid === unitUuid)?.quantity ?? '0';
}

function resolveDefaultUnit(product: PosProduct): PosProduct['units'][number] | undefined {
  return product.units.find((unit) => unit.unitUuid === product.defaultSalesUnitUuid) ?? product.units[0];
}

function resolveUnitPrice(product: PosProduct, priceLevel: PriceLevel): string {
  if (priceLevel === 'wholesale' && product.wholesalePrice) {
    return product.wholesalePrice;
  }

  if (product.retailPrice) {
    return product.retailPrice;
  }

  if (product.wholesalePrice) {
    return product.wholesalePrice;
  }

  return product.minSellingPrice ?? '0';
}

function getProductSearchText(product: PosProduct): string {
  return [
    product.sku,
    product.name,
    product.description ?? '',
    product.categoryName ?? '',
    product.brandName ?? '',
    ...(product.barcodes ?? []),
  ].join(' ').toLowerCase();
}

function currentLineTotal(line: PosCartLine): number {
  return clampQuantity(line.quantity) * parseMoney(line.unitPrice) - parseMoney(line.lineDiscount);
}

function cartSubtotal(lines: PosCartLine[]): number {
  return lines.reduce((total, line) => total + clampQuantity(line.quantity) * parseMoney(line.unitPrice), 0);
}

function cartDiscount(lines: PosCartLine[], saleDiscount: string): number {
  return lines.reduce((total, line) => total + parseMoney(line.lineDiscount), 0) + parseMoney(saleDiscount);
}

function validateLine(line: PosCartLine): string | undefined {
  const minimum = parseMoney(line.minSellingPrice ?? '0');
  const price = parseMoney(line.unitPrice);
  if (minimum > 0 && price < minimum) {
    return `Below minimum price ${formatMoney(minimum)}`;
  }

  if (parseMoney(line.lineDiscount) > clampQuantity(line.quantity) * price) {
    return 'Line discount exceeds line total';
  }

  return undefined;
}

export function PosScreen() {
  const queryClient = useQueryClient();
  const username = useClientUiStore((state) => state.username);
  const deviceUuid = useClientUiStore((state) => state.deviceUuid);
  const shopStatus = useClientUiStore((state) => state.shopStatus);
  const connectionState = useClientUiStore((state) => state.connectionState);
  const shiftStatus = useClientUiStore((state) => state.shiftStatus);
  const setActiveNavigationId = useClientUiStore((state) => state.setActiveNavigationId);
  const notify = useClientUiStore((state) => state.notify);

  const [warehouseUuid, setWarehouseUuid] = useState('');
  const [customerSearch, setCustomerSearch] = useState('');
  const [selectedCustomerUuid, setSelectedCustomerUuid] = useState('');
  const [selectedPriceLevel, setSelectedPriceLevel] = useState<PriceLevel>('retail');
  const [saleDiscount, setSaleDiscount] = useState('0');
  const [paymentMethodUuid, setPaymentMethodUuid] = useState('');
  const [paymentsDialogOpen, setPaymentsDialogOpen] = useState(false);
  const [selectedReceiptFormat, setSelectedReceiptFormat] = useState<'thermal' | 'a4'>('thermal');
  const [selectedPrinterName, setSelectedPrinterName] = useState('');
  const [receiptCopyCount, setReceiptCopyCount] = useState('1');
  const [creditDueDate, setCreditDueDate] = useState('');
  const [reprintReason, setReprintReason] = useState('');
  const [pendingReprintReceiptUuid, setPendingReprintReceiptUuid] = useState('');
  const [paymentDrafts, setPaymentDrafts] = useState<PosPaymentDraft[]>([]);
  const [idempotencyKey, setIdempotencyKey] = useState(() => crypto.randomUUID());
  const [lastCompletedReceiptUuid, setLastCompletedReceiptUuid] = useState('');
  const [searchText, setSearchText] = useState('');
  const [barcodeInput, setBarcodeInput] = useState('');
  const [quantityDraft, setQuantityDraft] = useState('1');
  const [searchMode, setSearchMode] = useState<SearchMode>('all');
  const [activeIndex, setActiveIndex] = useState(0);
  const [saleError, setSaleError] = useState<string>();
  const [lineNoteDraft, setLineNoteDraft] = useState('');
  const [cartLines, setCartLines] = useState<PosCartLine[]>([]);
  const [lookupSaleNumber, setLookupSaleNumber] = useState('');
  const [lookupReceiptNumber, setLookupReceiptNumber] = useState('');
  const [lookupCustomer, setLookupCustomer] = useState('');
  const [lookupDate, setLookupDate] = useState('');
  const [lookupCashier, setLookupCashier] = useState('');
  const [lookupProduct, setLookupProduct] = useState('');
  const [selectedLookupSaleUuid, setSelectedLookupSaleUuid] = useState('');
  const [returnType, setReturnType] = useState<'full' | 'partial'>('partial');
  const [returnReason, setReturnReason] = useState('');
  const [returnRefundMethod, setReturnRefundMethod] = useState<'cash' | 'bank_transfer' | 'pos_terminal' | 'mobile_money' | 'credit_note'>('cash');
  const [returnApprovalRequested, setReturnApprovalRequested] = useState(true);
  const [returnItemQuantities, setReturnItemQuantities] = useState<Record<string, string>>({});
  const [printReturnReceiptAfterCreate, setPrintReturnReceiptAfterCreate] = useState(true);
  const [cancellationReason, setCancellationReason] = useState('');
  const [cancellationRefundMethod, setCancellationRefundMethod] = useState<'cash' | 'bank_transfer' | 'pos_terminal' | 'mobile_money' | 'credit_note'>('cash');
  const [cancellationApprovalRequested, setCancellationApprovalRequested] = useState(true);
  const [cancellationRefundState, setCancellationRefundState] = useState<'pending' | 'refunded'>('pending');

  const searchInputRef = useRef<HTMLInputElement | null>(null);
  const barcodeInputRef = useRef<HTMLInputElement | null>(null);
  const listScrollRef = useRef<HTMLDivElement | null>(null);
  const debouncedSearch = useDebouncedValue(searchText, 180);
  const debouncedCustomerSearch = useDebouncedValue(customerSearch, 220);

  const printersQuery = useQuery({
    queryKey: ['client-printers'],
    queryFn: async () => window.marketlensClient.printer.list(),
    staleTime: 60_000,
    refetchOnWindowFocus: false,
  });

  const defaultPrinterQuery = useQuery({
    queryKey: ['client-default-printer'],
    queryFn: async () => window.marketlensClient.printer.getDefault(),
    staleTime: 60_000,
    refetchOnWindowFocus: false,
  });

  const shiftContextQuery = useQuery({
    queryKey: ['pos-shift-context', shopStatus?.shopDeviceUuid ?? 'unknown', username ?? 'unknown', deviceUuid ?? 'unknown'],
    queryFn: async () => {
      const session = await window.marketlensClient.session.load();

      if (!session || !deviceUuid) {
        throw new Error('Session not available.');
      }

      return loadCashierShiftContext({
        shopBaseUrl: session.shopBaseUrl,
        accessToken: session.token,
        deviceUuid,
      });
    },
    enabled: Boolean(shopStatus && connectionState === 'connected' && username && deviceUuid),
    staleTime: 15_000,
    refetchOnWindowFocus: false,
  });

  const catalogQuery = useQuery({
    queryKey: ['pos-catalog', shopStatus?.shopDeviceUuid ?? 'unknown', warehouseUuid || 'all', username ?? 'unknown'],
    queryFn: async () => {
      const session = await window.marketlensClient.session.load();

      if (!session || !deviceUuid) {
        throw new Error('Session not available.');
      }

      return loadPosCatalog({
        shopBaseUrl: session.shopBaseUrl,
        accessToken: session.token,
        deviceUuid,
        warehouseUuid: warehouseUuid || undefined,
        limit: 200,
      });
    },
    enabled: Boolean(shiftContextQuery.data && connectionState === 'connected' && username && deviceUuid),
    staleTime: 30_000,
    refetchOnWindowFocus: false,
  });

  const customersQuery = useQuery({
    queryKey: ['pos-customers', shopStatus?.shopDeviceUuid ?? 'unknown', debouncedCustomerSearch, username ?? 'unknown'],
    queryFn: async () => {
      const session = await window.marketlensClient.session.load();

      if (!session || !deviceUuid) {
        throw new Error('Session not available.');
      }

      return searchCustomers({
        shopBaseUrl: session.shopBaseUrl,
        accessToken: session.token,
        deviceUuid,
        search: debouncedCustomerSearch,
      });
    },
    enabled: Boolean(debouncedCustomerSearch.trim().length > 0 && connectionState === 'connected' && username && deviceUuid),
    staleTime: 30_000,
    refetchOnWindowFocus: false,
  });

  const saleLookupQuery = useQuery({
    queryKey: ['pos-sale-lookup', shopStatus?.shopDeviceUuid ?? 'unknown', lookupSaleNumber, lookupReceiptNumber, lookupCustomer, lookupDate, lookupCashier, lookupProduct],
    queryFn: async () => {
      const session = await window.marketlensClient.session.load();
      if (!session || !deviceUuid) {
        throw new Error('Session not available.');
      }

      return searchPosSales({
        shopBaseUrl: session.shopBaseUrl,
        accessToken: session.token,
        deviceUuid,
        saleNumber: lookupSaleNumber.trim() || undefined,
        receiptNumber: lookupReceiptNumber.trim() || undefined,
        customer: lookupCustomer.trim() || undefined,
        date: lookupDate.trim() || undefined,
        cashier: lookupCashier.trim() || undefined,
        product: lookupProduct.trim() || undefined,
        limit: 40,
      });
    },
    enabled: false,
    staleTime: 20_000,
    refetchOnWindowFocus: false,
  });

  const createReturnMutation = useMutation({
    mutationFn: async () => {
      const session = await window.marketlensClient.session.load();
      if (!session || !deviceUuid || !selectedLookupSaleUuid) {
        throw new Error('A selected sale is required for returns.');
      }

      const selectedSale = (saleLookupQuery.data ?? []).find((sale) => sale.uuid === selectedLookupSaleUuid);
      if (!selectedSale) {
        throw new Error('Selected sale was not found in lookup results.');
      }

      const partialItems = selectedSale.items
        .map((item) => ({
          saleItemUuid: item.uuid,
          quantity: String(Number(returnItemQuantities[item.uuid] ?? '0') || 0),
        }))
        .filter((item) => Number(item.quantity) > 0);

      return createPosSaleReturn({
        shopBaseUrl: session.shopBaseUrl,
        accessToken: session.token,
        deviceUuid,
        saleUuid: selectedLookupSaleUuid,
        returnType,
        reason: returnReason.trim(),
        items: returnType === 'partial' ? partialItems : undefined,
        refundMethod: returnRefundMethod,
        approvalRequested: returnApprovalRequested,
        approvedByUserUuid: returnApprovalRequested ? undefined : useClientUiStore.getState().currentUser?.uuid,
        requestedByUserUuid: useClientUiStore.getState().currentUser?.uuid,
      });
    },
    onSuccess: async (result) => {
      if (printReturnReceiptAfterCreate) {
        const session = await window.marketlensClient.session.load();
        if (session && deviceUuid) {
          const details = await fetchSaleReturnDetails({
            shopBaseUrl: session.shopBaseUrl,
            accessToken: session.token,
            deviceUuid,
            saleReturnUuid: result.uuid,
          });

          await window.marketlensClient.printer.printReceipt({
            receiptUuid: details.uuid,
            saleUuid: details.saleUuid,
            format: selectedReceiptFormat,
            preview: false,
            printerName: selectedPrinterName || undefined,
            copies: Math.max(1, Math.min(10, Number(receiptCopyCount) || 1)),
            receipt: {
              receiptNumber: details.receiptVerificationReference,
              saleNumber: details.saleNumber,
              createdAt: details.createdAt,
              cashier: details.cashierUsername ?? details.cashierUserUuid ?? 'Cashier',
              customer: details.customerName ?? 'Walk-in customer',
              businessName: details.businessName ?? 'MarketLens',
              branchName: details.branchName ?? 'Branch',
              items: details.items.map((item) => ({
                name: `${item.name} (Return)`,
                sku: item.sku ?? undefined,
                unit: item.unitCode ?? item.unitName ?? undefined,
                quantity: item.quantity,
                unitPrice: item.amount,
                lineTotal: item.amount,
              })),
              payments: [{ method: `Refund (${details.refundMethod ?? 'n/a'})`, amount: details.totals.refundAmount, reference: details.reason }],
              subtotal: details.totals.refundAmount,
              discountAmount: '0',
              totalAmount: details.totals.refundAmount,
              totalPaid: details.totals.refundAmount,
              changeDue: '0',
              balance: '0',
              verificationReference: details.receiptVerificationReference,
              thankYouMessage: details.thankYouMessage,
            },
          });
        }
      }

      notify({
        tone: 'success',
        title: 'Return recorded',
        message: 'Shop App created a return record without editing the original sale.',
      });
      setReturnReason('');
      setReturnItemQuantities({});
      await saleLookupQuery.refetch();
    },
    onError: (error) => {
      notify({
        tone: 'danger',
        title: 'Return failed',
        message: error instanceof Error ? error.message : 'Unable to create return.',
      });
    },
  });

  const cancellationMutation = useMutation({
    mutationFn: async () => {
      const session = await window.marketlensClient.session.load();
      if (!session || !deviceUuid || !selectedLookupSaleUuid) {
        throw new Error('A selected sale is required for cancellation.');
      }

      return cancelPosSale({
        shopBaseUrl: session.shopBaseUrl,
        accessToken: session.token,
        deviceUuid,
        saleUuid: selectedLookupSaleUuid,
        reason: cancellationReason.trim(),
        refundMethod: cancellationRefundMethod,
        approvalRequested: cancellationApprovalRequested,
        approvedByUserUuid: cancellationApprovalRequested ? undefined : useClientUiStore.getState().currentUser?.uuid,
        refundState: cancellationRefundState,
        requestedByUserUuid: useClientUiStore.getState().currentUser?.uuid,
      });
    },
    onSuccess: async () => {
      notify({
        tone: 'success',
        title: 'Cancellation recorded',
        message: 'Shop App created a cancellation record and left the original sale immutable.',
      });
      setCancellationReason('');
      await saleLookupQuery.refetch();
    },
    onError: (error) => {
      notify({
        tone: 'danger',
        title: 'Cancellation failed',
        message: error instanceof Error ? error.message : 'Unable to cancel sale.',
      });
    },
  });

  useEffect(() => {
    const context = shiftContextQuery.data;
    if (!context) {
      return;
    }

    if (!warehouseUuid && context.terminals.length > 0) {
      const firstWarehouse = catalogQuery.data?.warehouses[0];
      if (firstWarehouse) {
        setWarehouseUuid(firstWarehouse.uuid);
      }
    }

    if (!paymentMethodUuid && catalogQuery.data?.paymentMethods.length > 0) {
      setPaymentMethodUuid(catalogQuery.data.paymentMethods[0].uuid);
    }

    if (paymentDrafts.length === 0 && catalogQuery.data?.paymentMethods.length) {
      const first = catalogQuery.data.paymentMethods[0];
      const initialAmount = formatMoneyRaw(Math.max(cartSubtotal(cartLines) - cartDiscount(cartLines, saleDiscount), 0));
      setPaymentDrafts([createPaymentDraft(first.uuid, first.name, asPaymentMode(first.type), initialAmount)]);
    }
  }, [catalogQuery.data?.paymentMethods, catalogQuery.data?.warehouses, cartLines, paymentDrafts.length, paymentMethodUuid, saleDiscount, shiftContextQuery.data, warehouseUuid]);

  useEffect(() => {
    if (selectedPrinterName) {
      return;
    }

    const first = defaultPrinterQuery.data ?? printersQuery.data?.find((printer) => printer.isDefault)?.name ?? '';
    if (first) {
      setSelectedPrinterName(first);
    }
  }, [defaultPrinterQuery.data, printersQuery.data, selectedPrinterName]);

  const activeShift = shiftContextQuery.data?.currentShift.shift ?? null;
  const catalog = catalogQuery.data;
  const warehouses = catalog?.warehouses ?? [];
  const categories = catalog?.categories ?? [];
  const brands = catalog?.brands ?? [];
  const paymentMethods = catalog?.paymentMethods ?? [];
  const products = catalog?.products ?? [];

  useEffect(() => {
    if (products.length === 0) {
      return;
    }

    if (activeIndex >= products.length) {
      setActiveIndex(0);
    }
  }, [activeIndex, products.length]);

  const filteredProducts = useMemo(() => {
    const query = debouncedSearch.trim().toLowerCase();

    const base = products.filter((product) => {
      const searchable = getProductSearchText(product);
      if (searchMode === 'recent') {
        return Boolean(product.lastSoldAt);
      }
      if (searchMode === 'frequent') {
        return product.soldCount > 0;
      }
      if (searchMode === 'category' && query.length > 0) {
        return (product.categoryName ?? '').toLowerCase().includes(query);
      }
      if (searchMode === 'brand' && query.length > 0) {
        return (product.brandName ?? '').toLowerCase().includes(query);
      }
      return query.length === 0 || searchable.includes(query);
    });

    return base.sort((left, right) => {
      if (searchMode === 'recent') {
        return String(right.lastSoldAt ?? '').localeCompare(String(left.lastSoldAt ?? ''));
      }
      if (searchMode === 'frequent') {
        return right.soldCount - left.soldCount;
      }
      return left.name.localeCompare(right.name);
    });
  }, [debouncedSearch, products, searchMode]);

  useEffect(() => {
    setActiveIndex(0);
  }, [debouncedSearch, searchMode]);

  const virtualizer = useVirtualizer({
    count: filteredProducts.length,
    getScrollElement: () => listScrollRef.current,
    estimateSize: () => 88,
    overscan: 6,
  });

  const cartValidationErrors = cartLines.map(validateLine).filter(Boolean) as string[];
  const subtotal = cartSubtotal(cartLines);
  const discount = cartDiscount(cartLines, saleDiscount);
  const tax = 0;
  const grandTotal = Math.max(subtotal - discount + tax, 0);
  const totalPaidByDrafts = paymentDrafts.reduce((sum, payment) => sum + parseMoney(payment.amount), 0);
  const totalTenderedByDrafts = paymentDrafts.reduce((sum, payment) => sum + parseMoney(payment.amountTendered || '0'), 0);
  const changeDue = Math.max(totalTenderedByDrafts - totalPaidByDrafts, 0);
  const balanceDue = Math.max(grandTotal - totalPaidByDrafts, 0);
  const requiresCustomerForCredit = paymentDrafts.some((payment) => payment.paymentMethodType === 'credit' || parseMoney(payment.creditAmount) > 0);
  const totalCreditRequested = paymentDrafts
    .filter((payment) => payment.paymentMethodType === 'credit')
    .reduce((sum, payment) => {
      const creditAmount = parseMoney(payment.creditAmount || '0');
      const fallback = parseMoney(payment.amount || '0');
      return sum + (creditAmount > 0 ? creditAmount : fallback);
    }, 0);
  const paymentValidationErrors = validatePaymentDrafts(paymentDrafts, grandTotal, Boolean(selectedCustomerUuid));
  const selectedLookupSale: PosSaleLookupResult | undefined = (saleLookupQuery.data ?? []).find((sale) => sale.uuid === selectedLookupSaleUuid);

  const creditEligibilityQuery = useQuery({
    queryKey: ['pos-credit-eligibility', selectedCustomerUuid, totalCreditRequested, creditDueDate],
    queryFn: async () => {
      const session = await window.marketlensClient.session.load();
      if (!session || !deviceUuid || !selectedCustomerUuid || !creditDueDate) {
        throw new Error('Customer and due date are required for credit eligibility.');
      }

      return fetchCreditSaleEligibility({
        shopBaseUrl: session.shopBaseUrl,
        accessToken: session.token,
        deviceUuid,
        customerUuid: selectedCustomerUuid,
        requestedAmount: formatMoneyRaw(totalCreditRequested),
        dueDate: creditDueDate,
      });
    },
    enabled: Boolean(requiresCustomerForCredit && selectedCustomerUuid && creditDueDate && totalCreditRequested > 0),
    staleTime: 0,
    refetchOnWindowFocus: false,
  });

  const creditRequiresEligibility = requiresCustomerForCredit && totalCreditRequested > 0;
  const creditEligibilityBlocked = creditRequiresEligibility
    && (!selectedCustomerUuid || !creditDueDate || !creditEligibilityQuery.data?.eligible);

  useEffect(() => {
    if (!selectedLookupSale) {
      return;
    }

    const next: Record<string, string> = {};
    for (const item of selectedLookupSale.items) {
      const remaining = Math.max(Number(item.quantity) - Number(item.returnedQuantity || 0), 0);
      next[item.uuid] = remaining > 0 ? String(remaining) : '0';
    }
    setReturnItemQuantities(next);
  }, [selectedLookupSale]);

  useEffect(() => {
    if (paymentDrafts.length === 0) {
      return;
    }

    const totalPaid = paymentDrafts.reduce((sum, payment) => sum + parseMoney(payment.amount), 0);
    if (Math.abs(totalPaid - grandTotal) < 0.0001) {
      return;
    }

    const remaining = Math.max(grandTotal - (totalPaid - parseMoney(paymentDrafts[0]?.amount || '0')), 0);
    setPaymentDrafts((current) => {
      if (current.length === 0) {
        return current;
      }
      const next = [...current];
      next[0] = { ...next[0], amount: formatMoneyRaw(remaining), amountTendered: next[0].paymentMethodType === 'cash' ? formatMoneyRaw(remaining) : next[0].amountTendered };
      return next;
    });
  }, [grandTotal]);

  const saleMutation = useMutation({
    mutationFn: async () => {
      const session = await window.marketlensClient.session.load();

      if (!session || !deviceUuid || !activeShift || !warehouseUuid) {
        throw new Error('A valid session, shift, and warehouse are required.');
      }

      const discountAmount = saleDiscount.trim().length > 0 ? saleDiscount : undefined;

      if (paymentValidationErrors.length > 0) {
        throw new Error(paymentValidationErrors[0]);
      }

      if (requiresCustomerForCredit && !selectedCustomerUuid) {
        throw new Error('Select a customer for credit sales.');
      }

      if (creditEligibilityBlocked) {
        throw new Error('Credit sale is not eligible or missing due date/approval readiness from Shop App.');
      }

      return submitPosSale({
        shopBaseUrl: session.shopBaseUrl,
        accessToken: session.token,
        deviceUuid,
        idempotencyKey,
        shiftUuid: activeShift.uuid,
        warehouseUuid,
        customerUuid: selectedCustomerUuid || undefined,
        discountAmount,
        receiptFormat: selectedReceiptFormat,
        items: cartLines.map((line) => ({
          productUuid: line.productUuid,
          unitUuid: line.unitUuid,
          quantity: String(clampQuantity(line.quantity)),
          unitPrice: line.unitPrice,
        })),
        payments: paymentDrafts.map((payment) => ({
          paymentMethodUuid: payment.paymentMethodUuid,
          amount: formatMoneyRaw(parseMoney(payment.amount)),
          amountTendered: payment.amountTendered.trim() ? formatMoneyRaw(parseMoney(payment.amountTendered)) : undefined,
          reference: payment.reference.trim() || undefined,
          transferVerified: payment.transferVerified,
          creditAmount: payment.creditAmount.trim() ? formatMoneyRaw(parseMoney(payment.creditAmount)) : undefined,
          customerCreditSelection: payment.customerCreditSelection.trim() || undefined,
        })),
      });
    },
    onSuccess: async (sale) => {
      const session = await window.marketlensClient.session.load();
      if (!session || !deviceUuid) {
        throw new Error('Session not available for receipt printing.');
      }

      const receipt = await fetchReceiptDetails({
        shopBaseUrl: session.shopBaseUrl,
        accessToken: session.token,
        deviceUuid,
        receiptUuid: sale.receipt.uuid,
      });

      const printResult = await window.marketlensClient.printer.printReceipt({
        receiptUuid: receipt.uuid,
        saleUuid: receipt.saleUuid,
        format: selectedReceiptFormat,
        preview: false,
        printerName: selectedPrinterName || undefined,
        copies: Math.max(1, Math.min(10, Number(receiptCopyCount) || 1)),
        receipt: {
          receiptNumber: receipt.receiptNumber,
          saleNumber: receipt.saleNumber,
          createdAt: receipt.saleCreatedAt,
          cashier: receipt.cashierUsername ?? receipt.cashierUserUuid ?? 'Cashier',
          customer: receipt.customerName ?? 'Walk-in customer',
          businessName: receipt.businessName ?? 'MarketLens',
          branchName: receipt.branchName ?? 'Branch',
          items: receipt.items.map((item) => ({
            name: item.name,
            sku: item.sku ?? undefined,
            unit: item.unitCode ?? item.unitName ?? undefined,
            quantity: item.quantity,
            unitPrice: item.unitPrice,
            lineTotal: item.lineTotal,
          })),
          payments: receipt.payments.map((payment) => ({
            method: payment.paymentMethodName,
            amount: payment.amount,
            reference: payment.reference,
          })),
          subtotal: receipt.totals.subtotal,
          discountAmount: receipt.totals.discountAmount,
          totalAmount: receipt.totals.totalAmount,
          totalPaid: receipt.totals.totalPaid,
          changeDue: receipt.totals.changeDue,
          balance: receipt.totals.balance,
          verificationReference: receipt.receiptVerificationReference,
          thankYouMessage: receipt.thankYouMessage,
        },
      });

      setCartLines([]);
      setSaleDiscount('0');
      setSelectedCustomerUuid('');
      setSaleError(undefined);
      setPaymentsDialogOpen(false);
      setLastCompletedReceiptUuid(sale.receipt.uuid);
      setPendingReprintReceiptUuid(sale.receipt.uuid);
      setReprintReason('');
      setIdempotencyKey(crypto.randomUUID());
      if (paymentMethods.length > 0) {
        const first = paymentMethods[0];
        setPaymentDrafts([createPaymentDraft(first.uuid, first.name, asPaymentMode(first.type), '0')]);
      }
      notify(printResult.accepted
        ? {
            tone: 'success',
            title: 'Sale completed',
            message: 'The Shop App confirmed the sale and the receipt was sent to the printer.',
          }
        : {
            tone: 'warning',
            title: 'Sale completed, printing failed',
            message: printResult.reason ?? 'The Shop App confirmed the sale, but receipt printing failed. Use reprint after checking printer setup.',
          });
      await queryClient.invalidateQueries({ queryKey: ['pos-catalog'] });
      await queryClient.invalidateQueries({ queryKey: ['cashier-shift-context'] });
      await queryClient.invalidateQueries({ queryKey: ['pos-customers'] });
    },
    onError: (error) => {
      setSaleError(error instanceof Error ? error.message : 'Sale submission failed.');
      notify({
        tone: 'danger',
        title: 'Sale failed',
        message: error instanceof Error ? error.message : 'Sale submission failed.',
      });
    },
  });

  const selectedResult = filteredProducts[activeIndex];
  const recentProducts = filteredProducts.filter((product) => Boolean(product.lastSoldAt)).slice(0, 12);
  const frequentProducts = filteredProducts.filter((product) => product.soldCount > 0).slice(0, 12);

  const addProductToCart = (product: PosProduct) => {
    const unit = resolveDefaultUnit(product);
    if (!unit) {
      return;
    }

    const nextLine: PosCartLine = {
      id: crypto.randomUUID(),
      productUuid: product.uuid,
      sku: product.sku,
      name: product.name,
      categoryName: product.categoryName,
      brandName: product.brandName,
      unitUuid: unit.unitUuid,
      unitName: unit.unitName,
      unitCode: unit.unitCode,
      quantity: clampQuantity(Number(quantityDraft || '1')),
      unitPrice: resolveUnitPrice(product, selectedPriceLevel),
      lineDiscount: '0',
      note: lineNoteDraft.trim(),
      minSellingPrice: product.minSellingPrice,
      retailPrice: product.retailPrice,
      wholesalePrice: product.wholesalePrice,
      stockAvailable: productStockForUnit(product, unit.unitUuid),
    };

    const existingIndex = cartLines.findIndex((line) => line.productUuid === product.uuid && line.unitUuid === unit.unitUuid && line.unitPrice === nextLine.unitPrice && line.note === nextLine.note);
    if (existingIndex >= 0) {
      setCartLines((current) => current.map((line, index) => index === existingIndex ? { ...line, quantity: line.quantity + nextLine.quantity } : line));
    } else {
      setCartLines((current) => [nextLine, ...current]);
    }

    setLineNoteDraft('');
    setQuantityDraft('1');
    setSearchText('');
    setActiveIndex(0);
  };

  const updateLine = (lineId: string, patch: Partial<PosCartLine>) => {
    setCartLines((current) => current.map((line) => {
      if (line.id !== lineId) {
        return line;
      }

      const nextLine = { ...line, ...patch };
      if (patch.unitUuid && catalog) {
        const product = catalog.products.find((item) => item.uuid === line.productUuid);
        if (product) {
          const unit = product.units.find((item) => item.unitUuid === patch.unitUuid) ?? product.units[0];
          if (unit) {
            nextLine.unitName = unit.unitName;
            nextLine.unitCode = unit.unitCode;
            nextLine.stockAvailable = productStockForUnit(product, unit.unitUuid);
          }
        }
      }

      return nextLine;
    }));
  };

  const removeLine = (lineId: string) => setCartLines((current) => current.filter((line) => line.id !== lineId));

  const addPaymentLine = () => {
    if (paymentMethods.length === 0) {
      return;
    }
    const method = paymentMethods[0];
    setPaymentDrafts((current) => [
      ...current,
      createPaymentDraft(method.uuid, method.name, asPaymentMode(method.type), '0'),
    ]);
  };

  const removePaymentLine = (lineId: string) => {
    setPaymentDrafts((current) => current.filter((line) => line.id !== lineId));
  };

  const updatePaymentLine = (lineId: string, patch: Partial<PosPaymentDraft>) => {
    setPaymentDrafts((current) => current.map((line) => {
      if (line.id !== lineId) {
        return line;
      }

      const next = { ...line, ...patch };

      if (patch.paymentMethodUuid) {
        const method = paymentMethods.find((item) => item.uuid === patch.paymentMethodUuid);
        if (method) {
          next.paymentMethodName = method.name;
          next.paymentMethodType = asPaymentMode(method.type);
          if (next.paymentMethodType !== 'cash') {
            next.amountTendered = '';
          }
          if (!['bank_transfer', 'pos_terminal', 'mobile_money'].includes(next.paymentMethodType)) {
            next.reference = '';
            next.transferVerified = false;
          }
        }
      }

      return next;
    }));
  };

  const openPaymentsDialog = () => {
    if (paymentDrafts.length === 0 && paymentMethods.length > 0) {
      const first = paymentMethods[0];
      setPaymentDrafts([createPaymentDraft(first.uuid, first.name, asPaymentMode(first.type), formatMoneyRaw(grandTotal))]);
    }
    setPaymentsDialogOpen(true);
  };

  const runReceiptPreview = async () => {
    const session = await window.marketlensClient.session.load();
    if (!session || !deviceUuid || !lastCompletedReceiptUuid) {
      return;
    }

    const receipt = await fetchReceiptDetails({
      shopBaseUrl: session.shopBaseUrl,
      accessToken: session.token,
      deviceUuid,
      receiptUuid: lastCompletedReceiptUuid,
    });

    const previewResult = await window.marketlensClient.printer.printReceipt({
      receiptUuid: receipt.uuid,
      saleUuid: receipt.saleUuid,
      format: selectedReceiptFormat,
      preview: true,
      printerName: selectedPrinterName || undefined,
      copies: Math.max(1, Math.min(10, Number(receiptCopyCount) || 1)),
      receipt: {
        receiptNumber: receipt.receiptNumber,
        saleNumber: receipt.saleNumber,
        createdAt: receipt.saleCreatedAt,
        cashier: receipt.cashierUsername ?? receipt.cashierUserUuid ?? 'Cashier',
        customer: receipt.customerName ?? 'Walk-in customer',
        businessName: receipt.businessName ?? 'MarketLens',
        branchName: receipt.branchName ?? 'Branch',
        items: receipt.items.map((item) => ({
          name: item.name,
          sku: item.sku ?? undefined,
          unit: item.unitCode ?? item.unitName ?? undefined,
          quantity: item.quantity,
          unitPrice: item.unitPrice,
          lineTotal: item.lineTotal,
        })),
        payments: receipt.payments.map((payment) => ({
          method: payment.paymentMethodName,
          amount: payment.amount,
          reference: payment.reference,
        })),
        subtotal: receipt.totals.subtotal,
        discountAmount: receipt.totals.discountAmount,
        totalAmount: receipt.totals.totalAmount,
        totalPaid: receipt.totals.totalPaid,
        changeDue: receipt.totals.changeDue,
        balance: receipt.totals.balance,
        verificationReference: receipt.receiptVerificationReference,
        thankYouMessage: receipt.thankYouMessage,
      },
    });

    if (!previewResult.accepted) {
      notify({
        tone: 'warning',
        title: 'Preview print failed',
        message: previewResult.reason ?? 'Preview printing failed. Check the selected printer and try again.',
      });
    }
  };

  const handleReprint = async () => {
    const session = await window.marketlensClient.session.load();
    if (!session || !deviceUuid || !pendingReprintReceiptUuid) {
      return;
    }

    if (!reprintReason.trim()) {
      setSaleError('Reprint reason is required.');
      return;
    }

    await reprintReceipt({
      shopBaseUrl: session.shopBaseUrl,
      accessToken: session.token,
      deviceUuid,
      receiptUuid: pendingReprintReceiptUuid,
      reason: reprintReason.trim(),
      approvedByUserUuid: useClientUiStore.getState().currentUser?.uuid,
    });

    const receipt = await fetchReceiptDetails({
      shopBaseUrl: session.shopBaseUrl,
      accessToken: session.token,
      deviceUuid,
      receiptUuid: pendingReprintReceiptUuid,
    });

    const printResult = await window.marketlensClient.printer.printReceipt({
      receiptUuid: receipt.uuid,
      saleUuid: receipt.saleUuid,
      format: selectedReceiptFormat,
      preview: false,
      printerName: selectedPrinterName || undefined,
      copies: Math.max(1, Math.min(10, Number(receiptCopyCount) || 1)),
      receipt: {
        receiptNumber: receipt.receiptNumber,
        saleNumber: receipt.saleNumber,
        createdAt: receipt.saleCreatedAt,
        cashier: receipt.cashierUsername ?? receipt.cashierUserUuid ?? 'Cashier',
        customer: receipt.customerName ?? 'Walk-in customer',
        businessName: receipt.businessName ?? 'MarketLens',
        branchName: receipt.branchName ?? 'Branch',
        items: receipt.items.map((item) => ({
          name: item.name,
          sku: item.sku ?? undefined,
          unit: item.unitCode ?? item.unitName ?? undefined,
          quantity: item.quantity,
          unitPrice: item.unitPrice,
          lineTotal: item.lineTotal,
        })),
        payments: receipt.payments.map((payment) => ({
          method: payment.paymentMethodName,
          amount: payment.amount,
          reference: payment.reference,
        })),
        subtotal: receipt.totals.subtotal,
        discountAmount: receipt.totals.discountAmount,
        totalAmount: receipt.totals.totalAmount,
        totalPaid: receipt.totals.totalPaid,
        changeDue: receipt.totals.changeDue,
        balance: receipt.totals.balance,
        verificationReference: receipt.receiptVerificationReference,
        thankYouMessage: receipt.thankYouMessage,
      },
    });

    notify(printResult.accepted
      ? {
          tone: 'success',
          title: 'Receipt reprinted',
          message: 'Shop App recorded the reprint audit and the receipt was printed.',
        }
      : {
          tone: 'warning',
          title: 'Reprint recorded, printing failed',
          message: printResult.reason ?? 'Shop App recorded the reprint audit, but printing failed.',
        });
    setReprintReason('');
    setSaleError(undefined);
  };

  const onSearchKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      setActiveIndex((current) => Math.min(current + 1, Math.max(filteredProducts.length - 1, 0)));
    }

    if (event.key === 'ArrowUp') {
      event.preventDefault();
      setActiveIndex((current) => Math.max(current - 1, 0));
    }

    if (event.key === 'Enter' && selectedResult) {
      event.preventDefault();
      addProductToCart(selectedResult);
    }
  };

  const onBarcodeKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
    if (event.key !== 'Enter') {
      return;
    }

    const match = products.find((product) => product.barcodes.some((barcode) => barcode === barcodeInput.trim()) || product.sku === barcodeInput.trim());
    if (match) {
      addProductToCart(match);
      setBarcodeInput('');
      return;
    }

    setSaleError('Barcode or SKU not found.');
  };

  if (!activeShift) {
    return (
      <section className="min-h-screen bg-background p-6 text-foreground">
        <div className="mx-auto max-w-2xl rounded-2xl border border-border bg-surface p-6 shadow-sm">
          <h1 className="text-2xl font-semibold">Point of sale</h1>
          <p className="mt-2 text-sm text-muted">Open a cashier shift first. The Shop App blocks sales until the shift is confirmed.</p>
          <div className="mt-4 flex gap-2">
            <Button onClick={() => setActiveNavigationId('cashier-shifts-control')}>Open shift screen</Button>
            <Button variant="ghost" onClick={() => setActiveNavigationId('dashboard-home')}>Back to dashboard</Button>
          </div>
        </div>
      </section>
    );
  }

  return (
    <section className="min-h-screen bg-background p-6 text-foreground">
      <div className="mx-auto grid max-w-7xl gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(360px,0.65fr)]">
        <div className="space-y-6">
          <header className="rounded-2xl border border-border bg-surface p-5 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div>
                <p className="text-xs uppercase tracking-wide text-muted">Sales Operations</p>
                <h1 className="text-2xl font-semibold">Keyboard-first POS</h1>
                <p className="mt-1 text-sm text-muted">Search by barcode, SKU, name, category, or brand. Enter adds the highlighted product.</p>
              </div>
              <div className="flex items-center gap-2">
                <StatusBadge tone={shiftStatus === 'open' ? 'success' : 'warning'}>{activeShift.shiftNumber}</StatusBadge>
                <StatusBadge tone="information">{warehouseUuid ? warehouses.find((warehouse) => warehouse.uuid === warehouseUuid)?.name ?? 'Warehouse' : 'Select warehouse'}</StatusBadge>
              </div>
            </div>

            <div className="mt-4 grid gap-3 xl:grid-cols-[1.2fr_0.8fr_0.7fr_0.7fr]">
              <ProductSearch
                value={searchText}
                onChange={(event) => setSearchText(event.target.value)}
                onKeyDown={onSearchKeyDown}
                placeholder="Search product, SKU, barcode, category, or brand"
                ref={searchInputRef}
              />
              <Input
                ref={barcodeInputRef}
                value={barcodeInput}
                onChange={(event) => setBarcodeInput(event.target.value)}
                onKeyDown={onBarcodeKeyDown}
                label="Barcode / SKU"
                placeholder="Scan barcode"
              />
              <Input value={quantityDraft} onChange={(event) => setQuantityDraft(event.target.value)} label="Quick quantity" inputMode="decimal" />
              <Input value={lineNoteDraft} onChange={(event) => setLineNoteDraft(event.target.value)} label="Quick note" placeholder="Optional line note" />
            </div>

            <div className="mt-3 flex flex-wrap gap-2">
              {(['all', 'recent', 'frequent', 'category', 'brand'] as SearchMode[]).map((mode) => (
                <Button key={mode} variant={searchMode === mode ? 'secondary' : 'ghost'} onClick={() => setSearchMode(mode)}>
                  {mode}
                </Button>
              ))}
            </div>
          </header>

          <section className="grid gap-4 xl:grid-cols-2">
            <article className="rounded-2xl border border-border bg-surface p-5 shadow-sm">
              <div className="flex items-center justify-between gap-3">
                <div>
                  <h2 className="text-lg font-semibold">Catalog</h2>
                  <p className="mt-1 text-sm text-muted">{filteredProducts.length} result(s) from the Shop App catalog</p>
                </div>
                <StatusBadge tone="neutral">{searchMode}</StatusBadge>
              </div>

              <div ref={listScrollRef} className="mt-4 h-[34rem] overflow-auto rounded-xl border border-border bg-background">
                <div style={{ height: `${virtualizer.getTotalSize()}px`, position: 'relative' }}>
                  {virtualizer.getVirtualItems().map((virtualRow) => {
                    const product = filteredProducts[virtualRow.index];
                    const highlighted = virtualRow.index === activeIndex;

                    return (
                      <button
                        type="button"
                        key={product.uuid}
                        className={`absolute left-0 top-0 w-full border-b border-border px-4 py-3 text-left ${highlighted ? 'bg-primary/10' : 'hover:bg-surface-muted'}`}
                        style={{ transform: `translateY(${virtualRow.start}px)` }}
                        onMouseEnter={() => setActiveIndex(virtualRow.index)}
                        onClick={() => addProductToCart(product)}
                      >
                        <div className="flex items-start justify-between gap-3">
                          <div className="min-w-0">
                            <p className="truncate text-sm font-semibold">{product.name}</p>
                            <p className="mt-1 text-xs text-muted">{product.sku} · {product.categoryName ?? 'No category'} · {product.brandName ?? 'No brand'}</p>
                          </div>
                          <div className="flex flex-col items-end gap-1 text-xs text-muted">
                            <span>{product.lastSoldAt ? `Recent ${new Date(product.lastSoldAt).toLocaleDateString()}` : 'No recent sale'}</span>
                            <span>Sold {product.soldCount}</span>
                          </div>
                        </div>
                        <div className="mt-2 flex flex-wrap items-center gap-2 text-xs">
                          <StatusBadge tone="information">{product.barcodes[0] ?? 'No barcode'}</StatusBadge>
                          <StatusBadge tone="neutral">{resolveUnitPrice(product, selectedPriceLevel)}</StatusBadge>
                          <StatusBadge tone={Number(productStockForUnit(product, resolveDefaultUnit(product)?.unitUuid ?? product.defaultSalesUnitUuid ?? '') || '0') > 0 ? 'success' : 'warning'}>
                            Stock {productStockForUnit(product, resolveDefaultUnit(product)?.unitUuid ?? product.defaultSalesUnitUuid ?? '')}
                          </StatusBadge>
                        </div>
                      </button>
                    );
                  })}
                </div>
              </div>

              <div className="mt-3 flex items-center justify-between text-xs text-muted">
                <span>Use arrow keys and Enter to add</span>
                <span>{selectedResult ? selectedResult.name : 'No selection'}</span>
              </div>
            </article>

            <article className="rounded-2xl border border-border bg-surface p-5 shadow-sm">
              <div className="flex items-center justify-between gap-3">
                <div>
                  <h2 className="text-lg font-semibold">Quick tools</h2>
                  <p className="mt-1 text-sm text-muted">Barcode scan, quantity entry, and cart shortcuts.</p>
                </div>
                <ShoppingCart className="h-5 w-5 text-muted" />
              </div>
              <div className="mt-4 grid gap-3">
                <Select
                  label="Warehouse"
                  value={warehouseUuid}
                  onChange={(event) => setWarehouseUuid(event.target.value)}
                  options={warehouses.map((warehouse) => ({ value: warehouse.uuid, label: `${warehouse.name} (${warehouse.code})` }))}
                />
                <Select
                  label="Price level"
                  value={selectedPriceLevel}
                  onChange={(event) => setSelectedPriceLevel(event.target.value as PriceLevel)}
                  options={[
                    { value: 'retail', label: 'Retail' },
                    { value: 'wholesale', label: 'Wholesale' },
                    { value: 'manual', label: 'Manual' },
                  ]}
                />
                <Select
                  label="Payment method"
                  value={paymentMethodUuid}
                  onChange={(event) => setPaymentMethodUuid(event.target.value)}
                  options={paymentMethods.map((method) => ({ value: method.uuid, label: `${method.name} (${method.type})` }))}
                />
                <Input label="Sale discount" value={saleDiscount} onChange={(event) => setSaleDiscount(event.target.value)} inputMode="decimal" />
                <Select
                  label="Receipt format"
                  value={selectedReceiptFormat}
                  onChange={(event) => setSelectedReceiptFormat(event.target.value as 'thermal' | 'a4')}
                  options={[{ value: 'thermal', label: 'Thermal receipt' }, { value: 'a4', label: 'A4 invoice' }]}
                />
                <Select
                  label="Printer"
                  value={selectedPrinterName}
                  onChange={(event) => {
                    setSelectedPrinterName(event.target.value);
                    void window.marketlensClient.printer.setDefault(event.target.value);
                  }}
                  options={(printersQuery.data ?? []).map((printer) => ({ value: printer.name, label: printer.isDefault ? `${printer.displayName} (system default)` : printer.displayName }))}
                />
                <Input label="Receipt copies" value={receiptCopyCount} onChange={(event) => setReceiptCopyCount(event.target.value)} inputMode="numeric" />
                <Button onClick={() => selectedResult ? addProductToCart(selectedResult) : undefined} disabled={!selectedResult}>Add highlighted product</Button>
                <div className="grid grid-cols-2 gap-2">
                  <Button variant="ghost" onClick={() => void queryClient.invalidateQueries({ queryKey: ['pos-catalog'] })}>Refresh catalog</Button>
                  <Button variant="ghost" onClick={() => setActiveNavigationId('dashboard-home')}><ChevronLeft className="h-4 w-4" /> Back</Button>
                </div>
                <PosKeypad
                  onInput={(value) => setQuantityDraft((current) => `${current}${value}`.replace(/^0+(?=\d)/, ''))}
                  onBackspace={() => setQuantityDraft((current) => current.slice(0, -1) || '1')}
                  onClear={() => setQuantityDraft('1')}
                />
              </div>
            </article>
          </section>

          <section className="grid gap-4 xl:grid-cols-2">
            <article className="rounded-2xl border border-border bg-surface p-5 shadow-sm">
              <h2 className="text-lg font-semibold">Customer selection</h2>
              <p className="mt-1 text-sm text-muted">Search customers by code, name, or phone.</p>
              <div className="mt-4 grid gap-3">
                <Input label="Customer search" value={customerSearch} onChange={(event) => setCustomerSearch(event.target.value)} placeholder="Type customer name or code" />
                <div className="max-h-72 overflow-auto rounded-xl border border-border bg-background">
                  {(customersQuery.data ?? []).map((customer) => (
                    <button
                      key={customer.uuid}
                      type="button"
                      className={`block w-full border-b border-border px-4 py-3 text-left text-sm ${selectedCustomerUuid === customer.uuid ? 'bg-primary/10' : 'hover:bg-surface-muted'}`}
                      onClick={() => setSelectedCustomerUuid(customer.uuid)}
                    >
                      <p className="font-medium">{customer.name}</p>
                      <p className="mt-1 text-xs text-muted">{customer.code} · {customer.type} · {customer.phone ?? 'No phone'}</p>
                    </button>
                  ))}
                  {debouncedCustomerSearch.trim().length > 0 && (customersQuery.data ?? []).length === 0 ? <p className="p-4 text-sm text-muted">No matching customer found.</p> : null}
                </div>
                {selectedCustomerUuid ? <StatusBadge tone="information">Selected customer: {selectedCustomerUuid}</StatusBadge> : <StatusBadge tone="neutral">No customer selected</StatusBadge>}
              </div>
            </article>

            <article className="rounded-2xl border border-border bg-surface p-5 shadow-sm">
              <h2 className="text-lg font-semibold">Search suggestions</h2>
              <div className="mt-3 grid gap-3 sm:grid-cols-2">
                <SuggestionList title="Recent products" items={recentProducts} onPick={addProductToCart} />
                <SuggestionList title="Frequently sold" items={frequentProducts} onPick={addProductToCart} />
              </div>
              <div className="mt-4 rounded-xl border border-border bg-background p-4 text-sm text-muted">
                <p>Categories: {categories.map((category) => category.name).slice(0, 6).join(', ') || 'None loaded'}</p>
                <p className="mt-2">Brands: {brands.map((brand) => brand.name).slice(0, 6).join(', ') || 'None loaded'}</p>
              </div>
            </article>
          </section>
        </div>

        <aside className="space-y-4">
          <article className="rounded-2xl border border-border bg-surface p-5 shadow-sm">
            <div className="flex items-center justify-between gap-3">
              <div>
                <h2 className="text-lg font-semibold">Cart</h2>
                <p className="mt-1 text-sm text-muted">{cartLines.length} item(s)</p>
              </div>
              <StatusBadge tone={cartValidationErrors.length > 0 ? 'warning' : 'success'}>{cartValidationErrors.length > 0 ? 'Review required' : 'Ready'}</StatusBadge>
            </div>

            <div className="mt-4 space-y-3">
              {cartLines.length > 0 ? cartLines.map((line) => {
                const lineTotal = currentLineTotal(line);
                const lineError = validateLine(line);
                const product = catalog?.products.find((item) => item.uuid === line.productUuid);

                return (
                  <div key={line.id} className="rounded-xl border border-border bg-background p-3">
                    <div className="flex items-start justify-between gap-3">
                      <div className="min-w-0">
                        <p className="truncate text-sm font-semibold">{line.name}</p>
                        <p className="mt-1 text-xs text-muted">{line.sku} · {line.unitName} · Stock {line.stockAvailable}</p>
                      </div>
                      <StatusBadge tone={lineError ? 'warning' : 'information'}>{formatMoney(lineTotal)}</StatusBadge>
                    </div>

                    <div className="mt-3 grid gap-2 sm:grid-cols-2">
                      <Input label="Quantity" value={String(line.quantity)} onChange={(event) => updateLine(line.id, { quantity: clampQuantity(Number(event.target.value || '1')) })} inputMode="decimal" />
                      <Select
                        label="Unit"
                        value={line.unitUuid}
                        onChange={(event) => updateLine(line.id, { unitUuid: event.target.value })}
                        options={(product?.units ?? []).map((unit) => ({ value: unit.unitUuid, label: `${unit.unitName} (${unit.unitCode})` }))}
                      />
                      <Input label="Unit price" value={line.unitPrice} onChange={(event) => updateLine(line.id, { unitPrice: event.target.value })} inputMode="decimal" />
                      <Input label="Line discount" value={line.lineDiscount} onChange={(event) => updateLine(line.id, { lineDiscount: event.target.value })} inputMode="decimal" />
                    </div>
                    <Input className="mt-2" label="Notes" value={line.note} onChange={(event) => updateLine(line.id, { note: event.target.value })} placeholder="Line note" />

                    <div className="mt-3 flex flex-wrap gap-2 text-xs text-muted">
                      <span>Product: {line.name}</span>
                      <span>Unit: {line.unitName}</span>
                      <span>Qty: {line.quantity}</span>
                      <span>Price: {line.unitPrice}</span>
                      <span>Discount: {line.lineDiscount}</span>
                      <span>Total: {formatMoney(lineTotal)}</span>
                    </div>

                    {lineError ? <p className="mt-2 text-xs text-danger">{lineError}</p> : null}

                    <div className="mt-3 flex items-center justify-between gap-2">
                      <div className="flex gap-1">
                        <Button variant="secondary" onClick={() => updateLine(line.id, { quantity: line.quantity + 1 })}><ChevronRight className="h-4 w-4" /></Button>
                        <Button variant="secondary" onClick={() => updateLine(line.id, { quantity: clampQuantity(line.quantity - 1) })}><ChevronLeft className="h-4 w-4" /></Button>
                      </div>
                      <Button variant="ghost" onClick={() => removeLine(line.id)}>Remove</Button>
                    </div>
                  </div>
                );
              }) : <p className="text-sm text-muted">No items in cart.</p>}
            </div>
          </article>

          <article className="rounded-2xl border border-border bg-surface p-5 shadow-sm">
            <h2 className="text-lg font-semibold">Totals</h2>
            <div className="mt-4 grid gap-3 text-sm">
              <Row label="Cart subtotal" value={formatMoney(subtotal)} />
              <Row label="Discount" value={formatMoney(discount)} />
              <Row label="Tax" value={formatMoney(tax)} />
              <Row label="Grand total" value={formatMoney(grandTotal)} emphasis />
            </div>

            <div className="mt-4 rounded-xl border border-border bg-background p-4 text-sm text-muted">
              <p>Price level: {selectedPriceLevel}</p>
              <p className="mt-1">Minimum-price validation and stock checks are enforced again by the Shop App when the sale is submitted.</p>
            </div>

            {saleError ? <p className="mt-3 text-sm text-danger">{saleError}</p> : null}

            {lastCompletedReceiptUuid ? (
              <div className="mt-3 rounded-xl border border-border bg-background p-3 text-xs text-muted">
                <p>Last receipt: {lastCompletedReceiptUuid}</p>
                <div className="mt-2 flex gap-2">
                  <Button variant="ghost" size="sm" onClick={() => void runReceiptPreview()}>Print preview</Button>
                  <Input className="max-w-[14rem]" label="Reprint reason" value={reprintReason} onChange={(event) => setReprintReason(event.target.value)} placeholder="Paper jam, faded print..." />
                  <Button variant="secondary" size="sm" onClick={() => void handleReprint()}>Reprint</Button>
                </div>
              </div>
            ) : null}

            <div className="mt-4 flex gap-2">
              <Button
                disabled={saleMutation.isPending || cartLines.length === 0 || Boolean(cartValidationErrors.length > 0) || !warehouseUuid}
                onClick={openPaymentsDialog}
              >
                {saleMutation.isPending ? 'Completing sale...' : 'Open payment dialog'}
              </Button>
              <Button variant="ghost" onClick={() => setCartLines([])}>Clear cart</Button>
            </div>
          </article>
        </aside>
      </div>

      <section className="mx-auto mt-6 max-w-7xl rounded-2xl border border-border bg-surface p-5 shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 className="text-lg font-semibold">Returns and cancellations</h2>
            <p className="mt-1 text-sm text-muted">Read-only sale lookup and controlled workflows. Original sales are never edited.</p>
          </div>
          <Button variant="secondary" onClick={() => void saleLookupQuery.refetch()} disabled={saleLookupQuery.isFetching}>
            {saleLookupQuery.isFetching ? 'Searching...' : 'Search sales'}
          </Button>
        </div>

        <div className="mt-4 grid gap-3 md:grid-cols-3 xl:grid-cols-6">
          <Input label="Sale number" value={lookupSaleNumber} onChange={(event) => setLookupSaleNumber(event.target.value)} />
          <Input label="Receipt number" value={lookupReceiptNumber} onChange={(event) => setLookupReceiptNumber(event.target.value)} />
          <Input label="Customer" value={lookupCustomer} onChange={(event) => setLookupCustomer(event.target.value)} />
          <Input label="Date" type="date" value={lookupDate} onChange={(event) => setLookupDate(event.target.value)} />
          <Input label="Cashier" value={lookupCashier} onChange={(event) => setLookupCashier(event.target.value)} />
          <Input label="Product" value={lookupProduct} onChange={(event) => setLookupProduct(event.target.value)} />
        </div>

        <div className="mt-4 grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
          <article className="rounded-xl border border-border bg-background p-3">
            <h3 className="text-sm font-semibold">Lookup results</h3>
            <div className="mt-2 max-h-[20rem] space-y-2 overflow-auto">
              {(saleLookupQuery.data ?? []).map((sale) => (
                <button
                  key={sale.uuid}
                  type="button"
                  className={`w-full rounded-lg border border-border px-3 py-2 text-left ${selectedLookupSaleUuid === sale.uuid ? 'bg-primary/10' : 'hover:bg-surface-muted'}`}
                  onClick={() => setSelectedLookupSaleUuid(sale.uuid)}
                >
                  <p className="text-sm font-semibold">{sale.saleNumber}</p>
                  <p className="text-xs text-muted">Receipt: {sale.receiptNumber ?? 'n/a'} · Customer: {sale.customerName ?? 'Walk-in'} · Cashier: {sale.cashierUsername ?? 'n/a'}</p>
                  <p className="text-xs text-muted">Date: {new Date(sale.createdAt).toLocaleString()} · Total: {sale.totalAmount}</p>
                </button>
              ))}
              {(saleLookupQuery.data ?? []).length === 0 ? <p className="text-sm text-muted">No matching sales found.</p> : null}
            </div>
          </article>

          <article className="rounded-xl border border-border bg-background p-3">
            <h3 className="text-sm font-semibold">Sale detail</h3>
            {selectedLookupSale ? (
              <div className="mt-2 space-y-3">
                <div className="grid gap-2 md:grid-cols-2">
                  <StatusBadge tone="information">Sale status: {selectedLookupSale.status}</StatusBadge>
                  <StatusBadge tone={selectedLookupSale.cancellation ? 'warning' : 'neutral'}>Cancellation: {selectedLookupSale.cancellation ? 'Yes' : 'No'}</StatusBadge>
                </div>
                {selectedLookupSale.cancellation ? (
                  <div className="rounded-lg border border-border px-3 py-2 text-xs text-muted">
                    <p>Cancellation reason: {selectedLookupSale.cancellation.reason}</p>
                    <p>Approval state: {selectedLookupSale.cancellation.approvalState}</p>
                    <p>Refund state: {selectedLookupSale.cancellation.refundState}</p>
                    <p>Refund method: {selectedLookupSale.cancellation.refundMethod ?? 'n/a'}</p>
                  </div>
                ) : null}
                <div className="rounded-lg border border-border px-3 py-2 text-xs text-muted">
                  <p>Returns: {selectedLookupSale.returnSummary.returnCount}</p>
                  <p>Refunded amount: {formatMoney(selectedLookupSale.returnSummary.refundAmount)}</p>
                  <p>Pending approvals: {selectedLookupSale.returnSummary.pendingApprovalCount}</p>
                </div>
                <div className="max-h-[12rem] space-y-2 overflow-auto rounded-lg border border-border p-2">
                  {selectedLookupSale.items.map((item) => {
                    const remaining = Math.max(Number(item.quantity) - Number(item.returnedQuantity), 0);
                    return (
                      <div key={item.uuid} className="rounded-md border border-border px-2 py-2 text-xs">
                        <p className="font-medium">{item.name} ({item.sku})</p>
                        <p className="text-muted">Sold: {item.quantity} · Returned: {item.returnedQuantity} · Remaining: {remaining}</p>
                        <Input
                          label="Return quantity"
                          value={returnItemQuantities[item.uuid] ?? '0'}
                          onChange={(event) => setReturnItemQuantities((current) => ({ ...current, [item.uuid]: event.target.value }))}
                          inputMode="decimal"
                        />
                      </div>
                    );
                  })}
                </div>
              </div>
            ) : <p className="mt-2 text-sm text-muted">Select a sale from lookup results.</p>}
          </article>
        </div>

        <div className="mt-4 grid gap-4 xl:grid-cols-2">
          <article className="rounded-xl border border-border bg-background p-3">
            <h3 className="text-sm font-semibold">Return workflow</h3>
            <div className="mt-2 grid gap-2 md:grid-cols-2">
              <Select
                label="Return type"
                value={returnType}
                onChange={(event) => setReturnType(event.target.value as 'full' | 'partial')}
                options={[{ value: 'partial', label: 'Partial return' }, { value: 'full', label: 'Full return' }]}
              />
              <Select
                label="Refund method"
                value={returnRefundMethod}
                onChange={(event) => setReturnRefundMethod(event.target.value as 'cash' | 'bank_transfer' | 'pos_terminal' | 'mobile_money' | 'credit_note')}
                options={[
                  { value: 'cash', label: 'Cash' },
                  { value: 'bank_transfer', label: 'Bank transfer' },
                  { value: 'pos_terminal', label: 'POS terminal' },
                  { value: 'mobile_money', label: 'Mobile money' },
                  { value: 'credit_note', label: 'Credit note' },
                ]}
              />
            </div>
            <Input className="mt-2" label="Return reason" value={returnReason} onChange={(event) => setReturnReason(event.target.value)} />
            <label className="mt-2 inline-flex items-center gap-2 text-sm">
              <input type="checkbox" checked={returnApprovalRequested} onChange={(event) => setReturnApprovalRequested(event.target.checked)} />
              Approval request
            </label>
            <label className="mt-2 inline-flex items-center gap-2 text-sm">
              <input type="checkbox" checked={printReturnReceiptAfterCreate} onChange={(event) => setPrintReturnReceiptAfterCreate(event.target.checked)} />
              Print return receipt
            </label>
            <div className="mt-3">
              <Button
                disabled={!selectedLookupSale || !returnReason.trim() || createReturnMutation.isPending}
                onClick={() => createReturnMutation.mutate()}
              >
                {createReturnMutation.isPending ? 'Creating return...' : 'Submit return'}
              </Button>
            </div>
          </article>

          <article className="rounded-xl border border-border bg-background p-3">
            <h3 className="text-sm font-semibold">Cancellation workflow</h3>
            <Select
              label="Refund method"
              value={cancellationRefundMethod}
              onChange={(event) => setCancellationRefundMethod(event.target.value as 'cash' | 'bank_transfer' | 'pos_terminal' | 'mobile_money' | 'credit_note')}
              options={[
                { value: 'cash', label: 'Cash' },
                { value: 'bank_transfer', label: 'Bank transfer' },
                { value: 'pos_terminal', label: 'POS terminal' },
                { value: 'mobile_money', label: 'Mobile money' },
                { value: 'credit_note', label: 'Credit note' },
              ]}
            />
            <Select
              className="mt-2"
              label="Refund state"
              value={cancellationRefundState}
              onChange={(event) => setCancellationRefundState(event.target.value as 'pending' | 'refunded')}
              options={[{ value: 'pending', label: 'Pending' }, { value: 'refunded', label: 'Refunded' }]}
            />
            <Input className="mt-2" label="Cancellation reason" value={cancellationReason} onChange={(event) => setCancellationReason(event.target.value)} />
            <label className="mt-2 inline-flex items-center gap-2 text-sm">
              <input type="checkbox" checked={cancellationApprovalRequested} onChange={(event) => setCancellationApprovalRequested(event.target.checked)} />
              Approval request
            </label>
            <div className="mt-3">
              <Button
                disabled={!selectedLookupSale || !cancellationReason.trim() || cancellationMutation.isPending || Boolean(selectedLookupSale?.cancellation)}
                onClick={() => cancellationMutation.mutate()}
              >
                {cancellationMutation.isPending ? 'Submitting cancellation...' : 'Submit cancellation'}
              </Button>
            </div>
          </article>
        </div>
      </section>

      {paymentsDialogOpen ? (
        <div className="fixed inset-0 z-40 grid place-items-center bg-black/50 p-4">
          <div className="w-full max-w-4xl rounded-2xl border border-border bg-surface p-5 shadow-2xl">
            <div className="flex items-center justify-between gap-3">
              <h2 className="text-lg font-semibold">Payment dialog</h2>
              <div className="flex gap-2">
                <Button variant="ghost" onClick={addPaymentLine}>Add method</Button>
                <Button variant="ghost" onClick={() => setPaymentsDialogOpen(false)}>Close</Button>
              </div>
            </div>

            <div className="mt-4 space-y-3">
              {paymentDrafts.map((payment) => {
                const methodOptions = paymentMethods.map((method) => ({ value: method.uuid, label: `${method.name} (${method.type})` }));
                return (
                  <div key={payment.id} className="rounded-xl border border-border bg-background p-3">
                    <div className="grid gap-3 md:grid-cols-3">
                      <Select
                        label="Payment method"
                        value={payment.paymentMethodUuid}
                        onChange={(event) => updatePaymentLine(payment.id, { paymentMethodUuid: event.target.value })}
                        options={methodOptions}
                      />
                      <Input label="Amount" value={payment.amount} onChange={(event) => updatePaymentLine(payment.id, { amount: event.target.value })} inputMode="decimal" />
                      <Input
                        label="Amount tendered"
                        value={payment.amountTendered}
                        onChange={(event) => updatePaymentLine(payment.id, { amountTendered: event.target.value })}
                        inputMode="decimal"
                        disabled={payment.paymentMethodType !== 'cash'}
                      />
                      <Input
                        label={payment.paymentMethodType === 'pos_terminal' ? 'POS reference' : payment.paymentMethodType === 'bank_transfer' ? 'Bank transfer reference' : payment.paymentMethodType === 'mobile_money' ? 'Mobile money reference' : 'Reference'}
                        value={payment.reference}
                        onChange={(event) => updatePaymentLine(payment.id, { reference: event.target.value })}
                        disabled={!['bank_transfer', 'pos_terminal', 'mobile_money'].includes(payment.paymentMethodType)}
                      />
                      <Input
                        label="Credit amount"
                        value={payment.creditAmount}
                        onChange={(event) => updatePaymentLine(payment.id, { creditAmount: event.target.value })}
                        inputMode="decimal"
                        disabled={payment.paymentMethodType !== 'credit'}
                      />
                      <Input
                        label="Customer credit selection"
                        value={payment.customerCreditSelection}
                        onChange={(event) => updatePaymentLine(payment.id, { customerCreditSelection: event.target.value })}
                        disabled={payment.paymentMethodType !== 'credit'}
                      />
                    </div>
                    {['bank_transfer', 'mobile_money'].includes(payment.paymentMethodType) ? (
                      <label className="mt-2 inline-flex items-center gap-2 text-sm">
                        <input
                          type="checkbox"
                          checked={payment.transferVerified}
                          onChange={(event) => updatePaymentLine(payment.id, { transferVerified: event.target.checked })}
                        />
                        Transfer verified
                      </label>
                    ) : null}
                    <div className="mt-2">
                      <Button variant="ghost" size="sm" onClick={() => removePaymentLine(payment.id)} disabled={paymentDrafts.length === 1}>Remove method</Button>
                    </div>
                  </div>
                );
              })}
            </div>

            <div className="mt-4 rounded-xl border border-border bg-background p-4 text-sm">
              <Row label="Grand total" value={formatMoney(grandTotal)} emphasis />
              <Row label="Paid" value={formatMoney(totalPaidByDrafts)} />
              <Row label="Change due" value={formatMoney(changeDue)} />
              <Row label="Balance" value={formatMoney(balanceDue)} />
              {requiresCustomerForCredit && !selectedCustomerUuid ? <p className="mt-2 text-danger">Credit payment requires selecting a customer.</p> : null}
              {creditRequiresEligibility ? (
                <div className="mt-3 rounded-lg border border-border p-3 text-xs">
                  <Input label="Credit due date" type="date" value={creditDueDate} onChange={(event) => setCreditDueDate(event.target.value)} />
                  <p className="mt-2">Requested credit: {formatMoney(totalCreditRequested)}</p>
                  <p>Existing balance: {formatMoney(Number(creditEligibilityQuery.data?.existingBalance ?? '0'))}</p>
                  <p>Available credit: {formatMoney(Number(creditEligibilityQuery.data?.availableCredit ?? '0'))}</p>
                  <p>Credit limit: {formatMoney(Number(creditEligibilityQuery.data?.creditLimit ?? '0'))}</p>
                  <p>Due date: {creditEligibilityQuery.data?.dueDate ?? creditDueDate || 'n/a'}</p>
                  <p>Approval requirement: {creditEligibilityQuery.data?.requiresApproval ? 'Required' : 'Not required'}</p>
                  {creditEligibilityQuery.isLoading ? <p className="text-muted">Checking Shop App credit eligibility...</p> : null}
                  {creditEligibilityQuery.data && !creditEligibilityQuery.data.eligible ? <p className="text-danger">{creditEligibilityQuery.data.reason ?? 'Credit sale not eligible.'}</p> : null}
                </div>
              ) : null}
              {paymentValidationErrors.length > 0 ? <p className="mt-2 text-danger">{paymentValidationErrors[0]}</p> : null}
            </div>

            <div className="mt-4 flex items-center justify-end gap-2">
              <Button variant="ghost" onClick={() => setPaymentsDialogOpen(false)}>Cancel</Button>
              <Button
                disabled={saleMutation.isPending || cartLines.length === 0 || paymentValidationErrors.length > 0 || (requiresCustomerForCredit && !selectedCustomerUuid) || creditEligibilityBlocked}
                onClick={() => saleMutation.mutate()}
              >
                {saleMutation.isPending ? 'Waiting for Shop App confirmation...' : 'Submit sale'}
              </Button>
            </div>
          </div>
        </div>
      ) : null}
    </section>
  );
}

function Row(props: { label: string; value: string; emphasis?: boolean }) {
  return (
    <div className="flex items-center justify-between gap-3 rounded-lg border border-border px-3 py-2">
      <span className="text-muted">{props.label}</span>
      <span className={props.emphasis ? 'text-base font-semibold text-foreground' : 'font-medium text-foreground'}>{props.value}</span>
    </div>
  );
}

function SuggestionList(props: { title: string; items: PosProduct[]; onPick: (product: PosProduct) => void }) {
  return (
    <section className="rounded-xl border border-border bg-background p-3">
      <h3 className="text-sm font-semibold">{props.title}</h3>
      <div className="mt-2 space-y-2">
        {props.items.length > 0 ? props.items.map((product) => (
          <button key={product.uuid} type="button" className="w-full rounded-lg border border-border px-3 py-2 text-left text-sm hover:bg-surface-muted" onClick={() => props.onPick(product)}>
            <p className="font-medium">{product.name}</p>
            <p className="mt-1 text-xs text-muted">{product.sku} · {product.categoryName ?? 'No category'}</p>
          </button>
        )) : <p className="text-sm text-muted">No suggestions.</p>}
      </div>
    </section>
  );
}

function useDebouncedValue<T>(value: T, delayMs: number): T {
  const [debouncedValue, setDebouncedValue] = useState(value);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedValue(value), delayMs);
    return () => window.clearTimeout(timer);
  }, [delayMs, value]);

  return debouncedValue;
}

function formatMoneyRaw(value: number): string {
  return Number.isFinite(value) ? value.toFixed(2) : '0.00';
}

function asPaymentMode(type: string): PaymentMode {
  if (type === 'cash' || type === 'bank_transfer' || type === 'pos_terminal' || type === 'mobile_money' || type === 'credit') {
    return type;
  }

  return 'cash';
}

function createPaymentDraft(paymentMethodUuid: string, paymentMethodName: string, paymentMethodType: PaymentMode, amount = '0.00'): PosPaymentDraft {
  return {
    id: crypto.randomUUID(),
    paymentMethodUuid,
    paymentMethodName,
    paymentMethodType,
    amount,
    amountTendered: paymentMethodType === 'cash' ? amount : '',
    reference: '',
    transferVerified: false,
    creditAmount: paymentMethodType === 'credit' ? amount : '0.00',
    customerCreditSelection: '',
  };
}

function validatePaymentDrafts(paymentDrafts: PosPaymentDraft[], grandTotal: number, hasCustomer: boolean): string[] {
  if (paymentDrafts.length === 0) {
    return ['At least one payment method is required.'];
  }

  const errors: string[] = [];
  let paid = 0;

  for (const payment of paymentDrafts) {
    const amount = parseMoney(payment.amount);
    if (!Number.isFinite(amount) || amount < 0) {
      errors.push('Payment amount must be zero or greater.');
      break;
    }
    paid += amount;

    if (payment.paymentMethodType === 'cash') {
      const tendered = parseMoney(payment.amountTendered || '0');
      if (!Number.isFinite(tendered) || tendered < amount) {
        errors.push('Cash amount tendered must be at least the cash payment amount.');
        break;
      }
    }

    if (payment.paymentMethodType === 'bank_transfer' || payment.paymentMethodType === 'mobile_money') {
      if (!payment.reference.trim()) {
        errors.push('Transfer and mobile money payments require a reference.');
        break;
      }
      if (!payment.transferVerified) {
        errors.push('Transfer and mobile money payments must be verified before completion.');
        break;
      }
    }

    if (payment.paymentMethodType === 'pos_terminal' && !payment.reference.trim()) {
      errors.push('POS terminal payments require a POS reference.');
      break;
    }

    if (payment.paymentMethodType === 'credit') {
      if (!hasCustomer) {
        errors.push('Credit payment requires selecting a customer.');
        break;
      }
      const creditAmount = parseMoney(payment.creditAmount || '0');
      if (!Number.isFinite(creditAmount) || creditAmount <= 0) {
        errors.push('Credit amount must be greater than zero for credit payments.');
        break;
      }
    }
  }

  if (Math.abs(paid - grandTotal) > 0.009) {
    errors.push('Total paid across payment methods must equal the sale total.');
  }

  return errors;
}