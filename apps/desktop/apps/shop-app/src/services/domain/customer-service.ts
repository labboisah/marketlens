import { randomUUID } from 'node:crypto';
import type Database from 'better-sqlite3';
import type { AuditLogRepository } from '../../repositories/audit-log-repository';
import type { SyncQueueRepository } from '../../repositories/sync-queue-repository';
import { assertCreditAllowed } from './customer-credit-policy';

export type CustomerType = 'retail' | 'wholesale' | 'corporate';
export type CustomerLedgerTransactionType = 'opening_balance' | 'credit_sale' | 'payment' | 'refund' | 'adjustment' | 'write_off' | 'reversal';

type CustomerLedgerTransaction = {
  uuid: string;
  customerUuid: string;
  transactionType: CustomerLedgerTransactionType;
  amount: string;
  dueDate: string | null;
  reference: string | null;
  reversalOfUuid: string | null;
  metadataJson: string | null;
  createdAt: string;
};

export type CreateCustomerInput = {
  code: string;
  name: string;
  type: CustomerType;
  phone?: string;
  address?: string;
  creditLimit?: string;
  openingBalance?: string;
  contacts?: Array<{ name: string; phone?: string; email?: string; role?: string }>;
  guarantor?: { name: string; phone?: string; address?: string; relationship?: string };
};

export type CreditSaleInput = {
  amount: string;
  dueDate: string;
  reference?: string;
  creditApprovalUuid?: string;
};

export type CustomerPaymentInput = {
  amount: string;
  reference?: string;
  paymentMethod?: string;
  allocations?: Array<{ accountTransactionUuid: string; amount: string }>;
};

export class CustomerService {
  constructor(
    private readonly sqlite: Database,
    private readonly auditLogs: AuditLogRepository,
    private readonly syncQueue: SyncQueueRepository,
  ) {}

  async createCustomer(input: CreateCustomerInput) {
    const now = new Date().toISOString();
    const customer = { uuid: randomUUID(), code: input.code, name: input.name, type: input.type, phone: input.phone ?? null, address: input.address ?? null, status: 'active', createdAt: now, updatedAt: now };
    const account = { uuid: randomUUID(), customerUuid: customer.uuid, currentBalance: input.openingBalance ?? '0', createdAt: now, updatedAt: now };

    this.sqlite.transaction(() => {
      this.sqlite.prepare('INSERT INTO customers (uuid, code, name, type, phone, address, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
        .run(customer.uuid, customer.code, customer.name, customer.type, customer.phone, customer.address, customer.status, now, now);
      this.sqlite.prepare('INSERT INTO customer_credit_limits (uuid, customer_uuid, credit_limit, additional_credit_allowed, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')
        .run(randomUUID(), customer.uuid, input.creditLimit ?? '0', '0', now, now);
      this.sqlite.prepare('INSERT INTO customer_accounts (uuid, customer_uuid, current_balance, created_at, updated_at) VALUES (?, ?, ?, ?, ?)')
        .run(account.uuid, customer.uuid, account.currentBalance, now, now);

      for (const contact of input.contacts ?? []) {
        this.sqlite.prepare('INSERT INTO customer_contacts (uuid, customer_uuid, name, phone, email, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
          .run(randomUUID(), customer.uuid, contact.name, contact.phone ?? null, contact.email ?? null, contact.role ?? null, now, now);
      }
      if (input.guarantor) {
        this.sqlite.prepare('INSERT INTO customer_guarantors (uuid, customer_uuid, name, phone, address, relationship, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
          .run(randomUUID(), customer.uuid, input.guarantor.name, input.guarantor.phone ?? null, input.guarantor.address ?? null, input.guarantor.relationship ?? null, now, now);
      }
      if (this.amount(input.openingBalance ?? '0') !== 0) {
        this.insertLedgerTransaction({ customerUuid: customer.uuid, transactionType: 'opening_balance', amount: input.openingBalance ?? '0', createdAt: now });
      }
    })();

    this.auditLogs.record({ action: 'customer.created', subjectType: 'customer', subjectUuid: customer.uuid, metadata: { code: customer.code, type: customer.type } });
    this.syncQueue.enqueue({ aggregateType: 'customer', aggregateUuid: customer.uuid, payload: customer, idempotencyKey: `customer:${customer.uuid}` });
    return customer;
  }

  async recordCreditSale(customerUuid: string, input: CreditSaleInput): Promise<CustomerLedgerTransaction> {
    const transaction = this.sqlite.transaction(() => {
      const customer = this.requireCustomer(customerUuid);
      const account = this.requireAccount(customerUuid);
      const limit = this.sqlite.prepare('SELECT credit_limit AS creditLimit FROM customer_credit_limits WHERE customer_uuid = ?').get(customerUuid) as { creditLimit: string };
      const amount = this.amount(input.amount);
      const requestedExposure = this.amount(account.currentBalance) + amount;
      assertCreditAllowed({
        customerStatus: customer.status,
        currentBalance: this.amount(account.currentBalance),
        creditLimit: this.amount(limit.creditLimit),
        requestedAmount: amount,
        hasOverdueBalance: this.hasOverdueBalance(customerUuid),
        hasManagerApproval: this.hasApprovedCredit(input.creditApprovalUuid, customerUuid, requestedExposure),
      });

      const now = new Date().toISOString();
      const entry = this.insertLedgerTransaction({ customerUuid, transactionType: 'credit_sale', amount: input.amount, dueDate: input.dueDate, reference: input.reference ?? null, createdAt: now });
      this.updateBalanceCache(customerUuid, this.amount(account.currentBalance) + this.amount(input.amount), now);
      return entry;
    })();

    this.auditLogs.record({ action: 'customer.credit_sale_recorded', subjectType: 'customer_account_transaction', subjectUuid: transaction.uuid, metadata: { customerUuid, amount: input.amount } });
    this.syncQueue.enqueue({ aggregateType: 'customer_account_transaction', aggregateUuid: transaction.uuid, payload: transaction, idempotencyKey: `customer-account-transaction:${transaction.uuid}` });
    return transaction;
  }

  async recordPayment(customerUuid: string, input: CustomerPaymentInput) {
    const result = this.sqlite.transaction(() => {
      this.requireCustomer(customerUuid);
      const account = this.requireAccount(customerUuid);
      const amount = this.amount(input.amount);
      if (amount <= 0) throw new Error('Payment amount must be greater than zero');
      const allocations = input.allocations ?? [];
      if (allocations.reduce((total, allocation) => total + this.amount(allocation.amount), 0) > amount) throw new Error('Payment allocations exceed payment amount');

      const now = new Date().toISOString();
      const payment = { uuid: randomUUID(), customerUuid, amount: input.amount, reference: input.reference ?? null, paymentMethod: input.paymentMethod ?? null, createdAt: now };
      this.sqlite.prepare('INSERT INTO customer_payments (uuid, customer_uuid, amount, reference, payment_method, created_at) VALUES (?, ?, ?, ?, ?, ?)')
        .run(payment.uuid, customerUuid, payment.amount, payment.reference, payment.paymentMethod, now);
      for (const allocation of allocations) {
        const debt = this.sqlite.prepare('SELECT uuid FROM customer_account_transactions WHERE uuid = ? AND customer_uuid = ? AND transaction_type = ?').get(allocation.accountTransactionUuid, customerUuid, 'credit_sale');
        if (!debt) throw new Error('Payment allocation must reference this customer\'s credit sale');
        this.sqlite.prepare('INSERT INTO customer_payment_allocations (uuid, payment_uuid, account_transaction_uuid, amount, created_at) VALUES (?, ?, ?, ?, ?)')
          .run(randomUUID(), payment.uuid, allocation.accountTransactionUuid, allocation.amount, now);
      }
      const entry = this.insertLedgerTransaction({ customerUuid, transactionType: 'payment', amount: this.format(-amount), reference: input.reference ?? null, metadataJson: JSON.stringify({ paymentUuid: payment.uuid }), createdAt: now });
      this.updateBalanceCache(customerUuid, this.amount(account.currentBalance) - amount, now);
      return { payment, entry };
    })();

    this.auditLogs.record({ action: 'customer.payment_recorded', subjectType: 'customer_payment', subjectUuid: result.payment.uuid, metadata: { customerUuid, amount: input.amount } });
    this.syncQueue.enqueue({ aggregateType: 'customer_payment', aggregateUuid: result.payment.uuid, payload: result.payment, idempotencyKey: `customer-payment:${result.payment.uuid}` });
    return result.payment;
  }

  async approveCredit(customerUuid: string, input: { requestedAmount: string; approvedByUserUuid: string; reason: string }) {
    this.requireCustomer(customerUuid);
    const approval = { uuid: randomUUID(), customerUuid, requestedAmount: input.requestedAmount, approvedAmount: input.requestedAmount, approvedByUserUuid: input.approvedByUserUuid, reason: input.reason, status: 'approved', createdAt: new Date().toISOString() };
    this.sqlite.prepare('INSERT INTO credit_approvals (uuid, customer_uuid, requested_amount, approved_amount, approved_by_user_uuid, reason, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
      .run(approval.uuid, approval.customerUuid, approval.requestedAmount, approval.approvedAmount, approval.approvedByUserUuid, approval.reason, approval.status, approval.createdAt);
    this.auditLogs.record({ action: 'customer.credit_approved', subjectType: 'credit_approval', subjectUuid: approval.uuid, metadata: { customerUuid, amount: approval.approvedAmount } });
    return approval;
  }

  async writeOffDebt(customerUuid: string, input: { accountTransactionUuid: string; amount: string; approvedByUserUuid: string; reason: string }) {
    const result = this.sqlite.transaction(() => {
      this.requireCustomer(customerUuid);
      const account = this.requireAccount(customerUuid);
      const debt = this.sqlite.prepare("SELECT uuid FROM customer_account_transactions WHERE uuid = ? AND customer_uuid = ? AND transaction_type = 'credit_sale'").get(input.accountTransactionUuid, customerUuid);
      if (!debt) throw new Error('Debt write-off must reference this customer\'s credit sale');
      const now = new Date().toISOString();
      const writeOff = { uuid: randomUUID(), customerUuid, accountTransactionUuid: input.accountTransactionUuid, amount: input.amount, approvedByUserUuid: input.approvedByUserUuid, reason: input.reason, createdAt: now };
      this.sqlite.prepare('INSERT INTO debt_write_offs (uuid, customer_uuid, account_transaction_uuid, amount, approved_by_user_uuid, reason, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
        .run(writeOff.uuid, customerUuid, writeOff.accountTransactionUuid, writeOff.amount, writeOff.approvedByUserUuid, writeOff.reason, now);
      const entry = this.insertLedgerTransaction({ customerUuid, transactionType: 'write_off', amount: this.format(-this.amount(input.amount)), reference: writeOff.uuid, metadataJson: JSON.stringify({ accountTransactionUuid: input.accountTransactionUuid, approvedByUserUuid: input.approvedByUserUuid, reason: input.reason }), createdAt: now });
      this.updateBalanceCache(customerUuid, this.amount(account.currentBalance) - this.amount(input.amount), now);
      return { writeOff, entry };
    })();

    this.auditLogs.record({ action: 'customer.debt_written_off', subjectType: 'debt_write_off', subjectUuid: result.writeOff.uuid, metadata: { customerUuid, amount: input.amount, approvedByUserUuid: input.approvedByUserUuid } });
    this.syncQueue.enqueue({ aggregateType: 'debt_write_off', aggregateUuid: result.writeOff.uuid, payload: result.writeOff, idempotencyKey: `debt-write-off:${result.writeOff.uuid}` });
    return result.writeOff;
  }

  async recordLedgerCorrection(customerUuid: string, input: { transactionType: 'refund' | 'adjustment' | 'reversal'; amount: string; reference?: string; reversalOfUuid?: string }) {
    const transaction = this.sqlite.transaction(() => {
      this.requireCustomer(customerUuid);
      const account = this.requireAccount(customerUuid);
      const amount = this.amount(input.amount);
      if (input.transactionType === 'reversal' && !input.reversalOfUuid) throw new Error('A reversal must reference the original ledger transaction');
      if (input.reversalOfUuid) {
        const original = this.sqlite.prepare('SELECT uuid FROM customer_account_transactions WHERE uuid = ? AND customer_uuid = ?').get(input.reversalOfUuid, customerUuid);
        if (!original) throw new Error('Original ledger transaction not found');
      }
      const now = new Date().toISOString();
      const entry = this.insertLedgerTransaction({ customerUuid, transactionType: input.transactionType, amount: this.format(amount), reference: input.reference ?? null, reversalOfUuid: input.reversalOfUuid ?? null, createdAt: now });
      this.updateBalanceCache(customerUuid, this.amount(account.currentBalance) + amount, now);
      return entry;
    })();

    this.auditLogs.record({ action: `customer.${input.transactionType}_recorded`, subjectType: 'customer_account_transaction', subjectUuid: transaction.uuid, metadata: { customerUuid, amount: input.amount, reversalOfUuid: input.reversalOfUuid } });
    this.syncQueue.enqueue({ aggregateType: 'customer_account_transaction', aggregateUuid: transaction.uuid, payload: transaction, idempotencyKey: `customer-account-transaction:${transaction.uuid}` });
    return transaction;
  }

  async createDebtReminder(customerUuid: string, input: { reminderAt: string; channel: string; accountTransactionUuid?: string }) {
    this.requireCustomer(customerUuid);
    const reminder = { uuid: randomUUID(), customerUuid, accountTransactionUuid: input.accountTransactionUuid ?? null, reminderAt: input.reminderAt, channel: input.channel, status: 'scheduled', createdAt: new Date().toISOString() };
    this.sqlite.prepare('INSERT INTO debt_reminders (uuid, customer_uuid, account_transaction_uuid, reminder_at, channel, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
      .run(reminder.uuid, reminder.customerUuid, reminder.accountTransactionUuid, reminder.reminderAt, reminder.channel, reminder.status, reminder.createdAt);
    this.auditLogs.record({ action: 'customer.debt_reminder_created', subjectType: 'debt_reminder', subjectUuid: reminder.uuid, metadata: { customerUuid, channel: reminder.channel } });
    return reminder;
  }

  async setCustomerStatus(customerUuid: string, status: 'active' | 'blocked') {
    this.requireCustomer(customerUuid);
    const updatedAt = new Date().toISOString();
    this.sqlite.prepare('UPDATE customers SET status = ?, updated_at = ? WHERE uuid = ?').run(status, updatedAt, customerUuid);
    this.auditLogs.record({ action: `customer.${status}`, subjectType: 'customer', subjectUuid: customerUuid });
    return { customerUuid, status, updatedAt };
  }

  async setCreditSettings(customerUuid: string, input: { creditLimit: string; additionalCreditAllowed: boolean }) {
    this.requireCustomer(customerUuid);
    const updatedAt = new Date().toISOString();
    this.sqlite.prepare('UPDATE customer_credit_limits SET credit_limit = ?, additional_credit_allowed = ?, updated_at = ? WHERE customer_uuid = ?')
      .run(input.creditLimit, input.additionalCreditAllowed ? '1' : '0', updatedAt, customerUuid);
    this.auditLogs.record({ action: 'customer.credit_settings_updated', subjectType: 'customer', subjectUuid: customerUuid, metadata: input });
    return { customerUuid, ...input, updatedAt };
  }

  getAccount(customerUuid: string) {
    return this.sqlite.prepare('SELECT uuid, customer_uuid AS customerUuid, current_balance AS currentBalance, created_at AS createdAt, updated_at AS updatedAt FROM customer_accounts WHERE customer_uuid = ?').get(customerUuid) as { uuid: string; customerUuid: string; currentBalance: string; createdAt: string; updatedAt: string } | undefined;
  }

  getStatement(customerUuid: string) {
    const transactions = this.sqlite.prepare('SELECT uuid, customer_uuid AS customerUuid, transaction_type AS transactionType, amount, due_date AS dueDate, reference, reversal_of_uuid AS reversalOfUuid, metadata_json AS metadataJson, created_at AS createdAt FROM customer_account_transactions WHERE customer_uuid = ? ORDER BY created_at, id').all(customerUuid) as CustomerLedgerTransaction[];
    return { customerUuid, balance: this.format(transactions.reduce((total, transaction) => total + this.amount(transaction.amount), 0)), overdue: this.hasOverdueBalance(customerUuid), transactions };
  }

  private requireCustomer(customerUuid: string) {
    const customer = this.sqlite.prepare('SELECT uuid, status FROM customers WHERE uuid = ?').get(customerUuid) as { uuid: string; status: string } | undefined;
    if (!customer) throw new Error('Customer not found');
    return customer;
  }

  private requireAccount(customerUuid: string) {
    const account = this.getAccount(customerUuid);
    if (!account) throw new Error('Customer account not found');
    return account;
  }

  private hasOverdueBalance(customerUuid: string): boolean {
    const row = this.sqlite.prepare(`SELECT EXISTS(SELECT 1 FROM customer_account_transactions sale WHERE sale.customer_uuid = ? AND sale.transaction_type = 'credit_sale' AND date(sale.due_date) < date('now') AND CAST(sale.amount AS REAL) > COALESCE((SELECT SUM(CAST(allocation.amount AS REAL)) FROM customer_payment_allocations allocation WHERE allocation.account_transaction_uuid = sale.uuid), 0)) AS overdue`).get(customerUuid) as { overdue: number };
    return row.overdue === 1;
  }

  private hasApprovedCredit(approvalUuid: string | undefined, customerUuid: string, requestedExposure: number): boolean {
    if (!approvalUuid) return false;
    const approval = this.sqlite.prepare('SELECT approved_amount AS approvedAmount FROM credit_approvals WHERE uuid = ? AND customer_uuid = ? AND status = ?').get(approvalUuid, customerUuid, 'approved') as { approvedAmount: string } | undefined;
    return approval !== undefined && this.amount(approval.approvedAmount) >= requestedExposure;
  }

  private insertLedgerTransaction(input: Omit<CustomerLedgerTransaction, 'uuid' | 'dueDate' | 'reference' | 'reversalOfUuid' | 'metadataJson'> & Partial<Pick<CustomerLedgerTransaction, 'dueDate' | 'reference' | 'reversalOfUuid' | 'metadataJson'>>): CustomerLedgerTransaction {
    const transaction = { uuid: randomUUID(), customerUuid: input.customerUuid, transactionType: input.transactionType, amount: input.amount, dueDate: input.dueDate ?? null, reference: input.reference ?? null, reversalOfUuid: input.reversalOfUuid ?? null, metadataJson: input.metadataJson ?? null, createdAt: input.createdAt };
    this.sqlite.prepare('INSERT INTO customer_account_transactions (uuid, customer_uuid, transaction_type, amount, due_date, reference, reversal_of_uuid, metadata_json, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
      .run(transaction.uuid, transaction.customerUuid, transaction.transactionType, transaction.amount, transaction.dueDate, transaction.reference, transaction.reversalOfUuid, transaction.metadataJson, transaction.createdAt);
    return transaction;
  }

  private updateBalanceCache(customerUuid: string, balance: number, updatedAt: string) {
    this.sqlite.prepare('UPDATE customer_accounts SET current_balance = ?, updated_at = ? WHERE customer_uuid = ?').run(this.format(balance), updatedAt, customerUuid);
  }

  private amount(value: string): number { return Number(value); }
  private format(value: number): string { return String(value); }
}
