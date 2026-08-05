import { randomUUID } from 'node:crypto';
import type Database from 'better-sqlite3';
import type { AuditLogRepository } from '../../repositories/audit-log-repository';
import type { SyncQueueRepository } from '../../repositories/sync-queue-repository';

export class CashierService {
  constructor(private readonly sqlite: Database, private readonly auditLogs: AuditLogRepository, private readonly syncQueue: SyncQueueRepository) {}

  async createTerminal(input: { code: string; name: string }) {
    return this.createMaster('terminals', input, { status: 'active' });
  }

  async createCashAccount(input: { code: string; name: string }) {
    return this.createMaster('cash_accounts', input, { currentBalance: '0', active: '1' });
  }

  async createExpenseCategory(input: { name: string; requiresApproval: boolean }) {
    return this.createMaster('expense_categories', input, { requiresApproval: input.requiresApproval ? '1' : '0', active: '1' });
  }

  listExpenseCategories() {
    return this.sqlite.prepare(`
      SELECT uuid, name, requires_approval AS requiresApproval, active, created_at AS createdAt, updated_at AS updatedAt
      FROM expense_categories
      WHERE active = '1'
      ORDER BY name ASC
    `).all();
  }

  listExpenses(filter?: { status?: string; categoryUuid?: string; from?: string; to?: string; creatorUserUuid?: string }) {
    return this.sqlite.prepare(`
      SELECT
        e.uuid,
        e.category_uuid AS categoryUuid,
        ec.name AS categoryName,
        e.approval_requested AS approvalRequested,
        e.created_by_user_uuid AS createdByUserUuid,
        e.created_by_name AS createdByName,
        e.approved_by_user_uuid AS approvedByUserUuid,
        e.reversed_by_user_uuid AS reversedByUserUuid,
        e.reversed_at AS reversedAt,
        e.cash_account_uuid AS cashAccountUuid,
        ca.name AS cashAccountName,
        e.bank_account_uuid AS bankAccountUuid,
        ba.name AS bankAccountName,
        e.shift_uuid AS shiftUuid,
        e.amount,
        e.payment_method AS paymentMethod,
        e.description,
        e.receipt_attachment_path AS receiptAttachmentPath,
        e.status,
        e.created_at AS createdAt,
        e.updated_at AS updatedAt
      FROM expenses e
      LEFT JOIN expense_categories ec ON ec.uuid = e.category_uuid
      LEFT JOIN cash_accounts ca ON ca.uuid = e.cash_account_uuid
      LEFT JOIN bank_accounts ba ON ba.uuid = e.bank_account_uuid
      WHERE (? IS NULL OR e.status = ?)
        AND (? IS NULL OR e.category_uuid = ?)
        AND (? IS NULL OR date(e.created_at) >= date(?))
        AND (? IS NULL OR date(e.created_at) <= date(?))
        AND (? IS NULL OR e.created_by_user_uuid = ?)
      ORDER BY e.created_at DESC
    `).all(
      filter?.status ?? null,
      filter?.status ?? null,
      filter?.categoryUuid ?? null,
      filter?.categoryUuid ?? null,
      filter?.from ?? null,
      filter?.from ?? null,
      filter?.to ?? null,
      filter?.to ?? null,
      filter?.creatorUserUuid ?? null,
      filter?.creatorUserUuid ?? null,
    );
  }

  summarizeExpensesByCategory(filter?: { from?: string; to?: string }) {
    return this.sqlite.prepare(`
      SELECT
        e.category_uuid AS categoryUuid,
        ec.name AS categoryName,
        COUNT(*) AS expenseCount,
        COALESCE(SUM(CAST(e.amount AS REAL)), 0) AS amount
      FROM expenses e
      LEFT JOIN expense_categories ec ON ec.uuid = e.category_uuid
      WHERE (? IS NULL OR date(e.created_at) >= date(?))
        AND (? IS NULL OR date(e.created_at) <= date(?))
      GROUP BY e.category_uuid, ec.name
      ORDER BY amount DESC
    `).all(filter?.from ?? null, filter?.from ?? null, filter?.to ?? null, filter?.to ?? null);
  }

  summarizeExpensesByDate(filter?: { from?: string; to?: string }) {
    return this.sqlite.prepare(`
      SELECT
        date(e.created_at) AS expenseDate,
        COUNT(*) AS expenseCount,
        COALESCE(SUM(CAST(e.amount AS REAL)), 0) AS amount
      FROM expenses e
      WHERE (? IS NULL OR date(e.created_at) >= date(?))
        AND (? IS NULL OR date(e.created_at) <= date(?))
      GROUP BY date(e.created_at)
      ORDER BY expenseDate DESC
    `).all(filter?.from ?? null, filter?.from ?? null, filter?.to ?? null, filter?.to ?? null);
  }

  summarizeExpensesByCreator(filter?: { from?: string; to?: string }) {
    return this.sqlite.prepare(`
      SELECT
        e.created_by_user_uuid AS creatorUserUuid,
        COALESCE(e.created_by_name, 'Unknown') AS creatorName,
        COUNT(*) AS expenseCount,
        COALESCE(SUM(CAST(e.amount AS REAL)), 0) AS amount
      FROM expenses e
      WHERE (? IS NULL OR date(e.created_at) >= date(?))
        AND (? IS NULL OR date(e.created_at) <= date(?))
      GROUP BY e.created_by_user_uuid, e.created_by_name
      ORDER BY amount DESC
    `).all(filter?.from ?? null, filter?.from ?? null, filter?.to ?? null, filter?.to ?? null);
  }

  async openShift(input: { terminalUuid: string; cashAccountUuid: string; cashierUserUuid: string; openingCash: string; notes?: string }) {
    const conflictingShift = this.sqlite.prepare("SELECT uuid FROM cashier_shifts WHERE status = 'open' AND (cashier_user_uuid = ? OR terminal_uuid = ? OR cash_account_uuid = ?) LIMIT 1").get(input.cashierUserUuid, input.terminalUuid, input.cashAccountUuid) as { uuid: string } | undefined;
    if (conflictingShift) {
      throw new Error('Another active shift already conflicts with this workstation');
    }

    const now = new Date().toISOString();
    const shift = { uuid: randomUUID(), ...input, notes: input.notes ?? null, status: 'open', openedAt: now, createdAt: now, updatedAt: now };
    this.sqlite.prepare('INSERT INTO cashier_shifts (uuid, terminal_uuid, cash_account_uuid, cashier_user_uuid, status, opening_cash, notes, opened_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)').run(shift.uuid, shift.terminalUuid, shift.cashAccountUuid, shift.cashierUserUuid, shift.status, shift.openingCash, shift.notes, now, now, now);
    this.audit('cashier.shift_opened', 'cashier_shift', shift.uuid, { openingCash: shift.openingCash, notes: shift.notes });
    return { ...shift, shiftNumber: this.shiftNumber(shift.uuid) };
  }

  async recordShiftPayment(shiftUuid: string, input: { type: 'cash_sale' | 'cash_refund'; amount: string; reference?: string }) {
    this.requireOpenShift(shiftUuid);
    const entry = { uuid: randomUUID(), shiftUuid, ...input, reference: input.reference ?? null, createdAt: new Date().toISOString() };
    this.sqlite.prepare('INSERT INTO cashier_shift_payments (uuid, shift_uuid, type, amount, reference, created_at) VALUES (?, ?, ?, ?, ?, ?)').run(entry.uuid, shiftUuid, entry.type, entry.amount, entry.reference, entry.createdAt);
    this.audit('cashier.shift_payment_recorded', 'cashier_shift_payment', entry.uuid, { shiftUuid, type: entry.type, amount: entry.amount });
    return entry;
  }

  async recordExpense(input: {
    categoryUuid: string;
    amount: string;
    paymentMethod: 'cash' | 'bank';
    description: string;
    cashAccountUuid?: string;
    bankAccountUuid?: string;
    shiftUuid?: string;
    receiptAttachmentPath?: string;
    approvalRequested?: boolean;
    createdByUserUuid?: string;
    createdByName?: string;
  }) {
    if ((input.paymentMethod === 'cash') !== Boolean(input.cashAccountUuid) || (input.paymentMethod === 'bank') !== Boolean(input.bankAccountUuid)) {
      throw new Error('Expense payment account does not match payment method');
    }
    if (input.shiftUuid) this.requireOpenShift(input.shiftUuid);

    const category = this.sqlite.prepare('SELECT requires_approval AS requiresApproval FROM expense_categories WHERE uuid = ? AND active = ?').get(input.categoryUuid, '1') as { requiresApproval: string } | undefined;
    if (!category) throw new Error('Expense category not found');

    const now = new Date().toISOString();
    const creatorName = input.createdByName ?? this.resolveUserDisplayName(input.createdByUserUuid);
    const approvalRequested = input.approvalRequested ?? false;
    const status = category.requiresApproval === '1' || approvalRequested ? 'pending_approval' : 'approved';
    const expense = {
      uuid: randomUUID(),
      ...input,
      approvalRequested: approvalRequested ? '1' : '0',
      createdByName: creatorName,
      cashAccountUuid: input.cashAccountUuid ?? null,
      bankAccountUuid: input.bankAccountUuid ?? null,
      shiftUuid: input.shiftUuid ?? null,
      receiptAttachmentPath: input.receiptAttachmentPath ?? null,
      status,
      createdAt: now,
      updatedAt: now,
    };

    this.sqlite.transaction(() => {
      this.sqlite.prepare(`
        INSERT INTO expenses (
          uuid, category_uuid, approval_requested, created_by_user_uuid, created_by_name,
          cash_account_uuid, bank_account_uuid, shift_uuid, amount, payment_method,
          description, receipt_attachment_path, status, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      `).run(
        expense.uuid,
        expense.categoryUuid,
        expense.approvalRequested,
        expense.createdByUserUuid ?? null,
        expense.createdByName ?? null,
        expense.cashAccountUuid,
        expense.bankAccountUuid,
        expense.shiftUuid,
        expense.amount,
        expense.paymentMethod,
        expense.description,
        expense.receiptAttachmentPath,
        expense.status,
        now,
        now,
      );

      if (expense.status === 'approved') {
        this.postExpense(expense);
      }
    })();

    this.audit('expense.recorded', 'expense', expense.uuid, { status: expense.status, createdByUserUuid: expense.createdByUserUuid ?? null });
    return expense;
  }

  async approveExpense(expenseUuid: string, approvedByUserUuid: string) {
    const expense = this.requireExpense(expenseUuid);
    if (expense.status !== 'pending_approval') throw new Error('Expense is not pending approval');

    const now = new Date().toISOString();
    this.sqlite.transaction(() => {
      this.sqlite.prepare('INSERT INTO expense_approvals (uuid, expense_uuid, approved_by_user_uuid, status, created_at) VALUES (?, ?, ?, ?, ?)')
        .run(randomUUID(), expenseUuid, approvedByUserUuid, 'approved', now);
      this.sqlite.prepare("UPDATE expenses SET status = 'approved', approved_by_user_uuid = ?, updated_at = ? WHERE uuid = ?")
        .run(approvedByUserUuid, now, expenseUuid);
      this.postExpense(expense);
    })();

    this.audit('expense.approved', 'expense', expenseUuid, { approvedByUserUuid });
    return { ...expense, status: 'approved', approvedByUserUuid };
  }

  async rejectExpense(expenseUuid: string, rejectedByUserUuid: string, reason?: string) {
    const expense = this.requireExpense(expenseUuid);
    if (expense.status !== 'pending_approval') throw new Error('Expense is not pending approval');

    const now = new Date().toISOString();
    this.sqlite.transaction(() => {
      this.sqlite.prepare('INSERT INTO expense_approvals (uuid, expense_uuid, approved_by_user_uuid, status, created_at) VALUES (?, ?, ?, ?, ?)')
        .run(randomUUID(), expenseUuid, rejectedByUserUuid, 'rejected', now);
      this.sqlite.prepare("UPDATE expenses SET status = 'rejected', updated_at = ? WHERE uuid = ?")
        .run(now, expenseUuid);
    })();

    this.audit('expense.rejected', 'expense', expenseUuid, { rejectedByUserUuid, reason: reason ?? null });
    return { ...expense, status: 'rejected' };
  }

  async requestExpenseReversal(expenseUuid: string, reason: string, requestedByUserUuid?: string) {
    const expense = this.requireExpense(expenseUuid);
    if (expense.status !== 'approved' && expense.status !== 'reversal_requested') {
      throw new Error('Only approved expenses can be submitted for reversal');
    }

    const now = new Date().toISOString();
    const request = {
      uuid: randomUUID(),
      expenseUuid,
      requestedByUserUuid: requestedByUserUuid ?? null,
      approvedByUserUuid: null,
      rejectedByUserUuid: null,
      reason,
      rejectionReason: null,
      status: 'requested',
      createdAt: now,
      processedAt: null,
    };

    this.sqlite.transaction(() => {
      this.sqlite.prepare(`
        INSERT INTO expense_reversal_requests (
          uuid, expense_uuid, requested_by_user_uuid, approved_by_user_uuid,
          rejected_by_user_uuid, reason, rejection_reason, status, created_at, processed_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      `).run(
        request.uuid,
        request.expenseUuid,
        request.requestedByUserUuid,
        request.approvedByUserUuid,
        request.rejectedByUserUuid,
        request.reason,
        request.rejectionReason,
        request.status,
        request.createdAt,
        request.processedAt,
      );

      this.sqlite.prepare("UPDATE expenses SET status = 'reversal_requested', updated_at = ? WHERE uuid = ?")
        .run(now, expenseUuid);
    })();

    this.audit('expense.reversal_requested', 'expense_reversal_request', request.uuid, { expenseUuid, requestedByUserUuid: requestedByUserUuid ?? null, reason });
    return request;
  }

  async approveExpenseReversalRequest(requestUuid: string, approvedByUserUuid: string) {
    const request = this.sqlite.prepare(`
      SELECT uuid, expense_uuid AS expenseUuid, status, reason
      FROM expense_reversal_requests
      WHERE uuid = ?
    `).get(requestUuid) as { uuid: string; expenseUuid: string; status: string; reason: string } | undefined;
    if (!request) throw new Error('Expense reversal request not found');
    if (request.status !== 'requested') throw new Error('Expense reversal request is not pending');

    const expense = this.requireExpense(request.expenseUuid);
    if (expense.status !== 'reversal_requested' && expense.status !== 'approved') throw new Error('Expense is not eligible for reversal');

    const now = new Date().toISOString();
    this.sqlite.transaction(() => {
      this.sqlite.prepare(`
        UPDATE expense_reversal_requests
        SET status = 'approved', approved_by_user_uuid = ?, processed_at = ?
        WHERE uuid = ?
      `).run(approvedByUserUuid, now, requestUuid);

      this.sqlite.prepare(`
        UPDATE expenses
        SET status = 'cancelled', reversed_by_user_uuid = ?, reversed_at = ?, updated_at = ?
        WHERE uuid = ?
      `).run(approvedByUserUuid, now, now, request.expenseUuid);

      this.postTransaction(expense.paymentMethod, expense.cashAccountUuid, expense.bankAccountUuid, expense.amount, 'expense_reversal', 'expense', request.expenseUuid, { reason: request.reason }, now, true);
    })();

    this.audit('expense.reversal_approved', 'expense_reversal_request', requestUuid, { approvedByUserUuid, expenseUuid: request.expenseUuid });
    return { requestUuid, expenseUuid: request.expenseUuid, status: 'approved', approvedByUserUuid, processedAt: now };
  }

  async rejectExpenseReversalRequest(requestUuid: string, rejectedByUserUuid: string, reason?: string) {
    const request = this.sqlite.prepare(`
      SELECT uuid, expense_uuid AS expenseUuid, status
      FROM expense_reversal_requests
      WHERE uuid = ?
    `).get(requestUuid) as { uuid: string; expenseUuid: string; status: string } | undefined;
    if (!request) throw new Error('Expense reversal request not found');
    if (request.status !== 'requested') throw new Error('Expense reversal request is not pending');

    const now = new Date().toISOString();
    this.sqlite.transaction(() => {
      this.sqlite.prepare(`
        UPDATE expense_reversal_requests
        SET status = 'rejected', rejected_by_user_uuid = ?, rejection_reason = ?, processed_at = ?
        WHERE uuid = ?
      `).run(rejectedByUserUuid, reason ?? null, now, requestUuid);

      this.sqlite.prepare("UPDATE expenses SET status = 'approved', updated_at = ? WHERE uuid = ?")
        .run(now, request.expenseUuid);
    })();

    this.audit('expense.reversal_rejected', 'expense_reversal_request', requestUuid, { rejectedByUserUuid, reason: reason ?? null, expenseUuid: request.expenseUuid });
    return { requestUuid, expenseUuid: request.expenseUuid, status: 'rejected', rejectedByUserUuid, processedAt: now };
  }

  async cancelExpense(expenseUuid: string, reason: string) {
    const expense = this.requireExpense(expenseUuid);
    if (expense.status !== 'approved') throw new Error('Only approved expenses can be cancelled');

    const now = new Date().toISOString();
    this.sqlite.transaction(() => {
      this.sqlite.prepare("UPDATE expenses SET status = 'cancelled', updated_at = ? WHERE uuid = ?").run(now, expenseUuid);
      this.postTransaction(expense.paymentMethod, expense.cashAccountUuid, expense.bankAccountUuid, expense.amount, 'expense_reversal', 'expense', expenseUuid, { reason }, now, true);
    })();

    this.audit('expense.cancelled', 'expense', expenseUuid, { reason });
    return { ...expense, status: 'cancelled' };
  }

  async closeShift(shiftUuid: string, input: { actualCash: string; notes?: string; cashDenominations?: Array<{ denomination: string; count: number }>; supportingExplanation?: string }) {
    const shift = this.requireOpenShift(shiftUuid);
    const expected = this.expectedCash(shiftUuid, shift.openingCash);
    const now = new Date().toISOString();
    const settlementUuid = randomUUID();
    const variance = this.format(this.num(input.actualCash) - this.num(expected));
    const notes = input.notes ?? null;
    const cashDenominationsJson = JSON.stringify(input.cashDenominations ?? []);
    const supportingExplanation = input.supportingExplanation ?? null;

    this.sqlite.transaction(() => {
      this.sqlite.prepare("UPDATE cashier_shifts SET status = 'closed', expected_cash = ?, actual_cash = ?, variance = ?, notes = COALESCE(?, notes), closed_at = ?, updated_at = ? WHERE uuid = ?")
        .run(expected, input.actualCash, variance, notes, now, now, shiftUuid);
      this.sqlite.prepare('INSERT INTO cashier_settlements (uuid, shift_uuid, expected_cash, actual_cash, variance, status, notes, cash_denominations_json, supporting_explanation, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        .run(settlementUuid, shiftUuid, expected, input.actualCash, variance, 'pending_verification', notes, cashDenominationsJson, supportingExplanation, now);
    })();

    this.audit('cashier.shift_closed', 'cashier_shift', shiftUuid, { expected, actualCash: input.actualCash, variance, notes, cashDenominations: input.cashDenominations ?? [], supportingExplanation });
    return { ...shift, status: 'closed', expectedCash: expected, actualCash: input.actualCash, variance, settlementUuid, notes, cashDenominations: input.cashDenominations ?? [], supportingExplanation };
  }

  async verifySettlement(settlementUuid: string, verifiedByUserUuid: string) {
    const now = new Date().toISOString();
    const settlement = this.sqlite.prepare('SELECT * FROM cashier_settlements WHERE uuid = ?').get(settlementUuid) as { uuid: string } | undefined;
    if (!settlement) throw new Error('Settlement not found');
    this.sqlite.prepare("UPDATE cashier_settlements SET status = 'verified', verified_by_user_uuid = ?, verified_at = ? WHERE uuid = ?").run(verifiedByUserUuid, now, settlementUuid);
    this.audit('cashier.settlement_verified', 'cashier_settlement', settlementUuid, { verifiedByUserUuid });
    return { ...settlement, status: 'verified', verifiedByUserUuid, verifiedAt: now };
  }

  async recordOwnerWithdrawal(input: { type: 'cash' | 'product' | 'personal_expense' | 'family_consumption'; cashAccountUuid?: string; amount?: string; reason: string; items?: Array<{ productUuid?: string; quantity?: string; description?: string }> }) {
    if (input.type === 'cash' && (!input.cashAccountUuid || !input.amount)) throw new Error('Cash withdrawal requires an account and amount');
    const now = new Date().toISOString();
    const withdrawal = { uuid: randomUUID(), ...input, cashAccountUuid: input.cashAccountUuid ?? null, amount: input.amount ?? null, status: 'recorded', createdAt: now };
    this.sqlite.transaction(() => {
      this.sqlite.prepare('INSERT INTO owner_withdrawals (uuid, type, cash_account_uuid, amount, reason, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
        .run(withdrawal.uuid, withdrawal.type, withdrawal.cashAccountUuid, withdrawal.amount, withdrawal.reason, withdrawal.status, now);
      for (const item of input.items ?? []) {
        this.sqlite.prepare('INSERT INTO owner_withdrawal_items (uuid, owner_withdrawal_uuid, product_uuid, quantity, description, created_at) VALUES (?, ?, ?, ?, ?, ?)')
          .run(randomUUID(), withdrawal.uuid, item.productUuid ?? null, item.quantity ?? null, item.description ?? null, now);
      }
      if (withdrawal.type === 'cash') this.postTransaction('cash', withdrawal.cashAccountUuid, null, withdrawal.amount!, 'owner_withdrawal', 'owner_withdrawal', withdrawal.uuid, null, now);
    })();
    this.audit('owner.withdrawal_recorded', 'owner_withdrawal', withdrawal.uuid, { type: withdrawal.type });
    return withdrawal;
  }

  private createMaster(table: 'terminals' | 'cash_accounts' | 'expense_categories', input: { code?: string; name: string }, extras: Record<string, string>) {
    const now = new Date().toISOString();
    const value = { uuid: randomUUID(), ...input, ...extras, createdAt: now, updatedAt: now };
    const columns = Object.keys(value).map((key) => key.replace(/[A-Z]/g, (letter) => `_${letter.toLowerCase()}`));
    this.sqlite.prepare(`INSERT INTO ${table} (${columns.join(', ')}) VALUES (${columns.map(() => '?').join(', ')})`).run(...Object.values(value));
    return value;
  }

  private expectedCash(shiftUuid: string, openingCash: string) {
    const payments = this.sqlite.prepare('SELECT type, amount FROM cashier_shift_payments WHERE shift_uuid = ?').all(shiftUuid) as Array<{ type: string; amount: string }>;
    const expenses = this.sqlite.prepare("SELECT amount FROM expenses WHERE shift_uuid = ? AND payment_method = 'cash' AND status = 'approved'").all(shiftUuid) as Array<{ amount: string }>;
    return this.format(
      this.num(openingCash)
        + payments.reduce((total, payment) => total + (payment.type === 'cash_sale' ? this.num(payment.amount) : -this.num(payment.amount)), 0)
        - expenses.reduce((total, expense) => total + this.num(expense.amount), 0),
    );
  }

  private shiftNumber(shiftUuid: string) {
    return `CSH-${shiftUuid.slice(0, 8).toUpperCase()}`;
  }

  private postExpense(expense: { paymentMethod: string; cashAccountUuid?: string | null; bankAccountUuid?: string | null; amount: string; uuid: string }) {
    this.postTransaction(expense.paymentMethod, expense.cashAccountUuid ?? null, expense.bankAccountUuid ?? null, expense.amount, 'expense', 'expense', expense.uuid, null, new Date().toISOString());
  }

  private postTransaction(method: string, cashAccountUuid: string | null, bankAccountUuid: string | null, amount: string, type: string, referenceType: string, referenceUuid: string, metadata: unknown, createdAt: string, reversal = false) {
    const signed = reversal ? this.num(amount) : -this.num(amount);
    if (method === 'cash') {
      this.sqlite.prepare('INSERT INTO cash_transactions (uuid, cash_account_uuid, transaction_type, amount, reference_type, reference_uuid, metadata_json, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        .run(randomUUID(), cashAccountUuid, type, this.format(signed), referenceType, referenceUuid, metadata ? JSON.stringify(metadata) : null, createdAt);
      this.sqlite.prepare('UPDATE cash_accounts SET current_balance = CAST(current_balance AS REAL) + ?, updated_at = ? WHERE uuid = ?')
        .run(signed, createdAt, cashAccountUuid);
      return;
    }

    this.sqlite.prepare('INSERT INTO bank_transactions (uuid, bank_account_uuid, transaction_type, amount, reference_type, reference_uuid, metadata_json, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
      .run(randomUUID(), bankAccountUuid, type, this.format(signed), referenceType, referenceUuid, metadata ? JSON.stringify(metadata) : null, createdAt);
    this.sqlite.prepare('UPDATE bank_accounts SET current_balance = CAST(current_balance AS REAL) + ?, updated_at = ? WHERE uuid = ?')
      .run(signed, createdAt, bankAccountUuid);
  }

  private requireOpenShift(uuid: string) {
    const shift = this.sqlite.prepare("SELECT uuid, opening_cash AS openingCash FROM cashier_shifts WHERE uuid = ? AND status = 'open'").get(uuid) as { uuid: string; openingCash: string } | undefined;
    if (!shift) throw new Error('Open cashier shift not found');
    return shift;
  }

  private requireExpense(uuid: string) {
    const expense = this.sqlite.prepare(`
      SELECT
        uuid,
        cash_account_uuid AS cashAccountUuid,
        bank_account_uuid AS bankAccountUuid,
        amount,
        payment_method AS paymentMethod,
        status
      FROM expenses
      WHERE uuid = ?
    `).get(uuid) as {
      uuid: string;
      cashAccountUuid: string | null;
      bankAccountUuid: string | null;
      amount: string;
      paymentMethod: string;
      status: string;
    } | undefined;
    if (!expense) throw new Error('Expense not found');
    return expense;
  }

  private resolveUserDisplayName(userUuid?: string) {
    if (!userUuid) return null;
    const row = this.sqlite.prepare('SELECT display_name AS displayName, username FROM users WHERE uuid = ?').get(userUuid) as { displayName: string | null; username: string } | undefined;
    if (!row) return null;
    return row.displayName ?? row.username;
  }

  private audit(action: string, subjectType: string, subjectUuid: string, metadata?: Record<string, unknown>) {
    this.auditLogs.record({ action, subjectType, subjectUuid, metadata });
    this.syncQueue.enqueue({ aggregateType: subjectType, aggregateUuid: subjectUuid, payload: { action, ...metadata }, idempotencyKey: `${subjectType}:${subjectUuid}` });
  }

  private num(value: string) {
    return Number(value);
  }

  private format(value: number) {
    return String(value);
  }
}
