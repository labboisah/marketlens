import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { AlertCircle, Check, FileText, RefreshCcw, X } from 'lucide-react';
import { useWorkspaceShellStore } from '../state/workspace-shell-store';
import {
  approveExpense,
  createExpense,
  createExpenseCategory,
  listBankAccounts,
  listCashAccounts,
  listExpenseCategories,
  listExpenses,
  listExpenseSummaryByCategory,
  listExpenseSummaryByCreator,
  listExpenseSummaryByDate,
  rejectExpense,
  requestExpenseReversal,
  type ExpenseRow,
} from '../services/expenses-service';

type ExpenseStatusFilter = 'pending_approval' | 'approved' | 'rejected';
type PaymentMethod = 'cash' | 'bank';

const expenseQueryKey = {
  categories: ['expense-categories'] as const,
  cashAccounts: ['expense-cash-accounts'] as const,
  bankAccounts: ['expense-bank-accounts'] as const,
  expenses: (status: ExpenseStatusFilter, categoryUuid: string, from: string, to: string) =>
    ['expenses', status, categoryUuid, from, to] as const,
  byCategory: (from: string, to: string) => ['expense-summary-by-category', from, to] as const,
  byDate: (from: string, to: string) => ['expense-summary-by-date', from, to] as const,
  byCreator: (from: string, to: string) => ['expense-summary-by-creator', from, to] as const,
};

function toTwoDecimals(value: string) {
  const numeric = Number(value);
  if (!Number.isFinite(numeric) || numeric <= 0) {
    return null;
  }
  return numeric.toFixed(2);
}

function formatMoney(value: string | number) {
  const numeric = typeof value === 'string' ? Number(value) : value;
  if (!Number.isFinite(numeric)) return value;
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(numeric);
}

function formatDate(value: string) {
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return value;
  return parsed.toLocaleString();
}

export function ExpensesScreen() {
  const queryClient = useQueryClient();
  const activeWorkspace = useWorkspaceShellStore((state) => state.activeWorkspace);
  const activeSession = useWorkspaceShellStore((state) => state.activeSession);

  const [statusFilter, setStatusFilter] = useState<ExpenseStatusFilter>('pending_approval');
  const [categoryFilter, setCategoryFilter] = useState('all');
  const [fromDate, setFromDate] = useState('');
  const [toDate, setToDate] = useState('');

  const [expenseCategoryUuid, setExpenseCategoryUuid] = useState('');
  const [description, setDescription] = useState('');
  const [amount, setAmount] = useState('');
  const [paymentMethod, setPaymentMethod] = useState<PaymentMethod>('cash');
  const [cashAccountUuid, setCashAccountUuid] = useState('');
  const [bankAccountUuid, setBankAccountUuid] = useState('');
  const [shiftUuid, setShiftUuid] = useState('');
  const [receiptAttachmentPath, setReceiptAttachmentPath] = useState('');
  const [approvalRequested, setApprovalRequested] = useState(false);

  const [newCategoryName, setNewCategoryName] = useState('');
  const [newCategoryRequiresApproval, setNewCategoryRequiresApproval] = useState(true);

  const [reversalExpenseUuid, setReversalExpenseUuid] = useState('');
  const [reversalReason, setReversalReason] = useState('');
  const [rejectionReason, setRejectionReason] = useState('');

  const canQuery = Boolean(activeWorkspace?.shopApiBaseUrl && activeSession?.accessToken && activeSession.deviceUuid);

  const authContext = useMemo(() => {
    if (!activeWorkspace || !activeSession) return null;
    return {
      shopBaseUrl: activeWorkspace.shopApiBaseUrl,
      accessToken: activeSession.accessToken,
      deviceUuid: activeSession.deviceUuid,
    };
  }, [activeWorkspace, activeSession]);

  const categoriesQuery = useQuery({
    queryKey: expenseQueryKey.categories,
    enabled: canQuery && Boolean(authContext),
    queryFn: async () => listExpenseCategories(authContext!),
  });

  const cashAccountsQuery = useQuery({
    queryKey: expenseQueryKey.cashAccounts,
    enabled: canQuery && Boolean(authContext),
    queryFn: async () => listCashAccounts(authContext!),
  });

  const bankAccountsQuery = useQuery({
    queryKey: expenseQueryKey.bankAccounts,
    enabled: canQuery && Boolean(authContext),
    queryFn: async () => listBankAccounts(authContext!),
  });

  const expensesQuery = useQuery({
    queryKey: expenseQueryKey.expenses(statusFilter, categoryFilter, fromDate, toDate),
    enabled: canQuery && Boolean(authContext),
    queryFn: async () => listExpenses({
      ...authContext!,
      status: statusFilter,
      categoryUuid: categoryFilter === 'all' ? undefined : categoryFilter,
      from: fromDate || undefined,
      to: toDate || undefined,
    }),
  });

  const byCategoryQuery = useQuery({
    queryKey: expenseQueryKey.byCategory(fromDate, toDate),
    enabled: canQuery && Boolean(authContext),
    queryFn: async () => listExpenseSummaryByCategory({ ...authContext!, from: fromDate || undefined, to: toDate || undefined }),
  });

  const byDateQuery = useQuery({
    queryKey: expenseQueryKey.byDate(fromDate, toDate),
    enabled: canQuery && Boolean(authContext),
    queryFn: async () => listExpenseSummaryByDate({ ...authContext!, from: fromDate || undefined, to: toDate || undefined }),
  });

  const byCreatorQuery = useQuery({
    queryKey: expenseQueryKey.byCreator(fromDate, toDate),
    enabled: canQuery && Boolean(authContext),
    queryFn: async () => listExpenseSummaryByCreator({ ...authContext!, from: fromDate || undefined, to: toDate || undefined }),
  });

  const createCategoryMutation = useMutation({
    mutationFn: async () => {
      if (!authContext) throw new Error('Missing active workspace session');
      if (!newCategoryName.trim()) throw new Error('Category name is required');
      return createExpenseCategory({
        ...authContext,
        name: newCategoryName.trim(),
        requiresApproval: newCategoryRequiresApproval,
      });
    },
    onSuccess: async () => {
      setNewCategoryName('');
      await queryClient.invalidateQueries({ queryKey: expenseQueryKey.categories });
    },
  });

  const createExpenseMutation = useMutation({
    mutationFn: async () => {
      if (!authContext || !activeSession) throw new Error('Missing active workspace session');
      if (!expenseCategoryUuid) throw new Error('Expense category is required');
      const normalizedAmount = toTwoDecimals(amount);
      if (!normalizedAmount) throw new Error('Amount must be greater than 0');
      if (!description.trim()) throw new Error('Description is required');

      if (paymentMethod === 'cash' && !cashAccountUuid) throw new Error('Cash account is required for cash expenses');
      if (paymentMethod === 'bank' && !bankAccountUuid) throw new Error('Bank account is required for bank expenses');

      return createExpense({
        ...authContext,
        categoryUuid: expenseCategoryUuid,
        amount: normalizedAmount,
        paymentMethod,
        description: description.trim(),
        cashAccountUuid: paymentMethod === 'cash' ? cashAccountUuid : undefined,
        bankAccountUuid: paymentMethod === 'bank' ? bankAccountUuid : undefined,
        shiftUuid: shiftUuid.trim() || undefined,
        receiptAttachmentPath: receiptAttachmentPath.trim() || undefined,
        approvalRequested,
        createdByUserUuid: activeSession.userUuid,
        createdByName: activeSession.displayName,
      });
    },
    onSuccess: async () => {
      setDescription('');
      setAmount('');
      setReceiptAttachmentPath('');
      setShiftUuid('');
      setApprovalRequested(false);
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: expenseQueryKey.expenses(statusFilter, categoryFilter, fromDate, toDate) }),
        queryClient.invalidateQueries({ queryKey: expenseQueryKey.byCategory(fromDate, toDate) }),
        queryClient.invalidateQueries({ queryKey: expenseQueryKey.byDate(fromDate, toDate) }),
        queryClient.invalidateQueries({ queryKey: expenseQueryKey.byCreator(fromDate, toDate) }),
      ]);
    },
  });

  const approveExpenseMutation = useMutation({
    mutationFn: async (expenseUuid: string) => {
      if (!authContext || !activeSession?.userUuid) throw new Error('Missing active user');
      return approveExpense({
        ...authContext,
        expenseUuid,
        approvedByUserUuid: activeSession.userUuid,
      });
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['expenses'] });
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: expenseQueryKey.byCategory(fromDate, toDate) }),
        queryClient.invalidateQueries({ queryKey: expenseQueryKey.byDate(fromDate, toDate) }),
        queryClient.invalidateQueries({ queryKey: expenseQueryKey.byCreator(fromDate, toDate) }),
      ]);
    },
  });

  const rejectExpenseMutation = useMutation({
    mutationFn: async (expenseUuid: string) => {
      if (!authContext || !activeSession?.userUuid) throw new Error('Missing active user');
      return rejectExpense({
        ...authContext,
        expenseUuid,
        rejectedByUserUuid: activeSession.userUuid,
        reason: rejectionReason.trim() || undefined,
      });
    },
    onSuccess: async () => {
      setRejectionReason('');
      await queryClient.invalidateQueries({ queryKey: ['expenses'] });
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: expenseQueryKey.byCategory(fromDate, toDate) }),
        queryClient.invalidateQueries({ queryKey: expenseQueryKey.byDate(fromDate, toDate) }),
        queryClient.invalidateQueries({ queryKey: expenseQueryKey.byCreator(fromDate, toDate) }),
      ]);
    },
  });

  const requestReversalMutation = useMutation({
    mutationFn: async () => {
      if (!authContext || !activeSession?.userUuid) throw new Error('Missing active user');
      if (!reversalExpenseUuid) throw new Error('Select an approved expense first');
      if (!reversalReason.trim()) throw new Error('Reversal reason is required');
      return requestExpenseReversal({
        ...authContext,
        expenseUuid: reversalExpenseUuid,
        requestedByUserUuid: activeSession.userUuid,
        reason: reversalReason.trim(),
      });
    },
    onSuccess: async () => {
      setReversalExpenseUuid('');
      setReversalReason('');
      await queryClient.invalidateQueries({ queryKey: ['expenses'] });
    },
  });

  if (!activeWorkspace || !activeSession) {
    return (
      <div className="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
        Open a workspace with an active session to manage expenses.
      </div>
    );
  }

  const expenseRows = expensesQuery.data ?? [];
  const approvedRows = expenseRows.filter((row) => row.status === 'approved');

  const statusCardClass = (active: boolean) =>
    active
      ? 'border-blue-600 bg-blue-50 text-blue-900'
      : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300';

  const tableEmptyState = (label: string) => (
    <tr>
      <td className="px-3 py-3 text-sm text-slate-500" colSpan={7}>
        No {label} expenses found for current filters.
      </td>
    </tr>
  );

  return (
    <div className="space-y-6">
      <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-lg font-semibold text-slate-900">Expense Recording</h2>
          <div className="text-xs text-slate-500">Captured records are retained for audit history.</div>
        </div>

        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
          <label className="flex flex-col gap-1 text-sm text-slate-700">
            Expense category
            <select
              className="rounded-md border border-slate-300 bg-white px-3 py-2"
              value={expenseCategoryUuid}
              onChange={(event) => setExpenseCategoryUuid(event.target.value)}
            >
              <option value="">Select category</option>
              {(categoriesQuery.data ?? []).map((category) => (
                <option key={category.uuid} value={category.uuid}>
                  {category.name}
                </option>
              ))}
            </select>
          </label>

          <label className="flex flex-col gap-1 text-sm text-slate-700">
            Amount
            <input
              className="rounded-md border border-slate-300 px-3 py-2"
              value={amount}
              onChange={(event) => setAmount(event.target.value)}
              placeholder="0.00"
            />
          </label>

          <label className="flex flex-col gap-1 text-sm text-slate-700">
            Payment method
            <select
              className="rounded-md border border-slate-300 bg-white px-3 py-2"
              value={paymentMethod}
              onChange={(event) => setPaymentMethod(event.target.value as PaymentMethod)}
            >
              <option value="cash">Cash</option>
              <option value="bank">Bank</option>
            </select>
          </label>

          {paymentMethod === 'cash' ? (
            <label className="flex flex-col gap-1 text-sm text-slate-700">
              Cash account
              <select
                className="rounded-md border border-slate-300 bg-white px-3 py-2"
                value={cashAccountUuid}
                onChange={(event) => setCashAccountUuid(event.target.value)}
              >
                <option value="">Select cash account</option>
                {(cashAccountsQuery.data ?? []).map((account) => (
                  <option key={account.uuid} value={account.uuid}>
                    {account.name}
                  </option>
                ))}
              </select>
            </label>
          ) : (
            <label className="flex flex-col gap-1 text-sm text-slate-700">
              Bank account
              <select
                className="rounded-md border border-slate-300 bg-white px-3 py-2"
                value={bankAccountUuid}
                onChange={(event) => setBankAccountUuid(event.target.value)}
              >
                <option value="">Select bank account</option>
                {(bankAccountsQuery.data ?? []).map((account) => (
                  <option key={account.uuid} value={account.uuid}>
                    {account.name}
                  </option>
                ))}
              </select>
            </label>
          )}

          <label className="md:col-span-2 xl:col-span-3 flex flex-col gap-1 text-sm text-slate-700">
            Description
            <input
              className="rounded-md border border-slate-300 px-3 py-2"
              value={description}
              onChange={(event) => setDescription(event.target.value)}
              placeholder="Expense details"
            />
          </label>

          <label className="flex flex-col gap-1 text-sm text-slate-700">
            Shift-related expense (shift UUID)
            <input
              className="rounded-md border border-slate-300 px-3 py-2"
              value={shiftUuid}
              onChange={(event) => setShiftUuid(event.target.value)}
              placeholder="Optional shift UUID"
            />
          </label>

          <label className="md:col-span-2 flex flex-col gap-1 text-sm text-slate-700">
            Receipt attachment path
            <input
              className="rounded-md border border-slate-300 px-3 py-2"
              value={receiptAttachmentPath}
              onChange={(event) => setReceiptAttachmentPath(event.target.value)}
              placeholder="Optional file path"
            />
          </label>

          <label className="inline-flex items-center gap-2 text-sm text-slate-700">
            <input
              type="checkbox"
              checked={approvalRequested}
              onChange={(event) => setApprovalRequested(event.target.checked)}
            />
            Approval request
          </label>
        </div>

        <div className="mt-3 flex items-center gap-2">
          <button
            type="button"
            className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
            onClick={() => createExpenseMutation.mutate()}
            disabled={createExpenseMutation.isPending}
          >
            Record Expense
          </button>
          {createExpenseMutation.isError ? (
            <span className="text-sm text-rose-700">{String(createExpenseMutation.error)}</span>
          ) : null}
        </div>
      </section>

      <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <h2 className="mb-4 text-lg font-semibold text-slate-900">Expense Category Setup</h2>
        <div className="grid gap-3 md:grid-cols-3">
          <label className="md:col-span-2 flex flex-col gap-1 text-sm text-slate-700">
            Category name
            <input
              className="rounded-md border border-slate-300 px-3 py-2"
              value={newCategoryName}
              onChange={(event) => setNewCategoryName(event.target.value)}
              placeholder="Transportation"
            />
          </label>

          <label className="inline-flex items-center gap-2 pt-7 text-sm text-slate-700">
            <input
              type="checkbox"
              checked={newCategoryRequiresApproval}
              onChange={(event) => setNewCategoryRequiresApproval(event.target.checked)}
            />
            Requires approval
          </label>
        </div>

        <div className="mt-3 flex items-center gap-2">
          <button
            type="button"
            className="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60"
            onClick={() => createCategoryMutation.mutate()}
            disabled={createCategoryMutation.isPending}
          >
            Add Category
          </button>
          {createCategoryMutation.isError ? (
            <span className="text-sm text-rose-700">{String(createCategoryMutation.error)}</span>
          ) : null}
        </div>
      </section>

      <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div className="mb-4 flex flex-wrap items-center gap-2">
          <button type="button" className={`rounded-md border px-3 py-1.5 text-sm ${statusCardClass(statusFilter === 'pending_approval')}`} onClick={() => setStatusFilter('pending_approval')}>
            Pending expenses
          </button>
          <button type="button" className={`rounded-md border px-3 py-1.5 text-sm ${statusCardClass(statusFilter === 'approved')}`} onClick={() => setStatusFilter('approved')}>
            Approved expenses
          </button>
          <button type="button" className={`rounded-md border px-3 py-1.5 text-sm ${statusCardClass(statusFilter === 'rejected')}`} onClick={() => setStatusFilter('rejected')}>
            Rejected expenses
          </button>
        </div>

        <div className="mb-4 grid gap-3 md:grid-cols-3">
          <label className="flex flex-col gap-1 text-sm text-slate-700">
            Filter by category
            <select
              className="rounded-md border border-slate-300 bg-white px-3 py-2"
              value={categoryFilter}
              onChange={(event) => setCategoryFilter(event.target.value)}
            >
              <option value="all">All categories</option>
              {(categoriesQuery.data ?? []).map((category) => (
                <option key={category.uuid} value={category.uuid}>
                  {category.name}
                </option>
              ))}
            </select>
          </label>

          <label className="flex flex-col gap-1 text-sm text-slate-700">
            From date
            <input
              type="date"
              className="rounded-md border border-slate-300 px-3 py-2"
              value={fromDate}
              onChange={(event) => setFromDate(event.target.value)}
            />
          </label>

          <label className="flex flex-col gap-1 text-sm text-slate-700">
            To date
            <input
              type="date"
              className="rounded-md border border-slate-300 px-3 py-2"
              value={toDate}
              onChange={(event) => setToDate(event.target.value)}
            />
          </label>
        </div>

        <div className="overflow-x-auto rounded-lg border border-slate-200">
          <table className="min-w-full divide-y divide-slate-200">
            <thead className="bg-slate-50">
              <tr>
                <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Category</th>
                <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Description</th>
                <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Amount</th>
                <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Method</th>
                <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Creator</th>
                <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Created at</th>
                <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-200 bg-white">
              {expensesQuery.isLoading ? (
                <tr>
                  <td className="px-3 py-3 text-sm text-slate-500" colSpan={7}>Loading expenses...</td>
                </tr>
              ) : expenseRows.length === 0 ? (
                tableEmptyState(statusFilter.replace('_', ' '))
              ) : (
                expenseRows.map((expense: ExpenseRow) => (
                  <tr key={expense.uuid}>
                    <td className="px-3 py-2 text-sm text-slate-800">{expense.categoryName ?? 'Uncategorized'}</td>
                    <td className="px-3 py-2 text-sm text-slate-800">
                      <div className="flex items-center gap-2">
                        <span>{expense.description}</span>
                        {expense.receiptAttachmentPath ? (
                          <span title={expense.receiptAttachmentPath}>
                            <FileText className="h-4 w-4 text-slate-500" />
                          </span>
                        ) : null}
                      </div>
                    </td>
                    <td className="px-3 py-2 text-sm font-medium text-slate-900">{formatMoney(expense.amount)}</td>
                    <td className="px-3 py-2 text-sm text-slate-800">{expense.paymentMethod}</td>
                    <td className="px-3 py-2 text-sm text-slate-800">{expense.createdByName ?? expense.createdByUserUuid ?? 'Unknown'}</td>
                    <td className="px-3 py-2 text-sm text-slate-800">{formatDate(expense.createdAt)}</td>
                    <td className="px-3 py-2 text-sm text-slate-800">
                      <div className="flex flex-wrap items-center gap-2">
                        {expense.status === 'pending_approval' ? (
                          <>
                            <button
                              type="button"
                              className="inline-flex items-center gap-1 rounded-md border border-emerald-300 bg-emerald-50 px-2 py-1 text-emerald-700 hover:bg-emerald-100"
                              onClick={() => approveExpenseMutation.mutate(expense.uuid)}
                              title="Approve expense"
                            >
                              <Check className="h-3.5 w-3.5" />
                              Approve
                            </button>
                            <button
                              type="button"
                              className="inline-flex items-center gap-1 rounded-md border border-rose-300 bg-rose-50 px-2 py-1 text-rose-700 hover:bg-rose-100"
                              onClick={() => rejectExpenseMutation.mutate(expense.uuid)}
                              title="Reject expense"
                            >
                              <X className="h-3.5 w-3.5" />
                              Reject
                            </button>
                          </>
                        ) : null}
                        {expense.status === 'approved' ? (
                          <button
                            type="button"
                            className="inline-flex items-center gap-1 rounded-md border border-amber-300 bg-amber-50 px-2 py-1 text-amber-800 hover:bg-amber-100"
                            onClick={() => setReversalExpenseUuid(expense.uuid)}
                            title="Prepare reversal request"
                          >
                            <RefreshCcw className="h-3.5 w-3.5" />
                            Request reversal
                          </button>
                        ) : null}
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {rejectExpenseMutation.isError ? (
          <p className="mt-3 text-sm text-rose-700">{String(rejectExpenseMutation.error)}</p>
        ) : null}
        {approveExpenseMutation.isError ? (
          <p className="mt-3 text-sm text-rose-700">{String(approveExpenseMutation.error)}</p>
        ) : null}

        <div className="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3">
          <div className="mb-2 flex items-center gap-2 text-sm font-medium text-slate-800">
            <AlertCircle className="h-4 w-4" />
            Expense reversal request
          </div>
          <div className="grid gap-3 md:grid-cols-3">
            <label className="flex flex-col gap-1 text-sm text-slate-700">
              Approved expense
              <select
                className="rounded-md border border-slate-300 bg-white px-3 py-2"
                value={reversalExpenseUuid}
                onChange={(event) => setReversalExpenseUuid(event.target.value)}
              >
                <option value="">Select approved expense</option>
                {approvedRows.map((expense) => (
                  <option key={expense.uuid} value={expense.uuid}>
                    {expense.description} ({formatMoney(expense.amount)})
                  </option>
                ))}
              </select>
            </label>

            <label className="md:col-span-2 flex flex-col gap-1 text-sm text-slate-700">
              Reversal reason
              <input
                className="rounded-md border border-slate-300 px-3 py-2"
                value={reversalReason}
                onChange={(event) => setReversalReason(event.target.value)}
                placeholder="Reason for reversal"
              />
            </label>
          </div>

          <div className="mt-3 flex items-center gap-2">
            <button
              type="button"
              className="rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-60"
              onClick={() => requestReversalMutation.mutate()}
              disabled={requestReversalMutation.isPending}
            >
              Submit Reversal Request
            </button>
            {requestReversalMutation.isError ? (
              <span className="text-sm text-rose-700">{String(requestReversalMutation.error)}</span>
            ) : null}
          </div>
        </div>

        <div className="mt-4 grid gap-3 md:grid-cols-2">
          <label className="flex flex-col gap-1 text-sm text-slate-700">
            Rejection reason template
            <input
              className="rounded-md border border-slate-300 px-3 py-2"
              value={rejectionReason}
              onChange={(event) => setRejectionReason(event.target.value)}
              placeholder="Optional note for rejection action"
            />
          </label>
        </div>
      </section>

      <section className="grid gap-4 lg:grid-cols-3">
        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <h3 className="mb-3 text-base font-semibold text-slate-900">Expenses by category</h3>
          <div className="space-y-2">
            {(byCategoryQuery.data ?? []).map((row) => (
              <div key={`${row.categoryUuid ?? 'unknown'}-${row.categoryName ?? 'unknown'}`} className="flex items-center justify-between rounded-md bg-slate-50 px-3 py-2 text-sm">
                <span className="text-slate-700">{row.categoryName ?? 'Uncategorized'}</span>
                <span className="font-medium text-slate-900">{formatMoney(row.amount)}</span>
              </div>
            ))}
            {!byCategoryQuery.isLoading && (byCategoryQuery.data ?? []).length === 0 ? (
              <p className="text-sm text-slate-500">No category summary for selected dates.</p>
            ) : null}
          </div>
        </div>

        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <h3 className="mb-3 text-base font-semibold text-slate-900">Expenses by date</h3>
          <div className="space-y-2">
            {(byDateQuery.data ?? []).map((row) => (
              <div key={row.expenseDate} className="flex items-center justify-between rounded-md bg-slate-50 px-3 py-2 text-sm">
                <span className="text-slate-700">{row.expenseDate}</span>
                <span className="font-medium text-slate-900">{formatMoney(row.amount)}</span>
              </div>
            ))}
            {!byDateQuery.isLoading && (byDateQuery.data ?? []).length === 0 ? (
              <p className="text-sm text-slate-500">No date summary for selected dates.</p>
            ) : null}
          </div>
        </div>

        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <h3 className="mb-3 text-base font-semibold text-slate-900">Expenses by creator</h3>
          <div className="space-y-2">
            {(byCreatorQuery.data ?? []).map((row) => (
              <div key={`${row.creatorUserUuid ?? 'unknown'}-${row.creatorName}`} className="flex items-center justify-between rounded-md bg-slate-50 px-3 py-2 text-sm">
                <span className="text-slate-700">{row.creatorName}</span>
                <span className="font-medium text-slate-900">{formatMoney(row.amount)}</span>
              </div>
            ))}
            {!byCreatorQuery.isLoading && (byCreatorQuery.data ?? []).length === 0 ? (
              <p className="text-sm text-slate-500">No creator summary for selected dates.</p>
            ) : null}
          </div>
        </div>
      </section>
    </div>
  );
}
