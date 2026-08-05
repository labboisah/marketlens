import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Button, Input, Select, StatusBadge } from '@marketlens/ui';
import { useMemo, useState } from 'react';
import { useClientUiStore } from '../state/client-ui-store';
import {
  createCustomerAccountProfile,
  fetchCreditSaleEligibility,
  fetchCustomerDebtProfile,
  fetchCustomerStatement,
  recordCustomerPayment,
  searchCustomersForAccounts,
  updateCustomerAccountProfile,
  type CustomerDebtProfile,
} from '../services/customer-account-service';

function formatMoney(value: string): string {
  const numeric = Number(value || '0');
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number.isFinite(numeric) ? numeric : 0);
}

function hasAnyPermission(granted: string[], required: string[]): boolean {
  return required.some((permission) => granted.includes(permission));
}

export function CustomersScreen() {
  const queryClient = useQueryClient();
  const deviceUuid = useClientUiStore((state) => state.deviceUuid);
  const connectionState = useClientUiStore((state) => state.connectionState);
  const permissions = useClientUiStore((state) => state.permissions);
  const notify = useClientUiStore((state) => state.notify);

  const canCreateOrEdit = hasAnyPermission(permissions, ['customers.manage']);

  const [search, setSearch] = useState('');
  const [selectedCustomerUuid, setSelectedCustomerUuid] = useState('');

  const [newCode, setNewCode] = useState('');
  const [newName, setNewName] = useState('');
  const [newType, setNewType] = useState<'retail' | 'wholesale' | 'corporate'>('retail');
  const [newPhone, setNewPhone] = useState('');
  const [newAddress, setNewAddress] = useState('');
  const [newCreditLimit, setNewCreditLimit] = useState('0');
  const [newOpeningBalance, setNewOpeningBalance] = useState('0');

  const [editName, setEditName] = useState('');
  const [editType, setEditType] = useState<'retail' | 'wholesale' | 'corporate'>('retail');
  const [editPhone, setEditPhone] = useState('');
  const [editAddress, setEditAddress] = useState('');

  const [paymentAmount, setPaymentAmount] = useState('0');
  const [paymentReference, setPaymentReference] = useState('');
  const [paymentMethod, setPaymentMethod] = useState('cash');
  const [paymentAllocations, setPaymentAllocations] = useState<Record<string, string>>({});

  const [creditDueDate, setCreditDueDate] = useState('');
  const [creditRequestedAmount, setCreditRequestedAmount] = useState('0');

  const customersQuery = useQuery({
    queryKey: ['customer-accounts-list', search],
    queryFn: async () => {
      const session = await window.marketlensClient.session.load();
      if (!session || !deviceUuid) {
        throw new Error('Session not available.');
      }
      return searchCustomersForAccounts({
        shopBaseUrl: session.shopBaseUrl,
        accessToken: session.token,
        deviceUuid,
        search,
      });
    },
    enabled: Boolean(deviceUuid && connectionState === 'connected'),
    staleTime: 20_000,
    refetchOnWindowFocus: false,
  });

  const debtProfileQuery = useQuery({
    queryKey: ['customer-debt-profile', selectedCustomerUuid],
    queryFn: async () => {
      const session = await window.marketlensClient.session.load();
      if (!session || !deviceUuid || !selectedCustomerUuid) {
        throw new Error('Customer is required.');
      }
      return fetchCustomerDebtProfile({
        shopBaseUrl: session.shopBaseUrl,
        accessToken: session.token,
        deviceUuid,
        customerUuid: selectedCustomerUuid,
      });
    },
    enabled: Boolean(deviceUuid && selectedCustomerUuid),
    staleTime: 10_000,
    refetchOnWindowFocus: false,
  });

  const statementQuery = useQuery({
    queryKey: ['customer-statement', selectedCustomerUuid],
    queryFn: async () => {
      const session = await window.marketlensClient.session.load();
      if (!session || !deviceUuid || !selectedCustomerUuid) {
        throw new Error('Customer is required.');
      }
      return fetchCustomerStatement({
        shopBaseUrl: session.shopBaseUrl,
        accessToken: session.token,
        deviceUuid,
        customerUuid: selectedCustomerUuid,
      });
    },
    enabled: Boolean(deviceUuid && selectedCustomerUuid),
    staleTime: 10_000,
    refetchOnWindowFocus: false,
  });

  const selectedCustomer = useMemo(() => {
    return (customersQuery.data ?? []).find((customer) => customer.uuid === selectedCustomerUuid);
  }, [customersQuery.data, selectedCustomerUuid]);

  const createMutation = useMutation({
    mutationFn: async () => {
      const session = await window.marketlensClient.session.load();
      if (!session || !deviceUuid) throw new Error('Session not available.');
      return createCustomerAccountProfile({
        shopBaseUrl: session.shopBaseUrl,
        accessToken: session.token,
        deviceUuid,
        code: newCode,
        name: newName,
        type: newType,
        phone: newPhone || undefined,
        address: newAddress || undefined,
        creditLimit: newCreditLimit || '0',
        openingBalance: newOpeningBalance || '0',
      });
    },
    onSuccess: async (customer) => {
      notify({ tone: 'success', title: 'Customer created', message: 'Shop App created the customer profile.' });
      setNewCode('');
      setNewName('');
      setNewType('retail');
      setNewPhone('');
      setNewAddress('');
      setNewCreditLimit('0');
      setNewOpeningBalance('0');
      setSelectedCustomerUuid(customer.uuid);
      await queryClient.invalidateQueries({ queryKey: ['customer-accounts-list'] });
    },
    onError: (error) => {
      notify({ tone: 'danger', title: 'Create failed', message: error instanceof Error ? error.message : 'Unable to create customer.' });
    },
  });

  const updateMutation = useMutation({
    mutationFn: async () => {
      const session = await window.marketlensClient.session.load();
      if (!session || !deviceUuid || !selectedCustomerUuid) throw new Error('Select a customer first.');
      return updateCustomerAccountProfile({
        shopBaseUrl: session.shopBaseUrl,
        accessToken: session.token,
        deviceUuid,
        customerUuid: selectedCustomerUuid,
        name: editName,
        type: editType,
        phone: editPhone,
        address: editAddress,
      });
    },
    onSuccess: async () => {
      notify({ tone: 'success', title: 'Customer updated', message: 'Shop App saved customer changes.' });
      await queryClient.invalidateQueries({ queryKey: ['customer-accounts-list'] });
      await queryClient.invalidateQueries({ queryKey: ['customer-debt-profile', selectedCustomerUuid] });
    },
    onError: (error) => {
      notify({ tone: 'danger', title: 'Update failed', message: error instanceof Error ? error.message : 'Unable to update customer.' });
    },
  });

  const paymentMutation = useMutation({
    mutationFn: async () => {
      const session = await window.marketlensClient.session.load();
      if (!session || !deviceUuid || !selectedCustomerUuid) throw new Error('Select a customer first.');
      const allocations = Object.entries(paymentAllocations)
        .map(([accountTransactionUuid, amount]) => ({ accountTransactionUuid, amount }))
        .filter((allocation) => Number(allocation.amount) > 0);
      return recordCustomerPayment({
        shopBaseUrl: session.shopBaseUrl,
        accessToken: session.token,
        deviceUuid,
        customerUuid: selectedCustomerUuid,
        amount: paymentAmount,
        reference: paymentReference || undefined,
        paymentMethod,
        allocations,
      });
    },
    onSuccess: async () => {
      notify({ tone: 'success', title: 'Payment recorded', message: 'Shop App recorded customer payment and allocations.' });
      setPaymentAmount('0');
      setPaymentReference('');
      setPaymentAllocations({});
      await queryClient.invalidateQueries({ queryKey: ['customer-debt-profile', selectedCustomerUuid] });
      await queryClient.invalidateQueries({ queryKey: ['customer-statement', selectedCustomerUuid] });
    },
    onError: (error) => {
      notify({ tone: 'danger', title: 'Payment failed', message: error instanceof Error ? error.message : 'Unable to record payment.' });
    },
  });

  const creditEligibilityQuery = useQuery({
    queryKey: ['customer-credit-eligibility', selectedCustomerUuid, creditRequestedAmount, creditDueDate],
    queryFn: async () => {
      const session = await window.marketlensClient.session.load();
      if (!session || !deviceUuid || !selectedCustomerUuid || !creditDueDate) {
        throw new Error('Customer, amount and due date are required.');
      }
      return fetchCreditSaleEligibility({
        shopBaseUrl: session.shopBaseUrl,
        accessToken: session.token,
        deviceUuid,
        customerUuid: selectedCustomerUuid,
        requestedAmount: creditRequestedAmount,
        dueDate: creditDueDate,
      });
    },
    enabled: false,
    staleTime: 0,
    refetchOnWindowFocus: false,
  });

  const profile = debtProfileQuery.data;

  return (
    <section className="min-h-screen bg-background p-6 text-foreground">
      <div className="mx-auto max-w-7xl space-y-5">
        <header className="rounded-2xl border border-border bg-surface p-5 shadow-sm">
          <h1 className="text-2xl font-semibold">Customers and debt</h1>
          <p className="mt-1 text-sm text-muted">All balances, limits, overdue amounts, and eligibility are sourced from Shop App responses.</p>
        </header>

        <div className="grid gap-5 xl:grid-cols-[340px_minmax(0,1fr)]">
          <aside className="rounded-2xl border border-border bg-surface p-4 shadow-sm">
            <Input label="Customer search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search by code, name, or phone" />
            <div className="mt-3 max-h-[34rem] overflow-auto rounded-xl border border-border bg-background">
              {(customersQuery.data ?? []).map((customer) => (
                <button
                  key={customer.uuid}
                  type="button"
                  className={`block w-full border-b border-border px-3 py-3 text-left ${selectedCustomerUuid === customer.uuid ? 'bg-primary/10' : 'hover:bg-surface-muted'}`}
                  onClick={() => {
                    setSelectedCustomerUuid(customer.uuid);
                    setEditName(customer.name);
                    setEditType((customer.type as 'retail' | 'wholesale' | 'corporate') ?? 'retail');
                    setEditPhone(customer.phone ?? '');
                    setEditAddress(customer.address ?? '');
                  }}
                >
                  <p className="text-sm font-semibold">{customer.name}</p>
                  <p className="text-xs text-muted">{customer.code} · {customer.type} · {customer.phone ?? 'No phone'}</p>
                </button>
              ))}
            </div>
          </aside>

          <main className="space-y-5">
            <section className="rounded-2xl border border-border bg-surface p-4 shadow-sm">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-lg font-semibold">Account overview</h2>
                {selectedCustomer ? <StatusBadge tone="information">{selectedCustomer.code}</StatusBadge> : null}
              </div>
              {profile ? <DebtProfileSummary profile={profile} /> : <p className="mt-2 text-sm text-muted">Select a customer to view account details.</p>}
            </section>

            {canCreateOrEdit ? (
              <section className="grid gap-5 xl:grid-cols-2">
                <article className="rounded-2xl border border-border bg-surface p-4 shadow-sm">
                  <h3 className="text-base font-semibold">Create customer</h3>
                  <div className="mt-3 grid gap-2">
                    <Input label="Code" value={newCode} onChange={(event) => setNewCode(event.target.value)} />
                    <Input label="Name" value={newName} onChange={(event) => setNewName(event.target.value)} />
                    <Select
                      label="Type"
                      value={newType}
                      onChange={(event) => setNewType(event.target.value as 'retail' | 'wholesale' | 'corporate')}
                      options={[
                        { value: 'retail', label: 'Retail' },
                        { value: 'wholesale', label: 'Wholesale' },
                        { value: 'corporate', label: 'Corporate' },
                      ]}
                    />
                    <Input label="Phone" value={newPhone} onChange={(event) => setNewPhone(event.target.value)} />
                    <Input label="Address" value={newAddress} onChange={(event) => setNewAddress(event.target.value)} />
                    <Input label="Credit limit" value={newCreditLimit} onChange={(event) => setNewCreditLimit(event.target.value)} inputMode="decimal" />
                    <Input label="Opening balance" value={newOpeningBalance} onChange={(event) => setNewOpeningBalance(event.target.value)} inputMode="decimal" />
                    <Button disabled={!newCode || !newName || createMutation.isPending} onClick={() => createMutation.mutate()}>
                      {createMutation.isPending ? 'Creating...' : 'Create customer'}
                    </Button>
                  </div>
                </article>

                <article className="rounded-2xl border border-border bg-surface p-4 shadow-sm">
                  <h3 className="text-base font-semibold">Edit customer</h3>
                  <div className="mt-3 grid gap-2">
                    <Input label="Name" value={editName} onChange={(event) => setEditName(event.target.value)} disabled={!selectedCustomerUuid} />
                    <Select
                      label="Type"
                      value={editType}
                      onChange={(event) => setEditType(event.target.value as 'retail' | 'wholesale' | 'corporate')}
                      options={[
                        { value: 'retail', label: 'Retail' },
                        { value: 'wholesale', label: 'Wholesale' },
                        { value: 'corporate', label: 'Corporate' },
                      ]}
                    />
                    <Input label="Phone" value={editPhone} onChange={(event) => setEditPhone(event.target.value)} disabled={!selectedCustomerUuid} />
                    <Input label="Address" value={editAddress} onChange={(event) => setEditAddress(event.target.value)} disabled={!selectedCustomerUuid} />
                    <Button disabled={!selectedCustomerUuid || updateMutation.isPending} onClick={() => updateMutation.mutate()}>
                      {updateMutation.isPending ? 'Saving...' : 'Save changes'}
                    </Button>
                  </div>
                </article>
              </section>
            ) : null}

            <section className="grid gap-5 xl:grid-cols-2">
              <article className="rounded-2xl border border-border bg-surface p-4 shadow-sm">
                <h3 className="text-base font-semibold">Customer payment and allocation</h3>
                <div className="mt-3 grid gap-2">
                  <Input label="Amount" value={paymentAmount} onChange={(event) => setPaymentAmount(event.target.value)} inputMode="decimal" disabled={!selectedCustomerUuid} />
                  <Input label="Reference" value={paymentReference} onChange={(event) => setPaymentReference(event.target.value)} disabled={!selectedCustomerUuid} />
                  <Select
                    label="Payment method"
                    value={paymentMethod}
                    onChange={(event) => setPaymentMethod(event.target.value)}
                    options={[
                      { value: 'cash', label: 'Cash' },
                      { value: 'bank_transfer', label: 'Bank transfer' },
                      { value: 'pos_terminal', label: 'POS terminal' },
                      { value: 'mobile_money', label: 'Mobile money' },
                    ]}
                  />
                </div>

                <div className="mt-3 rounded-xl border border-border bg-background p-3">
                  <p className="text-sm font-medium">Payment allocation</p>
                  <p className="text-xs text-muted">Allocate against open credit sales (Shop App validates allocations).</p>
                  <div className="mt-2 max-h-48 space-y-2 overflow-auto">
                    {(profile?.openCreditSales ?? []).map((sale) => (
                      <div key={sale.uuid} className="rounded-lg border border-border px-2 py-2 text-xs">
                        <p>Ref: {sale.reference ?? sale.uuid.slice(0, 8)}</p>
                        <p>Due: {sale.dueDate ?? 'n/a'} · Outstanding: {formatMoney(sale.outstandingAmount)}</p>
                        <Input
                          label="Allocate"
                          value={paymentAllocations[sale.uuid] ?? ''}
                          onChange={(event) => setPaymentAllocations((current) => ({ ...current, [sale.uuid]: event.target.value }))}
                          inputMode="decimal"
                          disabled={!selectedCustomerUuid}
                        />
                      </div>
                    ))}
                    {(profile?.openCreditSales ?? []).length === 0 ? <p className="text-xs text-muted">No open credit sales.</p> : null}
                  </div>
                </div>

                <div className="mt-3">
                  <Button disabled={!selectedCustomerUuid || paymentMutation.isPending} onClick={() => paymentMutation.mutate()}>
                    {paymentMutation.isPending ? 'Recording payment...' : 'Record payment'}
                  </Button>
                </div>
              </article>

              <article className="rounded-2xl border border-border bg-surface p-4 shadow-sm">
                <h3 className="text-base font-semibold">Credit-sale eligibility</h3>
                <div className="mt-3 grid gap-2">
                  <Input label="Requested amount" value={creditRequestedAmount} onChange={(event) => setCreditRequestedAmount(event.target.value)} inputMode="decimal" disabled={!selectedCustomerUuid} />
                  <Input label="Due date" type="date" value={creditDueDate} onChange={(event) => setCreditDueDate(event.target.value)} disabled={!selectedCustomerUuid} />
                  <Button disabled={!selectedCustomerUuid || !creditDueDate || creditEligibilityQuery.isFetching} onClick={() => void creditEligibilityQuery.refetch()}>
                    {creditEligibilityQuery.isFetching ? 'Checking...' : 'Check eligibility'}
                  </Button>
                </div>
                {creditEligibilityQuery.data ? (
                  <div className="mt-3 rounded-xl border border-border bg-background p-3 text-sm">
                    <p>Existing balance: {formatMoney(creditEligibilityQuery.data.existingBalance)}</p>
                    <p>Available credit: {formatMoney(creditEligibilityQuery.data.availableCredit)}</p>
                    <p>Credit limit: {formatMoney(creditEligibilityQuery.data.creditLimit)}</p>
                    <p>Due date: {creditEligibilityQuery.data.dueDate}</p>
                    <p>Approval requirement: {creditEligibilityQuery.data.requiresApproval ? 'Required' : 'Not required'}</p>
                    <p>Eligibility: {creditEligibilityQuery.data.eligible ? 'Eligible' : 'Not eligible'}</p>
                    {creditEligibilityQuery.data.reason ? <p className="text-xs text-warning">Reason: {creditEligibilityQuery.data.reason}</p> : null}
                  </div>
                ) : null}
              </article>
            </section>

            <section className="grid gap-5 xl:grid-cols-2">
              <article className="rounded-2xl border border-border bg-surface p-4 shadow-sm">
                <h3 className="text-base font-semibold">Payment history</h3>
                <div className="mt-3 max-h-72 space-y-2 overflow-auto">
                  {(profile?.paymentHistory ?? []).map((payment) => (
                    <div key={payment.uuid} className="rounded-lg border border-border bg-background px-3 py-2 text-xs">
                      <p className="font-medium">{formatMoney(payment.amount)} · {payment.paymentMethod ?? 'n/a'}</p>
                      <p className="text-muted">Allocated: {formatMoney(payment.allocatedAmount)} · Unallocated: {formatMoney(payment.unallocatedAmount)}</p>
                      <p className="text-muted">Ref: {payment.reference ?? 'n/a'} · {new Date(payment.createdAt).toLocaleString()}</p>
                    </div>
                  ))}
                  {(profile?.paymentHistory ?? []).length === 0 ? <p className="text-sm text-muted">No payment history.</p> : null}
                </div>
              </article>

              <article className="rounded-2xl border border-border bg-surface p-4 shadow-sm">
                <h3 className="text-base font-semibold">Customer statement</h3>
                <div className="mt-3 max-h-72 space-y-2 overflow-auto">
                  {(statementQuery.data?.transactions ?? []).map((transaction) => (
                    <div key={transaction.uuid} className="rounded-lg border border-border bg-background px-3 py-2 text-xs">
                      <p className="font-medium">{transaction.transactionType} · {formatMoney(transaction.amount)}</p>
                      <p className="text-muted">Due: {transaction.dueDate ?? 'n/a'} · Ref: {transaction.reference ?? 'n/a'}</p>
                      <p className="text-muted">{new Date(transaction.createdAt).toLocaleString()}</p>
                    </div>
                  ))}
                  {(statementQuery.data?.transactions ?? []).length === 0 ? <p className="text-sm text-muted">No statement entries.</p> : null}
                </div>
              </article>
            </section>
          </main>
        </div>
      </div>
    </section>
  );
}

function DebtProfileSummary({ profile }: { profile: CustomerDebtProfile }) {
  return (
    <div className="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
      <Metric title="Current balance" value={formatMoney(profile.account.currentBalance)} tone="information" />
      <Metric title="Credit limit" value={formatMoney(profile.account.creditLimit)} tone="neutral" />
      <Metric title="Available credit" value={formatMoney(profile.account.availableCredit)} tone="success" />
      <Metric title="Overdue amount" value={formatMoney(profile.account.overdueAmount)} tone={profile.account.hasOverdueBalance ? 'warning' : 'success'} />
      <Metric title="Credit-sale eligibility" value={profile.account.hasOverdueBalance ? 'Restricted' : 'Open'} tone={profile.account.hasOverdueBalance ? 'warning' : 'success'} />
    </div>
  );
}

function Metric(props: { title: string; value: string; tone: 'success' | 'warning' | 'danger' | 'information' | 'neutral' }) {
  return (
    <div className="rounded-xl border border-border bg-background p-3">
      <div className="flex items-center justify-between gap-2">
        <p className="text-xs text-muted">{props.title}</p>
        <StatusBadge tone={props.tone}>{props.tone}</StatusBadge>
      </div>
      <p className="mt-2 text-sm font-semibold">{props.value}</p>
    </div>
  );
}
