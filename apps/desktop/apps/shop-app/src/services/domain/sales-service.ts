import { randomUUID } from 'node:crypto';
import type Database from 'better-sqlite3';
import type { AuditLogRepository } from '../../repositories/audit-log-repository';
import type { SyncQueueRepository } from '../../repositories/sync-queue-repository';

export class SalesService {
  constructor(private readonly sqlite: Database, private readonly auditLogs: AuditLogRepository, private readonly syncQueue: SyncQueueRepository) {}

  async createPaymentMethod(input: { code: string; name: string; type: 'cash' | 'bank_transfer' | 'pos_terminal' | 'mobile_money' | 'credit' }) {
    const now = new Date().toISOString(); const method = { uuid: randomUUID(), ...input, active: '1', createdAt: now, updatedAt: now };
    this.sqlite.prepare('INSERT INTO payment_methods (uuid, code, name, type, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)').run(method.uuid, method.code, method.name, method.type, method.active, now, now); return method;
  }

  async completeSale(input: {
    idempotencyKey: string;
    shiftUuid: string;
    warehouseUuid: string;
    customerUuid?: string;
    items: Array<{ productUuid: string; unitUuid: string; quantity: string; unitPrice: string }>;
    payments: Array<{
      paymentMethodUuid: string;
      amount: string;
      amountTendered?: string;
      reference?: string;
      transferVerified?: boolean;
      creditAmount?: string;
      customerCreditSelection?: string;
    }>;
    discountAmount?: string;
    receiptFormat?: 'thermal' | 'a4';
  }) {
    const existing = this.sqlite.prepare('SELECT uuid, sale_number AS saleNumber, status, total_amount AS totalAmount FROM sales WHERE idempotency_key = ?').get(input.idempotencyKey); if (existing) return existing;
    if (input.items.length === 0 || input.payments.length === 0) throw new Error('A sale requires items and payments');
    if (input.items.some((item) => !Number.isFinite(this.num(item.quantity)) || !Number.isFinite(this.num(item.unitPrice)) || this.num(item.quantity) <= 0 || this.num(item.unitPrice) < 0)) throw new Error('Sale quantities must be positive and prices cannot be negative');
    if (input.payments.some((payment) => !Number.isFinite(this.num(payment.amount)) || this.num(payment.amount) < 0)) throw new Error('Sale payments cannot be negative');
    const result = this.sqlite.transaction(() => {
      const shift = this.sqlite.prepare("SELECT uuid FROM cashier_shifts WHERE uuid = ? AND status = 'open'").get(input.shiftUuid); if (!shift) throw new Error('Active cashier shift required');
      const subtotal = input.items.reduce((total, item) => total + this.num(item.quantity) * this.num(item.unitPrice), 0); const discount = this.num(input.discountAmount ?? '0'); if (!Number.isFinite(discount) || discount < 0 || discount > subtotal) throw new Error('Sale discount must be between zero and the subtotal'); const total = subtotal - discount; const paid = input.payments.reduce((sum, payment) => sum + this.num(payment.amount), 0); if (paid !== total) throw new Error('Sale payments must equal the sale total');
      for (const item of input.items) { const product = this.sqlite.prepare("SELECT active, min_selling_price AS minSellingPrice FROM products WHERE uuid = ?").get(item.productUuid) as { active: string; minSellingPrice: string | null } | undefined; if (!product || product.active !== '1') throw new Error('Product is not active'); if (product.minSellingPrice && this.num(item.unitPrice) < this.num(product.minSellingPrice)) throw new Error('Selling below minimum price requires approval'); const stock = this.sqlite.prepare('SELECT uuid, quantity FROM stocks WHERE product_uuid = ? AND warehouse_uuid = ? AND unit_uuid = ?').get(item.productUuid, input.warehouseUuid, item.unitUuid) as { uuid: string; quantity: string } | undefined; if (!stock || this.num(stock.quantity) < this.num(item.quantity)) throw new Error('Insufficient stock'); }
      const now = new Date().toISOString(); const sale = { uuid: randomUUID(), saleNumber: `SALE-${Date.now()}`, idempotencyKey: input.idempotencyKey, status: 'completed', subtotal: this.format(subtotal), discountAmount: this.format(discount), totalAmount: this.format(total), createdAt: now };
      this.sqlite.prepare('INSERT INTO sales (uuid, sale_number, idempotency_key, shift_uuid, warehouse_uuid, customer_uuid, status, subtotal, discount_amount, total_amount, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)').run(sale.uuid, sale.saleNumber, sale.idempotencyKey, input.shiftUuid, input.warehouseUuid, input.customerUuid ?? null, sale.status, sale.subtotal, sale.discountAmount, sale.totalAmount, now);
      for (const item of input.items) { const itemUuid = randomUUID(); const lineTotal = this.format(this.num(item.quantity) * this.num(item.unitPrice)); this.sqlite.prepare('INSERT INTO sale_items (uuid, sale_uuid, product_uuid, unit_uuid, quantity, unit_price, line_total, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)').run(itemUuid, sale.uuid, item.productUuid, item.unitUuid, item.quantity, item.unitPrice, lineTotal, now); this.sqlite.prepare('UPDATE stocks SET quantity = CAST(quantity AS REAL) - ?, last_updated_at = ?, updated_at = ? WHERE product_uuid = ? AND warehouse_uuid = ? AND unit_uuid = ?').run(this.num(item.quantity), now, now, item.productUuid, input.warehouseUuid, item.unitUuid); this.sqlite.prepare('INSERT INTO stock_movements (uuid, product_uuid, from_warehouse_uuid, quantity_base, unit_uuid, movement_type, reference, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)').run(randomUUID(), item.productUuid, input.warehouseUuid, item.quantity, item.unitUuid, 'sale', sale.uuid, now); }
      for (const payment of input.payments) {
        const method = this.sqlite.prepare('SELECT type FROM payment_methods WHERE uuid = ? AND active = ?').get(payment.paymentMethodUuid, '1') as { type: string } | undefined;
        if (!method) throw new Error('Payment method not found');

        const amountTendered = typeof payment.amountTendered === 'string' ? payment.amountTendered : null;
        const transferVerified = typeof payment.transferVerified === 'boolean' ? (payment.transferVerified ? '1' : '0') : null;
        const creditAmount = typeof payment.creditAmount === 'string' ? payment.creditAmount : null;
        const customerCreditSelection = typeof payment.customerCreditSelection === 'string' ? payment.customerCreditSelection : null;

        this.sqlite.prepare('INSERT INTO sale_payments (uuid, sale_uuid, payment_method_uuid, amount, amount_tendered, reference, transfer_verified, credit_amount, customer_credit_selection, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
          .run(randomUUID(), sale.uuid, payment.paymentMethodUuid, payment.amount, amountTendered, payment.reference ?? null, transferVerified, creditAmount, customerCreditSelection, now);

        if (method.type === 'cash') {
          this.sqlite.prepare('INSERT INTO cashier_shift_payments (uuid, shift_uuid, type, amount, reference, created_at) VALUES (?, ?, ?, ?, ?, ?)').run(randomUUID(), input.shiftUuid, 'cash_sale', payment.amount, sale.uuid, now);
        }
      }
      if (discount > 0) this.sqlite.prepare('INSERT INTO sale_discounts (uuid, sale_uuid, amount, created_at) VALUES (?, ?, ?, ?)').run(randomUUID(), sale.uuid, sale.discountAmount, now);
      const receipt = { uuid: randomUUID(), saleUuid: sale.uuid, format: input.receiptFormat ?? 'thermal', receiptNumber: `RCP-${Date.now()}`, printCount: 0, createdAt: now }; this.sqlite.prepare('INSERT INTO receipts (uuid, sale_uuid, format, receipt_number, print_count, created_at) VALUES (?, ?, ?, ?, ?, ?)').run(receipt.uuid, receipt.saleUuid, receipt.format, receipt.receiptNumber, receipt.printCount, now); return { ...sale, receipt };
    })();
    this.auditLogs.record({ action: 'sale.completed', subjectType: 'sale', subjectUuid: result.uuid, metadata: { totalAmount: result.totalAmount } }); this.syncQueue.enqueue({ aggregateType: 'sale', aggregateUuid: result.uuid, payload: result, idempotencyKey: `sale:${result.uuid}` }); return result;
  }

  getReceiptDetails(receiptUuid: string) {
    const receipt = this.sqlite.prepare(`
      SELECT
        r.uuid,
        r.sale_uuid AS saleUuid,
        r.format,
        r.receipt_number AS receiptNumber,
        r.print_count AS printCount,
        r.created_at AS createdAt,
        s.sale_number AS saleNumber,
        s.customer_uuid AS customerUuid,
        s.total_amount AS totalAmount,
        s.subtotal,
        s.discount_amount AS discountAmount,
        s.created_at AS saleCreatedAt,
        cs.cashier_user_uuid AS cashierUserUuid,
        u.username AS cashierUsername,
        c.code AS customerCode,
        c.name AS customerName,
        c.phone AS customerPhone,
        ai.business_name AS businessName,
        ai.branch_name AS branchName,
        ai.device_name AS shopDeviceName
      FROM receipts r
      JOIN sales s ON s.uuid = r.sale_uuid
      LEFT JOIN cashier_shifts cs ON cs.uuid = s.shift_uuid
      LEFT JOIN users u ON u.uuid = cs.cashier_user_uuid
      LEFT JOIN customers c ON c.uuid = s.customer_uuid
      LEFT JOIN app_installation ai ON ai.id = 1
      WHERE r.uuid = ?
      LIMIT 1
    `).get(receiptUuid) as {
      uuid: string;
      saleUuid: string;
      format: string;
      receiptNumber: string;
      printCount: number;
      createdAt: string;
      saleNumber: string;
      customerUuid: string | null;
      totalAmount: string;
      subtotal: string;
      discountAmount: string;
      saleCreatedAt: string;
      cashierUserUuid: string | null;
      cashierUsername: string | null;
      customerCode: string | null;
      customerName: string | null;
      customerPhone: string | null;
      businessName: string | null;
      branchName: string | null;
      shopDeviceName: string | null;
    } | undefined;

    if (!receipt) {
      throw new Error('Receipt not found');
    }

    const items = this.sqlite.prepare(`
      SELECT
        si.uuid,
        si.product_uuid AS productUuid,
        p.sku,
        p.name,
        u.code AS unitCode,
        u.name AS unitName,
        si.quantity,
        si.unit_price AS unitPrice,
        si.line_total AS lineTotal
      FROM sale_items si
      JOIN products p ON p.uuid = si.product_uuid
      LEFT JOIN units u ON u.uuid = si.unit_uuid
      WHERE si.sale_uuid = ?
      ORDER BY si.id ASC
    `).all(receipt.saleUuid) as Array<{
      uuid: string;
      productUuid: string;
      sku: string | null;
      name: string;
      unitCode: string | null;
      unitName: string | null;
      quantity: string;
      unitPrice: string;
      lineTotal: string;
    }>;

    const payments = this.sqlite.prepare(`
      SELECT
        sp.uuid,
        pm.code AS paymentMethodCode,
        pm.name AS paymentMethodName,
        pm.type AS paymentMethodType,
        sp.amount,
        sp.amount_tendered AS amountTendered,
        sp.reference,
        sp.transfer_verified AS transferVerified,
        sp.credit_amount AS creditAmount,
        sp.customer_credit_selection AS customerCreditSelection
      FROM sale_payments sp
      JOIN payment_methods pm ON pm.uuid = sp.payment_method_uuid
      WHERE sp.sale_uuid = ?
      ORDER BY sp.id ASC
    `).all(receipt.saleUuid) as Array<{
      uuid: string;
      paymentMethodCode: string;
      paymentMethodName: string;
      paymentMethodType: string;
      amount: string;
      amountTendered: string | null;
      reference: string | null;
      transferVerified: string | null;
      creditAmount: string | null;
      customerCreditSelection: string | null;
    }>;

    const totalTendered = payments.reduce((sum, payment) => sum + this.num(payment.amountTendered ?? '0'), 0);
    const totalPaid = payments.reduce((sum, payment) => sum + this.num(payment.amount), 0);
    const totalAmount = this.num(receipt.totalAmount);
    const cashChange = Math.max(totalTendered - totalPaid, 0);
    const balance = Math.max(totalAmount - totalPaid, 0);

    return {
      ...receipt,
      receiptVerificationReference: receipt.receiptNumber,
      thankYouMessage: 'Thank you for choosing MarketLens.',
      items,
      payments: payments.map((payment) => ({
        ...payment,
        transferVerified: payment.transferVerified === '1',
      })),
      totals: {
        subtotal: receipt.subtotal,
        discountAmount: receipt.discountAmount,
        totalAmount: receipt.totalAmount,
        totalPaid: this.format(totalPaid),
        totalTendered: this.format(totalTendered),
        changeDue: this.format(cashChange),
        balance: this.format(balance),
      },
    };
  }

  searchSales(input: {
    saleNumber?: string;
    receiptNumber?: string;
    customer?: string;
    date?: string;
    cashier?: string;
    product?: string;
    limit?: number;
  }) {
    const saleNumber = input.saleNumber?.trim() ?? '';
    const receiptNumber = input.receiptNumber?.trim() ?? '';
    const customer = input.customer?.trim() ?? '';
    const date = input.date?.trim() ?? '';
    const cashier = input.cashier?.trim() ?? '';
    const product = input.product?.trim() ?? '';
    const limit = Math.max(1, Math.min(100, input.limit ?? 30));

    const likeSaleNumber = `%${saleNumber}%`;
    const likeReceiptNumber = `%${receiptNumber}%`;
    const likeCustomer = `%${customer}%`;
    const likeCashier = `%${cashier}%`;
    const likeProduct = `%${product}%`;

    const rows = this.sqlite.prepare(`
      SELECT
        s.uuid,
        s.sale_number AS saleNumber,
        s.status,
        s.subtotal,
        s.discount_amount AS discountAmount,
        s.total_amount AS totalAmount,
        s.created_at AS createdAt,
        s.shift_uuid AS shiftUuid,
        s.warehouse_uuid AS warehouseUuid,
        r.uuid AS receiptUuid,
        r.receipt_number AS receiptNumber,
        c.uuid AS customerUuid,
        c.code AS customerCode,
        c.name AS customerName,
        u.uuid AS cashierUserUuid,
        u.username AS cashierUsername,
        sc.reason AS cancellationReason,
        sc.approval_state AS cancellationApprovalState,
        sc.refund_state AS cancellationRefundState,
        sc.refund_method AS cancellationRefundMethod
      FROM sales s
      LEFT JOIN receipts r ON r.sale_uuid = s.uuid
      LEFT JOIN customers c ON c.uuid = s.customer_uuid
      LEFT JOIN cashier_shifts cs ON cs.uuid = s.shift_uuid
      LEFT JOIN users u ON u.uuid = cs.cashier_user_uuid
      LEFT JOIN sale_cancellations sc ON sc.sale_uuid = s.uuid
      WHERE
        (? = '' OR s.sale_number LIKE ?)
        AND (? = '' OR r.receipt_number LIKE ?)
        AND (? = '' OR c.name LIKE ? OR c.code LIKE ?)
        AND (? = '' OR date(s.created_at) = date(?))
        AND (? = '' OR u.username LIKE ?)
        AND (
          ? = '' OR EXISTS (
            SELECT 1
            FROM sale_items si
            JOIN products p ON p.uuid = si.product_uuid
            WHERE si.sale_uuid = s.uuid
              AND (p.name LIKE ? OR p.sku LIKE ?)
          )
        )
      ORDER BY s.created_at DESC
      LIMIT ?
    `).all(
      saleNumber, likeSaleNumber,
      receiptNumber, likeReceiptNumber,
      customer, likeCustomer, likeCustomer,
      date, date,
      cashier, likeCashier,
      product, likeProduct, likeProduct,
      limit,
    ) as Array<{
      uuid: string;
      saleNumber: string;
      status: string;
      subtotal: string;
      discountAmount: string;
      totalAmount: string;
      createdAt: string;
      shiftUuid: string;
      warehouseUuid: string;
      receiptUuid: string | null;
      receiptNumber: string | null;
      customerUuid: string | null;
      customerCode: string | null;
      customerName: string | null;
      cashierUserUuid: string | null;
      cashierUsername: string | null;
      cancellationReason: string | null;
      cancellationApprovalState: string | null;
      cancellationRefundState: string | null;
      cancellationRefundMethod: string | null;
    }>;

    return rows.map((row) => {
      const saleItems = this.sqlite.prepare(`
        SELECT
          si.uuid,
          si.product_uuid AS productUuid,
          p.sku,
          p.name,
          si.unit_uuid AS unitUuid,
          u.code AS unitCode,
          u.name AS unitName,
          si.quantity,
          si.unit_price AS unitPrice,
          si.line_total AS lineTotal,
          COALESCE((
            SELECT SUM(CAST(sri.quantity AS REAL))
            FROM sale_return_items sri
            JOIN sale_returns sr ON sr.uuid = sri.sale_return_uuid
            WHERE sri.sale_item_uuid = si.uuid
          ), 0) AS returnedQuantity
        FROM sale_items si
        JOIN products p ON p.uuid = si.product_uuid
        LEFT JOIN units u ON u.uuid = si.unit_uuid
        WHERE si.sale_uuid = ?
        ORDER BY si.id ASC
      `).all(row.uuid) as Array<{
        uuid: string;
        productUuid: string;
        sku: string;
        name: string;
        unitUuid: string;
        unitCode: string | null;
        unitName: string | null;
        quantity: string;
        unitPrice: string;
        lineTotal: string;
        returnedQuantity: number;
      }>;

      const returnSummary = this.sqlite.prepare(`
        SELECT
          COUNT(*) AS returnCount,
          COALESCE(SUM(CAST(refund_amount AS REAL)), 0) AS refundAmount,
          SUM(CASE WHEN status = 'pending_approval' THEN 1 ELSE 0 END) AS pendingApprovalCount,
          SUM(CASE WHEN status = 'refunded' THEN 1 ELSE 0 END) AS refundedCount
        FROM sale_returns
        WHERE sale_uuid = ?
      `).get(row.uuid) as { returnCount: number; refundAmount: number; pendingApprovalCount: number; refundedCount: number };

      const payments = this.sqlite.prepare(`
        SELECT
          sp.uuid,
          pm.name AS paymentMethodName,
          pm.type AS paymentMethodType,
          sp.amount,
          sp.reference
        FROM sale_payments sp
        JOIN payment_methods pm ON pm.uuid = sp.payment_method_uuid
        WHERE sp.sale_uuid = ?
        ORDER BY sp.id ASC
      `).all(row.uuid) as Array<{ uuid: string; paymentMethodName: string; paymentMethodType: string; amount: string; reference: string | null }>;

      return {
        ...row,
        items: saleItems,
        payments,
        cancellation: row.cancellationReason ? {
          reason: row.cancellationReason,
          approvalState: row.cancellationApprovalState ?? 'pending',
          refundState: row.cancellationRefundState ?? 'pending',
          refundMethod: row.cancellationRefundMethod,
        } : null,
        returnSummary,
      };
    });
  }

  createSaleReturn(input: {
    saleUuid: string;
    returnType: 'full' | 'partial';
    reason: string;
    items?: Array<{ saleItemUuid: string; quantity: string }>;
    refundMethod: 'cash' | 'bank_transfer' | 'pos_terminal' | 'mobile_money' | 'credit_note';
    approvalRequested?: boolean;
    approvedByUserUuid?: string;
    requestedByUserUuid?: string;
  }) {
    const sale = this.sqlite.prepare(`
      SELECT uuid, sale_number AS saleNumber, status, shift_uuid AS shiftUuid, warehouse_uuid AS warehouseUuid
      FROM sales
      WHERE uuid = ?
    `).get(input.saleUuid) as { uuid: string; saleNumber: string; status: string; shiftUuid: string; warehouseUuid: string } | undefined;
    if (!sale) throw new Error('Sale not found');
    if (sale.status !== 'completed') throw new Error('Only completed sales can be returned');

    const cancellation = this.sqlite.prepare('SELECT uuid FROM sale_cancellations WHERE sale_uuid = ? LIMIT 1').get(input.saleUuid) as { uuid: string } | undefined;
    if (cancellation) throw new Error('Cancelled sales cannot be returned');

    const saleItems = this.sqlite.prepare(`
      SELECT
        si.uuid,
        si.product_uuid AS productUuid,
        si.unit_uuid AS unitUuid,
        si.quantity,
        si.unit_price AS unitPrice,
        si.line_total AS lineTotal,
        COALESCE((
          SELECT SUM(CAST(sri.quantity AS REAL))
          FROM sale_return_items sri
          JOIN sale_returns sr ON sr.uuid = sri.sale_return_uuid
          WHERE sri.sale_item_uuid = si.uuid
        ), 0) AS returnedQuantity
      FROM sale_items si
      WHERE si.sale_uuid = ?
      ORDER BY si.id ASC
    `).all(input.saleUuid) as Array<{
      uuid: string;
      productUuid: string;
      unitUuid: string;
      quantity: string;
      unitPrice: string;
      lineTotal: string;
      returnedQuantity: number;
    }>;
    if (saleItems.length === 0) throw new Error('Sale has no returnable items');

    const returnItems = input.returnType === 'full'
      ? saleItems
        .map((item) => ({ saleItemUuid: item.uuid, quantity: this.format(Math.max(this.num(item.quantity) - item.returnedQuantity, 0)) }))
        .filter((item) => this.num(item.quantity) > 0)
      : (input.items ?? []);

    if (returnItems.length === 0) throw new Error('No return items selected');

    const itemMap = new Map(saleItems.map((item) => [item.uuid, item]));
    let refundAmountNumber = 0;
    const normalizedItems = returnItems.map((item) => {
      const saleItem = itemMap.get(item.saleItemUuid);
      if (!saleItem) throw new Error('Return item does not belong to sale');

      const quantity = this.num(item.quantity);
      if (!Number.isFinite(quantity) || quantity <= 0) throw new Error('Return quantity must be greater than zero');

      const remaining = Math.max(this.num(saleItem.quantity) - saleItem.returnedQuantity, 0);
      if (quantity > remaining) throw new Error('Return quantity exceeds remaining sold quantity');

      const unitPrice = this.num(saleItem.unitPrice);
      const amount = quantity * unitPrice;
      refundAmountNumber += amount;

      return {
        saleItem,
        quantity: this.format(quantity),
        amount: this.format(amount),
      };
    });

    const approvalRequested = input.approvalRequested ? '1' : '0';
    const status = input.approvalRequested && !input.approvedByUserUuid ? 'pending_approval' : 'refunded';
    const refundAmount = this.format(refundAmountNumber);
    const now = new Date().toISOString();
    const saleReturnUuid = randomUUID();

    this.sqlite.transaction(() => {
      this.sqlite.prepare('INSERT INTO sale_returns (uuid, sale_uuid, status, refund_amount, reason, refund_method, approval_requested, approved_by_user_uuid, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
        .run(saleReturnUuid, input.saleUuid, status, refundAmount, input.reason, input.refundMethod, approvalRequested, input.approvedByUserUuid ?? null, now);

      for (const item of normalizedItems) {
        this.sqlite.prepare('INSERT INTO sale_return_items (uuid, sale_return_uuid, sale_item_uuid, quantity, amount, created_at) VALUES (?, ?, ?, ?, ?, ?)')
          .run(randomUUID(), saleReturnUuid, item.saleItem.uuid, item.quantity, item.amount, now);

        this.sqlite.prepare('UPDATE stocks SET quantity = CAST(quantity AS REAL) + ?, last_updated_at = ?, updated_at = ? WHERE product_uuid = ? AND warehouse_uuid = ? AND unit_uuid = ?')
          .run(this.num(item.quantity), now, now, item.saleItem.productUuid, sale.warehouseUuid, item.saleItem.unitUuid);

        this.sqlite.prepare('INSERT INTO stock_movements (uuid, product_uuid, to_warehouse_uuid, quantity_base, unit_uuid, movement_type, reference, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
          .run(randomUUID(), item.saleItem.productUuid, sale.warehouseUuid, item.quantity, item.saleItem.unitUuid, 'sale_return', saleReturnUuid, now);
      }

      if (status === 'refunded' && input.refundMethod === 'cash') {
        this.sqlite.prepare('INSERT INTO cashier_shift_payments (uuid, shift_uuid, type, amount, reference, created_at) VALUES (?, ?, ?, ?, ?, ?)')
          .run(randomUUID(), sale.shiftUuid, 'cash_refund', refundAmount, saleReturnUuid, now);
      }
    })();

    this.auditLogs.record({
      action: 'sale.return_created',
      subjectType: 'sale_return',
      subjectUuid: saleReturnUuid,
      metadata: {
        saleUuid: input.saleUuid,
        saleNumber: sale.saleNumber,
        returnType: input.returnType,
        reason: input.reason,
        refundMethod: input.refundMethod,
        refundAmount,
        approvalRequested: input.approvalRequested ?? false,
        approvedByUserUuid: input.approvedByUserUuid ?? null,
        requestedByUserUuid: input.requestedByUserUuid ?? null,
      },
    });
    this.syncQueue.enqueue({ aggregateType: 'sale_return', aggregateUuid: saleReturnUuid, payload: { saleReturnUuid, saleUuid: input.saleUuid, status, refundAmount }, idempotencyKey: `sale_return:${saleReturnUuid}` });

    return {
      uuid: saleReturnUuid,
      saleUuid: input.saleUuid,
      saleNumber: sale.saleNumber,
      status,
      approvalState: status === 'pending_approval' ? 'pending' : (input.approvedByUserUuid ? 'approved' : 'not_required'),
      refundState: status === 'pending_approval' ? 'pending_approval' : 'refunded',
      reason: input.reason,
      refundMethod: input.refundMethod,
      refundAmount,
      createdAt: now,
    };
  }

  getSaleReturnDetails(saleReturnUuid: string) {
    const saleReturn = this.sqlite.prepare(`
      SELECT
        sr.uuid,
        sr.sale_uuid AS saleUuid,
        sr.status,
        sr.refund_amount AS refundAmount,
        sr.reason,
        sr.refund_method AS refundMethod,
        sr.approval_requested AS approvalRequested,
        sr.approved_by_user_uuid AS approvedByUserUuid,
        sr.created_at AS createdAt,
        s.sale_number AS saleNumber,
        s.created_at AS saleCreatedAt,
        s.customer_uuid AS customerUuid,
        c.code AS customerCode,
        c.name AS customerName,
        cs.cashier_user_uuid AS cashierUserUuid,
        u.username AS cashierUsername,
        ai.business_name AS businessName,
        ai.branch_name AS branchName
      FROM sale_returns sr
      JOIN sales s ON s.uuid = sr.sale_uuid
      LEFT JOIN customers c ON c.uuid = s.customer_uuid
      LEFT JOIN cashier_shifts cs ON cs.uuid = s.shift_uuid
      LEFT JOIN users u ON u.uuid = cs.cashier_user_uuid
      LEFT JOIN app_installation ai ON ai.id = 1
      WHERE sr.uuid = ?
      LIMIT 1
    `).get(saleReturnUuid) as {
      uuid: string;
      saleUuid: string;
      status: string;
      refundAmount: string;
      reason: string;
      refundMethod: string | null;
      approvalRequested: string | null;
      approvedByUserUuid: string | null;
      createdAt: string;
      saleNumber: string;
      saleCreatedAt: string;
      customerUuid: string | null;
      customerCode: string | null;
      customerName: string | null;
      cashierUserUuid: string | null;
      cashierUsername: string | null;
      businessName: string | null;
      branchName: string | null;
    } | undefined;
    if (!saleReturn) throw new Error('Sale return not found');

    const items = this.sqlite.prepare(`
      SELECT
        sri.uuid,
        sri.quantity,
        sri.amount,
        si.product_uuid AS productUuid,
        p.sku,
        p.name,
        u.code AS unitCode,
        u.name AS unitName
      FROM sale_return_items sri
      JOIN sale_items si ON si.uuid = sri.sale_item_uuid
      JOIN products p ON p.uuid = si.product_uuid
      LEFT JOIN units u ON u.uuid = si.unit_uuid
      WHERE sri.sale_return_uuid = ?
      ORDER BY sri.id ASC
    `).all(saleReturnUuid) as Array<{
      uuid: string;
      quantity: string;
      amount: string;
      productUuid: string;
      sku: string | null;
      name: string;
      unitCode: string | null;
      unitName: string | null;
    }>;

    return {
      ...saleReturn,
      receiptVerificationReference: `RET-${saleReturn.uuid.slice(0, 8).toUpperCase()}`,
      thankYouMessage: 'Return processed by MarketLens.',
      items,
      totals: {
        refundAmount: saleReturn.refundAmount,
      },
      approvalState: saleReturn.status === 'pending_approval' ? 'pending' : (saleReturn.approvedByUserUuid ? 'approved' : 'not_required'),
      refundState: saleReturn.status === 'pending_approval' ? 'pending_approval' : 'refunded',
    };
  }

  cancelSale(input: {
    saleUuid: string;
    reason: string;
    refundMethod: 'cash' | 'bank_transfer' | 'pos_terminal' | 'mobile_money' | 'credit_note';
    approvalRequested?: boolean;
    approvedByUserUuid?: string;
    refundState?: 'pending' | 'refunded';
    requestedByUserUuid?: string;
  }) {
    const sale = this.sqlite.prepare('SELECT uuid, sale_number AS saleNumber, status FROM sales WHERE uuid = ?').get(input.saleUuid) as { uuid: string; saleNumber: string; status: string } | undefined;
    if (!sale) throw new Error('Sale not found');
    if (sale.status !== 'completed') throw new Error('Only completed sales can be cancelled');

    const existingCancellation = this.sqlite.prepare('SELECT uuid FROM sale_cancellations WHERE sale_uuid = ? LIMIT 1').get(input.saleUuid) as { uuid: string } | undefined;
    if (existingCancellation) throw new Error('Sale is already cancelled');

    const existingReturns = this.sqlite.prepare('SELECT COUNT(*) AS count FROM sale_returns WHERE sale_uuid = ?').get(input.saleUuid) as { count: number };
    if (existingReturns.count > 0) throw new Error('Sales with returns cannot be cancelled');

    if (!input.approvalRequested && !input.approvedByUserUuid) {
      throw new Error('Cancellation requires approval or must be submitted as an approval request');
    }

    const approvalState = input.approvedByUserUuid ? 'approved' : 'pending';
    const refundState = approvalState === 'pending' ? 'pending' : (input.refundState ?? 'pending');
    const cancellationUuid = randomUUID();
    const now = new Date().toISOString();

    this.sqlite.prepare('INSERT INTO sale_cancellations (uuid, sale_uuid, reason, approved_by_user_uuid, approval_state, refund_state, refund_method, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
      .run(cancellationUuid, input.saleUuid, input.reason, input.approvedByUserUuid ?? null, approvalState, refundState, input.refundMethod, now);

    this.auditLogs.record({
      action: 'sale.cancelled',
      subjectType: 'sale_cancellation',
      subjectUuid: cancellationUuid,
      metadata: {
        saleUuid: input.saleUuid,
        saleNumber: sale.saleNumber,
        reason: input.reason,
        approvalState,
        refundState,
        refundMethod: input.refundMethod,
        approvalRequested: input.approvalRequested ?? false,
        approvedByUserUuid: input.approvedByUserUuid ?? null,
        requestedByUserUuid: input.requestedByUserUuid ?? null,
      },
    });
    this.syncQueue.enqueue({ aggregateType: 'sale_cancellation', aggregateUuid: cancellationUuid, payload: { saleUuid: input.saleUuid, approvalState, refundState }, idempotencyKey: `sale_cancellation:${cancellationUuid}` });

    return {
      uuid: cancellationUuid,
      saleUuid: input.saleUuid,
      saleNumber: sale.saleNumber,
      reason: input.reason,
      approvalState,
      refundState,
      refundMethod: input.refundMethod,
      createdAt: now,
    };
  }

  async reprintReceipt(receiptUuid: string, input: { reason: string; approvedByUserUuid?: string }) {
    const receipt = this.sqlite.prepare('SELECT uuid, print_count AS printCount FROM receipts WHERE uuid = ?').get(receiptUuid) as { uuid: string; printCount: number } | undefined;
    if (!receipt) throw new Error('Receipt not found');

    const now = new Date().toISOString();
    this.sqlite.transaction(() => {
      this.sqlite.prepare('INSERT INTO receipt_reprints (uuid, receipt_uuid, reason, approved_by_user_uuid, created_at) VALUES (?, ?, ?, ?, ?)').run(randomUUID(), receiptUuid, input.reason, input.approvedByUserUuid ?? null, now);
      this.sqlite.prepare('UPDATE receipts SET print_count = print_count + 1 WHERE uuid = ?').run(receiptUuid);
    })();

    this.auditLogs.record({
      action: 'receipt.reprinted',
      subjectType: 'receipt',
      subjectUuid: receiptUuid,
      metadata: {
        reason: input.reason,
        approvedByUserUuid: input.approvedByUserUuid ?? null,
        printCount: receipt.printCount + 1,
      },
    });

    return { receiptUuid, printCount: receipt.printCount + 1 };
  }
  private num(value: string) { return Number(value); } private format(value: number) { return String(value); }
}
