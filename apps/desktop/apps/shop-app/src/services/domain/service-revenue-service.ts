import { randomUUID } from 'node:crypto';
import type Database from 'better-sqlite3';
import type { AuditLogRepository } from '../../repositories/audit-log-repository';
import type { SyncQueueRepository } from '../../repositories/sync-queue-repository';

type RevenueRuleBasis =
  | 'gross_amount'
  | 'net_after_discount'
  | 'net_after_direct_expenses'
  | 'net_after_discount_and_direct_expenses'
  | 'fixed_amount';

type ServiceOrderState = 'pending' | 'assigned' | 'in_progress' | 'completed' | 'cancelled' | 'refunded';

type RuleComponentType = 'staff' | 'business' | 'expense_reserve' | 'partner' | 'other';
type StaffLedgerType = 'service_share' | 'bonus' | 'adjustment' | 'deduction' | 'settlement_payment' | 'reversal';

export class ServiceRevenueService {
  constructor(private readonly sqlite: Database, private readonly auditLogs: AuditLogRepository, private readonly syncQueue: SyncQueueRepository) {}

  createCategory(input: { name: string }) {
    this.assertEnabled();
    return this.insert('service_categories', { name: input.name, active: '1' });
  }

  createService(input: { categoryUuid: string; code: string; name: string }) {
    this.assertEnabled();
    return this.insert('services', { categoryUuid: input.categoryUuid, code: input.code, name: input.name, active: '1' });
  }

  setPrice(input: { serviceUuid: string; branchUuid?: string; amount: string }) {
    this.assertEnabled();
    return this.insert('service_prices', { serviceUuid: input.serviceUuid, branchUuid: input.branchUuid ?? null, amount: input.amount, active: '1' });
  }

  createRule(input: {
    serviceUuid?: string;
    branchUuid?: string;
    basis: RevenueRuleBasis;
    fixedAmount?: string;
    requiresApproval?: boolean;
    components: Array<{ type: RuleComponentType; percentage: string }>;
  }) {
    this.assertEnabled();
    if (input.components.reduce((total, component) => total + this.num(component.percentage), 0) !== 100) {
      throw new Error('Revenue rule components must total 100%');
    }

    const rule = this.insert('service_revenue_share_rules', {
      serviceUuid: input.serviceUuid ?? null,
      branchUuid: input.branchUuid ?? null,
      basis: input.basis,
      fixedAmount: input.fixedAmount ?? null,
      requiresApproval: input.requiresApproval ? '1' : '0',
      active: '1',
    });

    for (const component of input.components) {
      this.insert('service_revenue_share_rule_components', {
        ruleUuid: rule.uuid,
        type: component.type,
        percentage: component.percentage,
      });
    }

    return rule;
  }

  listModuleAccess(moduleKey = 'services') {
    const moduleSetting = this.sqlite.prepare(`
      SELECT
        module_key AS moduleKey,
        plan_allowed AS planAllowed,
        business_enabled AS businessEnabled,
        branch_effective_status AS branchEffectiveStatus,
        effective_value_json AS effectiveValueJson,
        updated_at AS updatedAt
      FROM module_settings
      WHERE module_key = ?
      ORDER BY last_received_at DESC, updated_at DESC
      LIMIT 1
    `).get(moduleKey) as {
      moduleKey: string;
      planAllowed: string;
      businessEnabled: string;
      branchEffectiveStatus: string;
      effectiveValueJson: string | null;
      updatedAt: string;
    } | undefined;

    if (!moduleSetting) {
      const installation = this.readInstallationFlags();
      const planAllowed = installation.plan.includes(moduleKey);
      const businessEnabled = installation.modules.includes(moduleKey);
      const branchEnabled = installation.settings[moduleKey] === true;
      return {
        moduleKey,
        planAllowed,
        businessEnabled,
        branchEnabled,
        enabled: planAllowed && businessEnabled && branchEnabled,
      };
    }

    const planAllowed = moduleSetting.planAllowed === '1';
    const businessEnabled = moduleSetting.businessEnabled === '1';
    const branchEnabled = moduleSetting.branchEffectiveStatus.toLowerCase() === 'enabled';
    return {
      moduleKey: moduleSetting.moduleKey,
      planAllowed,
      businessEnabled,
      branchEnabled,
      enabled: planAllowed && businessEnabled && branchEnabled,
      effectiveValue: moduleSetting.effectiveValueJson ? JSON.parse(moduleSetting.effectiveValueJson) : null,
      updatedAt: moduleSetting.updatedAt,
    };
  }

  listCatalog() {
    this.assertEnabled();
    const categories = this.sqlite.prepare(`
      SELECT uuid, name, active, created_at AS createdAt, updated_at AS updatedAt
      FROM service_categories
      WHERE active = '1'
      ORDER BY name ASC
    `).all();

    const services = this.sqlite.prepare(`
      SELECT
        s.uuid,
        s.category_uuid AS categoryUuid,
        c.name AS categoryName,
        s.code,
        s.name,
        s.active,
        s.created_at AS createdAt,
        s.updated_at AS updatedAt,
        (
          SELECT sp.amount
          FROM service_prices sp
          WHERE sp.service_uuid = s.uuid AND sp.active = '1'
          ORDER BY sp.created_at DESC
          LIMIT 1
        ) AS currentPrice
      FROM services s
      LEFT JOIN service_categories c ON c.uuid = s.category_uuid
      WHERE s.active = '1'
      ORDER BY s.name ASC
    `).all();

    return { categories, services };
  }

  listCustomers() {
    this.assertEnabled();
    return this.sqlite.prepare(`
      SELECT uuid, code, name, type, phone, address, status
      FROM customers
      WHERE status = 'active'
      ORDER BY name ASC
      LIMIT 500
    `).all();
  }

  listServiceStaff() {
    this.assertEnabled();
    return this.sqlite.prepare(`
      SELECT uuid, username, display_name AS displayName, staff_code AS staffCode, role
      FROM users
      WHERE status = 'active'
      ORDER BY COALESCE(display_name, username) ASC
      LIMIT 500
    `).all();
  }

  createOrder(input: { customerUuid?: string; branchUuid: string; discountAmount?: string; items: Array<{ serviceUuid: string; quantity: string; unitPrice: string }> }) {
    this.assertEnabled();
    const now = new Date().toISOString();
    const order = {
      uuid: randomUUID(),
      orderNumber: `SVC-${Date.now()}`,
      customerUuid: input.customerUuid ?? null,
      branchUuid: input.branchUuid,
      status: 'pending',
      discountAmount: input.discountAmount ?? '0',
      paymentConfirmedAt: null,
      allocationRequestedAt: null,
      approvedByUserUuid: null,
      cancelledAt: null,
      refundedAt: null,
      createdAt: now,
      completedAt: null,
    };

    this.sqlite.transaction(() => {
      this.sqlite.prepare(`
        INSERT INTO service_orders (
          uuid, order_number, customer_uuid, branch_uuid, status, discount_amount,
          payment_confirmed_at, allocation_requested_at, approved_by_user_uuid,
          cancelled_at, refunded_at, created_at, completed_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      `).run(
        order.uuid,
        order.orderNumber,
        order.customerUuid,
        order.branchUuid,
        order.status,
        order.discountAmount,
        order.paymentConfirmedAt,
        order.allocationRequestedAt,
        order.approvedByUserUuid,
        order.cancelledAt,
        order.refundedAt,
        order.createdAt,
        order.completedAt,
      );

      for (const item of input.items) {
        this.sqlite.prepare(`
          INSERT INTO service_order_items (
            uuid, order_uuid, service_uuid, quantity, unit_price, line_total, created_at
          ) VALUES (?, ?, ?, ?, ?, ?, ?)
        `).run(
          randomUUID(),
          order.uuid,
          item.serviceUuid,
          item.quantity,
          item.unitPrice,
          this.format(this.num(item.quantity) * this.num(item.unitPrice)),
          now,
        );
      }
    })();

    this.audit('service.order_created', 'service_order', order.uuid);
    return order;
  }

  listOrders(filter?: { status?: ServiceOrderState; customerUuid?: string; from?: string; to?: string }) {
    this.assertEnabled();
    return this.sqlite.prepare(`
      SELECT
        so.uuid,
        so.order_number AS orderNumber,
        so.customer_uuid AS customerUuid,
        c.name AS customerName,
        so.branch_uuid AS branchUuid,
        so.status,
        so.discount_amount AS discountAmount,
        so.payment_confirmed_at AS paymentConfirmedAt,
        so.allocation_requested_at AS allocationRequestedAt,
        so.approved_by_user_uuid AS approvedByUserUuid,
        so.cancelled_at AS cancelledAt,
        so.refunded_at AS refundedAt,
        so.created_at AS createdAt,
        so.completed_at AS completedAt,
        COALESCE((SELECT SUM(CAST(line_total AS REAL)) FROM service_order_items soi WHERE soi.order_uuid = so.uuid), 0) AS grossAmount,
        COALESCE((SELECT SUM(CAST(amount AS REAL)) FROM service_payments sp WHERE sp.order_uuid = so.uuid), 0) AS paidAmount,
        COALESCE((SELECT SUM(CAST(amount AS REAL)) FROM service_expenses se WHERE se.order_uuid = so.uuid AND se.expense_scope = 'direct_service'), 0) AS directExpenses,
        COALESCE((SELECT SUM(CAST(amount AS REAL)) FROM service_revenue_allocations sra WHERE sra.order_uuid = so.uuid AND sra.reversal_of_uuid IS NULL), 0) AS allocatedAmount
      FROM service_orders so
      LEFT JOIN customers c ON c.uuid = so.customer_uuid
      WHERE (? IS NULL OR so.status = ?)
        AND (? IS NULL OR so.customer_uuid = ?)
        AND (? IS NULL OR date(so.created_at) >= date(?))
        AND (? IS NULL OR date(so.created_at) <= date(?))
      ORDER BY so.created_at DESC
    `).all(
      filter?.status ?? null,
      filter?.status ?? null,
      filter?.customerUuid ?? null,
      filter?.customerUuid ?? null,
      filter?.from ?? null,
      filter?.from ?? null,
      filter?.to ?? null,
      filter?.to ?? null,
    );
  }

  getOrderDetail(orderUuid: string) {
    this.assertEnabled();
    const order = this.sqlite.prepare(`
      SELECT
        so.uuid,
        so.order_number AS orderNumber,
        so.customer_uuid AS customerUuid,
        c.name AS customerName,
        so.branch_uuid AS branchUuid,
        so.status,
        so.discount_amount AS discountAmount,
        so.payment_confirmed_at AS paymentConfirmedAt,
        so.allocation_requested_at AS allocationRequestedAt,
        so.approved_by_user_uuid AS approvedByUserUuid,
        so.cancelled_at AS cancelledAt,
        so.refunded_at AS refundedAt,
        so.created_at AS createdAt,
        so.completed_at AS completedAt
      FROM service_orders so
      LEFT JOIN customers c ON c.uuid = so.customer_uuid
      WHERE so.uuid = ?
    `).get(orderUuid) as Record<string, unknown> | undefined;

    if (!order) throw new Error('Service order not found');

    const items = this.sqlite.prepare(`
      SELECT
        soi.uuid,
        soi.service_uuid AS serviceUuid,
        s.name AS serviceName,
        soi.quantity,
        soi.unit_price AS unitPrice,
        soi.line_total AS lineTotal,
        soi.created_at AS createdAt
      FROM service_order_items soi
      JOIN services s ON s.uuid = soi.service_uuid
      WHERE soi.order_uuid = ?
      ORDER BY soi.created_at ASC
    `).all(orderUuid) as Array<Record<string, unknown>>;

    const assignments = this.sqlite.prepare(`
      SELECT
        ssa.uuid,
        ssa.order_item_uuid AS orderItemUuid,
        ssa.staff_user_uuid AS staffUserUuid,
        u.display_name AS staffName,
        ssa.contribution_percentage AS contributionPercentage,
        ssa.assignment_role AS assignmentRole,
        ssa.started_at AS startedAt,
        ssa.completed_at AS completedAt,
        ssa.notes,
        ssa.created_at AS createdAt
      FROM service_staff_assignments ssa
      JOIN users u ON u.uuid = ssa.staff_user_uuid
      WHERE ssa.order_item_uuid IN (SELECT uuid FROM service_order_items WHERE order_uuid = ?)
      ORDER BY ssa.created_at ASC
    `).all(orderUuid);

    return { order, items, assignments };
  }

  assignStaff(orderItemUuid: string, assignments: Array<{ staffUserUuid: string; contributionPercentage: string; assignmentRole?: string; startedAt?: string; completedAt?: string; notes?: string }>) {
    this.assertEnabled();
    if (assignments.reduce((total, assignment) => total + this.num(assignment.contributionPercentage), 0) !== 100) {
      throw new Error('Staff contribution percentages must total 100%');
    }

    const row = this.sqlite.prepare('SELECT order_uuid AS orderUuid FROM service_order_items WHERE uuid = ?').get(orderItemUuid) as { orderUuid: string } | undefined;
    if (!row) throw new Error('Service order item not found');

    const now = new Date().toISOString();
    this.sqlite.transaction(() => {
      this.sqlite.prepare('DELETE FROM service_staff_assignments WHERE order_item_uuid = ?').run(orderItemUuid);
      for (const assignment of assignments) {
        this.sqlite.prepare(`
          INSERT INTO service_staff_assignments (
            uuid, order_item_uuid, staff_user_uuid, contribution_percentage,
            assignment_role, started_at, completed_at, notes, created_at
          ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        `).run(
          randomUUID(),
          orderItemUuid,
          assignment.staffUserUuid,
          assignment.contributionPercentage,
          assignment.assignmentRole ?? null,
          assignment.startedAt ?? null,
          assignment.completedAt ?? null,
          assignment.notes ?? null,
          now,
        );
      }

      this.sqlite.prepare("UPDATE service_orders SET status = 'assigned' WHERE uuid = ? AND status = 'pending'").run(row.orderUuid);
    })();

    this.audit('service.staff_assigned', 'service_order_item', orderItemUuid);
    return assignments;
  }

  startOrder(orderUuid: string) {
    this.assertEnabled();
    const order = this.requireOrder(orderUuid);
    if (order.status !== 'assigned' && order.status !== 'pending') {
      throw new Error('Only pending or assigned orders can be started');
    }

    this.sqlite.prepare("UPDATE service_orders SET status = 'in_progress' WHERE uuid = ?").run(orderUuid);
    this.audit('service.order_started', 'service_order', orderUuid);
    return { orderUuid, status: 'in_progress' };
  }

  completeOrder(orderUuid: string) {
    this.assertEnabled();
    const order = this.requireOrder(orderUuid);
    if (order.status !== 'in_progress' && order.status !== 'assigned') {
      throw new Error('Only assigned or in-progress orders can be completed');
    }

    const now = new Date().toISOString();
    this.sqlite.prepare("UPDATE service_orders SET status = 'completed', completed_at = ? WHERE uuid = ?").run(now, orderUuid);
    this.audit('service.order_completed', 'service_order', orderUuid);
    return { orderUuid, status: 'completed', completedAt: now };
  }

  cancelOrder(orderUuid: string, reason: string) {
    this.assertEnabled();
    const order = this.requireOrder(orderUuid);
    if (order.status === 'completed' || order.status === 'refunded') {
      throw new Error('Completed or refunded orders cannot be cancelled');
    }

    const now = new Date().toISOString();
    this.sqlite.prepare("UPDATE service_orders SET status = 'cancelled', cancelled_at = ? WHERE uuid = ?").run(now, orderUuid);
    this.audit('service.order_cancelled', 'service_order', orderUuid);
    return { orderUuid, status: 'cancelled', reason, cancelledAt: now };
  }

  recordPayment(orderUuid: string, input: { amount: string; paymentMethod: string; reference?: string }) {
    this.assertEnabled();
    this.requireOrder(orderUuid);
    const payment = this.insert('service_payments', { orderUuid, amount: input.amount, paymentMethod: input.paymentMethod, reference: input.reference ?? null });
    this.audit('service.payment_recorded', 'service_payment', payment.uuid);
    return payment;
  }

  confirmOrderPayment(orderUuid: string) {
    this.assertEnabled();
    const detail = this.revenueContext(orderUuid);
    const netDue = detail.grossAmount - detail.discountAmount;
    if (detail.paidAmount < netDue) {
      throw new Error('Service payment is not yet fully confirmed');
    }

    const now = new Date().toISOString();
    this.sqlite.prepare('UPDATE service_orders SET payment_confirmed_at = ? WHERE uuid = ?').run(now, orderUuid);
    this.audit('service.payment_confirmed', 'service_order', orderUuid);
    return { orderUuid, paymentConfirmedAt: now, netDue: this.format(netDue), paidAmount: this.format(detail.paidAmount) };
  }

  recordDirectExpense(orderUuid: string, input: { category: string; description: string; amount: string; paidBy: string; paymentMethod: string; receiptAttachmentPath?: string; approvalStatus?: string }) {
    this.assertEnabled();
    this.requireOrder(orderUuid);

    const expense = this.insert('service_expenses', {
      orderUuid,
      category: input.category,
      amount: input.amount,
      description: input.description,
      paidBy: input.paidBy,
      paymentMethod: input.paymentMethod,
      receiptAttachmentPath: input.receiptAttachmentPath ?? null,
      approvalStatus: input.approvalStatus ?? 'pending',
      expenseScope: 'direct_service',
    });

    this.audit('service.direct_expense_recorded', 'service_expense', expense.uuid);
    return expense;
  }

  listDirectExpenses(filter?: { orderUuid?: string; from?: string; to?: string; approvalStatus?: string }) {
    this.assertEnabled();
    return this.sqlite.prepare(`
      SELECT
        se.uuid,
        se.order_uuid AS orderUuid,
        so.order_number AS orderNumber,
        se.category,
        se.description,
        se.amount,
        se.paid_by AS paidBy,
        se.payment_method AS paymentMethod,
        se.receipt_attachment_path AS receiptAttachmentPath,
        se.approval_status AS approvalStatus,
        se.expense_scope AS expenseScope,
        se.created_at AS createdAt
      FROM service_expenses se
      JOIN service_orders so ON so.uuid = se.order_uuid
      WHERE se.expense_scope = 'direct_service'
        AND (? IS NULL OR se.order_uuid = ?)
        AND (? IS NULL OR se.approval_status = ?)
        AND (? IS NULL OR date(se.created_at) >= date(?))
        AND (? IS NULL OR date(se.created_at) <= date(?))
      ORDER BY se.created_at DESC
    `).all(
      filter?.orderUuid ?? null,
      filter?.orderUuid ?? null,
      filter?.approvalStatus ?? null,
      filter?.approvalStatus ?? null,
      filter?.from ?? null,
      filter?.from ?? null,
      filter?.to ?? null,
      filter?.to ?? null,
    );
  }

  previewRevenueAllocation(orderUuid: string) {
    this.assertEnabled();
    return this.computeAllocationPreview(orderUuid);
  }

  requestRevenueAllocation(orderUuid: string, requestedByUserUuid?: string) {
    this.assertEnabled();
    const context = this.revenueContext(orderUuid);
    if (context.order.status !== 'completed') {
      throw new Error('Only completed service orders can request allocation');
    }
    if (!context.order.paymentConfirmedAt) {
      throw new Error('Payment confirmation is required before requesting allocation');
    }

    const existingPending = this.sqlite.prepare(`
      SELECT uuid FROM service_revenue_allocations
      WHERE order_uuid = ? AND status IN ('pending', 'pending_approval') AND reversal_of_uuid IS NULL
      LIMIT 1
    `).get(orderUuid) as { uuid: string } | undefined;
    if (existingPending) {
      throw new Error('An allocation request already exists for this order');
    }

    const preview = this.computeAllocationPreview(orderUuid);
    const now = new Date().toISOString();

    const allocations = this.sqlite.transaction(() => {
      const created: Array<Record<string, unknown>> = [];
      for (const component of preview.components) {
        if (component.componentType === 'staff' && component.staffSplit.length > 0) {
          for (const staffPart of component.staffSplit) {
            created.push(this.insert('service_revenue_allocations', {
              orderUuid,
              ruleUuid: preview.rule.uuid,
              componentType: 'staff',
              staffUserUuid: staffPart.staffUserUuid,
              amount: this.format(staffPart.amount),
              status: preview.rule.requiresApproval ? 'pending_approval' : 'approved',
              reversalOfUuid: null,
            }));
          }
          continue;
        }

        created.push(this.insert('service_revenue_allocations', {
          orderUuid,
          ruleUuid: preview.rule.uuid,
          componentType: component.componentType,
          staffUserUuid: null,
          amount: this.format(component.amount),
          status: preview.rule.requiresApproval ? 'pending_approval' : 'approved',
          reversalOfUuid: null,
        }));
      }

      this.sqlite.prepare('UPDATE service_orders SET allocation_requested_at = ? WHERE uuid = ?').run(now, orderUuid);

      if (!preview.rule.requiresApproval) {
        for (const allocation of created) {
          if (allocation.componentType === 'staff' && allocation.staffUserUuid) {
            this.postEarning(String(allocation.staffUserUuid), this.num(String(allocation.amount)), 'service_share', String(allocation.uuid));
          }
        }
      }

      return created;
    })();

    this.audit('service.allocation_requested', 'service_order', orderUuid);
    return {
      orderUuid,
      requestedByUserUuid: requestedByUserUuid ?? null,
      ruleUsed: preview.rule,
      allocations,
      status: preview.rule.requiresApproval ? 'pending_approval' : 'approved',
      requestedAt: now,
    };
  }

  approveRevenueAllocation(orderUuid: string, approvedByUserUuid: string) {
    this.assertEnabled();
    const pending = this.sqlite.prepare(`
      SELECT uuid, component_type AS componentType, staff_user_uuid AS staffUserUuid, amount
      FROM service_revenue_allocations
      WHERE order_uuid = ? AND status = 'pending_approval' AND reversal_of_uuid IS NULL
      ORDER BY created_at ASC
    `).all(orderUuid) as Array<{ uuid: string; componentType: string; staffUserUuid: string | null; amount: string }>;

    if (pending.length === 0) {
      throw new Error('No pending revenue allocations found for this order');
    }

    const now = new Date().toISOString();
    this.sqlite.transaction(() => {
      this.sqlite.prepare(`
        UPDATE service_revenue_allocations
        SET status = 'approved'
        WHERE order_uuid = ? AND status = 'pending_approval' AND reversal_of_uuid IS NULL
      `).run(orderUuid);

      this.sqlite.prepare('UPDATE service_orders SET approved_by_user_uuid = ? WHERE uuid = ?').run(approvedByUserUuid, orderUuid);

      for (const allocation of pending) {
        if (allocation.componentType === 'staff' && allocation.staffUserUuid) {
          this.postEarning(allocation.staffUserUuid, this.num(allocation.amount), 'service_share', allocation.uuid);
        }
      }
    })();

    this.audit('service.allocation_approved', 'service_order', orderUuid);
    return { orderUuid, approvedByUserUuid, approvedAt: now };
  }

  listRevenueAllocations(filter?: { orderUuid?: string; status?: string; from?: string; to?: string }) {
    this.assertEnabled();
    return this.sqlite.prepare(`
      SELECT
        sra.uuid,
        sra.order_uuid AS orderUuid,
        so.order_number AS orderNumber,
        sra.rule_uuid AS ruleUuid,
        sra.component_type AS componentType,
        sra.staff_user_uuid AS staffUserUuid,
        u.display_name AS staffName,
        sra.amount,
        sra.status,
        sra.reversal_of_uuid AS reversalOfUuid,
        sra.created_at AS createdAt
      FROM service_revenue_allocations sra
      JOIN service_orders so ON so.uuid = sra.order_uuid
      LEFT JOIN users u ON u.uuid = sra.staff_user_uuid
      WHERE (? IS NULL OR sra.order_uuid = ?)
        AND (? IS NULL OR sra.status = ?)
        AND (? IS NULL OR date(sra.created_at) >= date(?))
        AND (? IS NULL OR date(sra.created_at) <= date(?))
      ORDER BY sra.created_at DESC
    `).all(
      filter?.orderUuid ?? null,
      filter?.orderUuid ?? null,
      filter?.status ?? null,
      filter?.status ?? null,
      filter?.from ?? null,
      filter?.from ?? null,
      filter?.to ?? null,
      filter?.to ?? null,
    );
  }

  listStaffEarnings() {
    this.assertEnabled();
    return this.sqlite.prepare(`
      SELECT
        sea.staff_user_uuid AS staffUserUuid,
        u.display_name AS staffName,
        u.username,
        sea.current_balance AS currentBalance,
        sea.updated_at AS updatedAt
      FROM staff_earning_accounts sea
      JOIN users u ON u.uuid = sea.staff_user_uuid
      ORDER BY CAST(sea.current_balance AS REAL) DESC
    `).all();
  }

  listStaffSettlements(filter?: { staffUserUuid?: string; from?: string; to?: string }) {
    this.assertEnabled();
    return this.sqlite.prepare(`
      SELECT
        ss.uuid,
        ss.staff_user_uuid AS staffUserUuid,
        u.display_name AS staffName,
        ss.amount,
        ss.status,
        ss.created_at AS createdAt
      FROM staff_settlements ss
      JOIN users u ON u.uuid = ss.staff_user_uuid
      WHERE (? IS NULL OR ss.staff_user_uuid = ?)
        AND (? IS NULL OR date(ss.created_at) >= date(?))
        AND (? IS NULL OR date(ss.created_at) <= date(?))
      ORDER BY ss.created_at DESC
    `).all(
      filter?.staffUserUuid ?? null,
      filter?.staffUserUuid ?? null,
      filter?.from ?? null,
      filter?.from ?? null,
      filter?.to ?? null,
      filter?.to ?? null,
    );
  }

  staffEarningsOverview(filter?: { staffUserUuid?: string; from?: string; to?: string; period?: 'day' | 'week' | 'month' }) {
    this.assertEnabled();
    const period = filter?.period ?? 'month';
    const periodExpression = period === 'day'
      ? "strftime('%Y-%m-%d', created_at)"
      : period === 'week'
        ? "strftime('%Y-W%W', created_at)"
        : "strftime('%Y-%m', created_at)";

    const totals = this.sqlite.prepare(`
      SELECT
        COALESCE((
          SELECT SUM(CASE WHEN setx.type IN ('service_share', 'bonus', 'adjustment') THEN CAST(setx.amount AS REAL) ELSE 0 END)
          FROM staff_earning_transactions setx
          WHERE (? IS NULL OR setx.staff_user_uuid = ?)
            AND (? IS NULL OR date(setx.created_at) >= date(?))
            AND (? IS NULL OR date(setx.created_at) <= date(?))
        ), 0) AS totalEarned,
        COALESCE((
          SELECT SUM(CASE WHEN sra.status = 'approved' THEN CAST(sra.amount AS REAL) ELSE 0 END)
          FROM service_revenue_allocations sra
          WHERE sra.component_type = 'staff'
            AND sra.reversal_of_uuid IS NULL
            AND (? IS NULL OR sra.staff_user_uuid = ?)
            AND (? IS NULL OR date(sra.created_at) >= date(?))
            AND (? IS NULL OR date(sra.created_at) <= date(?))
        ), 0) AS approvedEarnings,
        COALESCE((
          SELECT SUM(CASE WHEN sra.status IN ('pending', 'pending_approval') THEN CAST(sra.amount AS REAL) ELSE 0 END)
          FROM service_revenue_allocations sra
          WHERE sra.component_type = 'staff'
            AND sra.reversal_of_uuid IS NULL
            AND (? IS NULL OR sra.staff_user_uuid = ?)
            AND (? IS NULL OR date(sra.created_at) >= date(?))
            AND (? IS NULL OR date(sra.created_at) <= date(?))
        ), 0) AS pendingEarnings,
        COALESCE((
          SELECT SUM(CAST(ss.amount AS REAL))
          FROM staff_settlements ss
          WHERE ss.status = 'completed'
            AND (? IS NULL OR ss.staff_user_uuid = ?)
            AND (? IS NULL OR date(ss.created_at) >= date(?))
            AND (? IS NULL OR date(ss.created_at) <= date(?))
        ), 0) AS settledAmount,
        COALESCE((
          SELECT SUM(CAST(sea.current_balance AS REAL))
          FROM staff_earning_accounts sea
          WHERE (? IS NULL OR sea.staff_user_uuid = ?)
        ), 0) AS outstandingBalance,
        COALESCE((
          SELECT SUM(CASE WHEN setx.type = 'reversal' THEN ABS(CAST(setx.amount AS REAL)) ELSE 0 END)
          FROM staff_earning_transactions setx
          WHERE (? IS NULL OR setx.staff_user_uuid = ?)
            AND (? IS NULL OR date(setx.created_at) >= date(?))
            AND (? IS NULL OR date(setx.created_at) <= date(?))
        ), 0) AS reversedAmount
    `).get(
      filter?.staffUserUuid ?? null,
      filter?.staffUserUuid ?? null,
      filter?.from ?? null,
      filter?.from ?? null,
      filter?.to ?? null,
      filter?.to ?? null,
      filter?.staffUserUuid ?? null,
      filter?.staffUserUuid ?? null,
      filter?.from ?? null,
      filter?.from ?? null,
      filter?.to ?? null,
      filter?.to ?? null,
      filter?.staffUserUuid ?? null,
      filter?.staffUserUuid ?? null,
      filter?.from ?? null,
      filter?.from ?? null,
      filter?.to ?? null,
      filter?.to ?? null,
      filter?.staffUserUuid ?? null,
      filter?.staffUserUuid ?? null,
      filter?.from ?? null,
      filter?.from ?? null,
      filter?.to ?? null,
      filter?.to ?? null,
      filter?.staffUserUuid ?? null,
      filter?.staffUserUuid ?? null,
      filter?.staffUserUuid ?? null,
      filter?.staffUserUuid ?? null,
      filter?.from ?? null,
      filter?.from ?? null,
      filter?.to ?? null,
      filter?.to ?? null,
    ) as {
      totalEarned: number;
      approvedEarnings: number;
      pendingEarnings: number;
      settledAmount: number;
      outstandingBalance: number;
      reversedAmount: number;
    };

    const earningsByService = this.sqlite.prepare(`
      WITH order_totals AS (
        SELECT order_uuid AS orderUuid, COALESCE(SUM(CAST(line_total AS REAL)), 0) AS grossAmount
        FROM service_order_items
        GROUP BY order_uuid
      )
      SELECT
        soi.service_uuid AS serviceUuid,
        s.name AS serviceName,
        SUM(CASE WHEN sra.status = 'approved' AND sra.reversal_of_uuid IS NULL
          THEN CAST(sra.amount AS REAL) * (CASE WHEN ot.grossAmount = 0 THEN 0 ELSE CAST(soi.line_total AS REAL) / ot.grossAmount END)
          ELSE 0 END) AS approvedEarnings,
        SUM(CASE WHEN sra.status IN ('pending', 'pending_approval') AND sra.reversal_of_uuid IS NULL
          THEN CAST(sra.amount AS REAL) * (CASE WHEN ot.grossAmount = 0 THEN 0 ELSE CAST(soi.line_total AS REAL) / ot.grossAmount END)
          ELSE 0 END) AS pendingEarnings,
        SUM(CASE WHEN sra.reversal_of_uuid IS NOT NULL
          THEN ABS(CAST(sra.amount AS REAL)) * (CASE WHEN ot.grossAmount = 0 THEN 0 ELSE CAST(soi.line_total AS REAL) / ot.grossAmount END)
          ELSE 0 END) AS reversedAmount
      FROM service_revenue_allocations sra
      JOIN service_order_items soi ON soi.order_uuid = sra.order_uuid
      JOIN services s ON s.uuid = soi.service_uuid
      JOIN order_totals ot ON ot.orderUuid = sra.order_uuid
      WHERE sra.component_type = 'staff'
        AND (? IS NULL OR sra.staff_user_uuid = ?)
        AND (? IS NULL OR date(sra.created_at) >= date(?))
        AND (? IS NULL OR date(sra.created_at) <= date(?))
      GROUP BY soi.service_uuid, s.name
      ORDER BY approvedEarnings DESC, pendingEarnings DESC
    `).all(
      filter?.staffUserUuid ?? null,
      filter?.staffUserUuid ?? null,
      filter?.from ?? null,
      filter?.from ?? null,
      filter?.to ?? null,
      filter?.to ?? null,
    ) as Array<{ serviceUuid: string; serviceName: string; approvedEarnings: number; pendingEarnings: number; reversedAmount: number }>;

    const earningsByPeriod = this.sqlite.prepare(`
      SELECT
        ${periodExpression} AS period,
        SUM(CASE WHEN type IN ('service_share', 'bonus', 'adjustment') THEN CAST(amount AS REAL) ELSE 0 END) AS totalEarned,
        SUM(CASE WHEN type = 'reversal' THEN ABS(CAST(amount AS REAL)) ELSE 0 END) AS reversedAmount,
        SUM(CASE WHEN type = 'settlement_payment' THEN ABS(CAST(amount AS REAL)) ELSE 0 END) AS settledAmount
      FROM staff_earning_transactions
      WHERE (? IS NULL OR staff_user_uuid = ?)
        AND (? IS NULL OR date(created_at) >= date(?))
        AND (? IS NULL OR date(created_at) <= date(?))
      GROUP BY ${periodExpression}
      ORDER BY period DESC
    `).all(
      filter?.staffUserUuid ?? null,
      filter?.staffUserUuid ?? null,
      filter?.from ?? null,
      filter?.from ?? null,
      filter?.to ?? null,
      filter?.to ?? null,
    ) as Array<{ period: string; totalEarned: number; reversedAmount: number; settledAmount: number }>;

    const pendingByPeriod = this.sqlite.prepare(`
      SELECT
        ${periodExpression} AS period,
        SUM(CASE WHEN status IN ('pending', 'pending_approval') AND reversal_of_uuid IS NULL THEN CAST(amount AS REAL) ELSE 0 END) AS pendingEarnings,
        SUM(CASE WHEN status = 'approved' AND reversal_of_uuid IS NULL THEN CAST(amount AS REAL) ELSE 0 END) AS approvedEarnings
      FROM service_revenue_allocations
      WHERE component_type = 'staff'
        AND (? IS NULL OR staff_user_uuid = ?)
        AND (? IS NULL OR date(created_at) >= date(?))
        AND (? IS NULL OR date(created_at) <= date(?))
      GROUP BY ${periodExpression}
      ORDER BY period DESC
    `).all(
      filter?.staffUserUuid ?? null,
      filter?.staffUserUuid ?? null,
      filter?.from ?? null,
      filter?.from ?? null,
      filter?.to ?? null,
      filter?.to ?? null,
    ) as Array<{ period: string; pendingEarnings: number; approvedEarnings: number }>;

    const periodMap = new Map<string, { period: string; totalEarned: number; approvedEarnings: number; pendingEarnings: number; settledAmount: number; reversedAmount: number }>();
    for (const row of earningsByPeriod) {
      periodMap.set(row.period, {
        period: row.period,
        totalEarned: row.totalEarned,
        approvedEarnings: 0,
        pendingEarnings: 0,
        settledAmount: row.settledAmount,
        reversedAmount: row.reversedAmount,
      });
    }
    for (const row of pendingByPeriod) {
      const current = periodMap.get(row.period) ?? {
        period: row.period,
        totalEarned: 0,
        approvedEarnings: 0,
        pendingEarnings: 0,
        settledAmount: 0,
        reversedAmount: 0,
      };
      current.approvedEarnings = row.approvedEarnings;
      current.pendingEarnings = row.pendingEarnings;
      periodMap.set(row.period, current);
    }

    const ledger = this.sqlite.prepare(`
      SELECT
        setx.uuid,
        setx.staff_user_uuid AS staffUserUuid,
        u.display_name AS staffName,
        u.username,
        setx.type,
        setx.amount,
        setx.reference_uuid AS referenceUuid,
        setx.reversal_of_uuid AS reversalOfUuid,
        so.order_number AS orderNumber,
        setx.created_at AS createdAt
      FROM staff_earning_transactions setx
      JOIN users u ON u.uuid = setx.staff_user_uuid
      LEFT JOIN service_revenue_allocations sra ON sra.uuid = setx.reference_uuid
      LEFT JOIN service_orders so ON so.uuid = sra.order_uuid
      WHERE (? IS NULL OR setx.staff_user_uuid = ?)
        AND (? IS NULL OR date(setx.created_at) >= date(?))
        AND (? IS NULL OR date(setx.created_at) <= date(?))
      ORDER BY setx.created_at DESC
      LIMIT 500
    `).all(
      filter?.staffUserUuid ?? null,
      filter?.staffUserUuid ?? null,
      filter?.from ?? null,
      filter?.from ?? null,
      filter?.to ?? null,
      filter?.to ?? null,
    ) as Array<{
      uuid: string;
      staffUserUuid: string;
      staffName: string | null;
      username: string;
      type: StaffLedgerType;
      amount: string;
      referenceUuid: string | null;
      reversalOfUuid: string | null;
      orderNumber: string | null;
      createdAt: string;
    }>;

    const settlementHistory = this.listStaffSettlements({
      staffUserUuid: filter?.staffUserUuid,
      from: filter?.from,
      to: filter?.to,
    });

    return {
      totals,
      earningsByService: earningsByService.map((row) => ({
        ...row,
        totalEarned: row.approvedEarnings + row.pendingEarnings,
      })),
      earningsByPeriod: [...periodMap.values()].sort((a, b) => b.period.localeCompare(a.period)),
      settlementHistory,
      ledger,
      ledgerTypes: ['service_share', 'bonus', 'adjustment', 'deduction', 'settlement_payment', 'reversal'] satisfies StaffLedgerType[],
    };
  }

  prepareStaffSettlement(staffUserUuid: string, amount?: string) {
    this.assertEnabled();
    const account = this.account(staffUserUuid);
    const staff = this.sqlite.prepare('SELECT uuid, display_name AS staffName, username FROM users WHERE uuid = ?').get(staffUserUuid) as {
      uuid: string;
      staffName: string | null;
      username: string;
    } | undefined;
    if (!staff) throw new Error('Staff account not found');

    const approvedOutstanding = Math.max(this.num(account.currentBalance), 0);
    const requestedAmount = this.num(amount ?? this.format(approvedOutstanding));
    const canSettle = requestedAmount > 0 && requestedAmount <= approvedOutstanding;

    return {
      staffUserUuid,
      staffName: staff.staffName,
      username: staff.username,
      requestedAmount: this.format(requestedAmount),
      approvedOutstanding: this.format(approvedOutstanding),
      canSettle,
      message: canSettle
        ? 'Settlement can be recorded.'
        : 'Requested amount is above approved outstanding earnings.',
    };
  }

  staffSettlementStatement(staffUserUuid: string, filter?: { from?: string; to?: string }) {
    this.assertEnabled();
    const staff = this.sqlite.prepare('SELECT uuid, display_name AS staffName, username FROM users WHERE uuid = ?').get(staffUserUuid) as {
      uuid: string;
      staffName: string | null;
      username: string;
    } | undefined;
    if (!staff) throw new Error('Staff account not found');

    const overview = this.staffEarningsOverview({
      staffUserUuid,
      from: filter?.from,
      to: filter?.to,
      period: 'month',
    });

    return {
      generatedAt: new Date().toISOString(),
      staff,
      period: {
        from: filter?.from ?? null,
        to: filter?.to ?? null,
      },
      totals: overview.totals,
      ledger: overview.ledger,
      settlementHistory: overview.settlementHistory,
      notes: [
        'Monitoring labels are neutral and require human review.',
        'Settlement recording is capped by approved outstanding earnings.',
      ],
    };
  }

  recordStaffEarningAdjustment(staffUserUuid: string, input: { type: 'bonus' | 'adjustment' | 'deduction'; amount: string; note?: string }) {
    this.assertEnabled();
    const amountValue = this.num(input.amount);
    if (amountValue <= 0) {
      throw new Error('Adjustment amount must be greater than zero');
    }

    if (input.type === 'deduction') {
      const account = this.account(staffUserUuid);
      if (amountValue > this.num(account.currentBalance)) {
        throw new Error('Deduction exceeds outstanding earnings');
      }
    }

    const signedAmount = input.type === 'deduction' ? -amountValue : amountValue;
    const transaction = this.postEarning(staffUserUuid, signedAmount, input.type, null);
    this.audit('staff.earning_adjusted', 'staff_earning_transaction', transaction.uuid);

    return {
      ...transaction,
      note: input.note ?? null,
    };
  }

  staffPerformanceSummary(filter?: { from?: string; to?: string }) {
    this.assertEnabled();
    const users = this.sqlite.prepare(`
      SELECT uuid, username, display_name AS staffName
      FROM users
      WHERE status = 'active'
      ORDER BY COALESCE(display_name, username) ASC
    `).all() as Array<{ uuid: string; username: string; staffName: string | null }>;

    const rows = users.map((user) => {
      const params = [user.uuid, user.uuid, filter?.from ?? null, filter?.from ?? null, filter?.to ?? null, filter?.to ?? null];

      const salesCount = this.totalWithParams(`
        SELECT COUNT(*) AS total
        FROM sales s
        JOIN cashier_shifts cs ON cs.uuid = s.shift_uuid
        WHERE cs.cashier_user_uuid = ?
          AND s.status = 'completed'
          AND (? IS NULL OR date(s.created_at) >= date(?))
          AND (? IS NULL OR date(s.created_at) <= date(?))
      `, [user.uuid, filter?.from ?? null, filter?.from ?? null, filter?.to ?? null, filter?.to ?? null]);

      const salesValue = this.totalWithParams(`
        SELECT COALESCE(SUM(CAST(s.total_amount AS REAL)), 0) AS total
        FROM sales s
        JOIN cashier_shifts cs ON cs.uuid = s.shift_uuid
        WHERE cs.cashier_user_uuid = ?
          AND s.status = 'completed'
          AND (? IS NULL OR date(s.created_at) >= date(?))
          AND (? IS NULL OR date(s.created_at) <= date(?))
      `, [user.uuid, filter?.from ?? null, filter?.from ?? null, filter?.to ?? null, filter?.to ?? null]);

      const discounts = this.totalWithParams(`
        SELECT COALESCE(SUM(CAST(sd.amount AS REAL)), 0) AS total
        FROM sale_discounts sd
        JOIN sales s ON s.uuid = sd.sale_uuid
        JOIN cashier_shifts cs ON cs.uuid = s.shift_uuid
        WHERE cs.cashier_user_uuid = ?
          AND (? IS NULL OR date(sd.created_at) >= date(?))
          AND (? IS NULL OR date(sd.created_at) <= date(?))
      `, [user.uuid, filter?.from ?? null, filter?.from ?? null, filter?.to ?? null, filter?.to ?? null]);

      const returns = this.totalWithParams(`
        SELECT COALESCE(SUM(CAST(sr.refund_amount AS REAL)), 0) AS total
        FROM sale_returns sr
        JOIN sales s ON s.uuid = sr.sale_uuid
        JOIN cashier_shifts cs ON cs.uuid = s.shift_uuid
        WHERE cs.cashier_user_uuid = ?
          AND (? IS NULL OR date(sr.created_at) >= date(?))
          AND (? IS NULL OR date(sr.created_at) <= date(?))
      `, [user.uuid, filter?.from ?? null, filter?.from ?? null, filter?.to ?? null, filter?.to ?? null]);

      const cancellations = this.totalWithParams(`
        SELECT COUNT(*) AS total
        FROM sale_cancellations sc
        JOIN sales s ON s.uuid = sc.sale_uuid
        JOIN cashier_shifts cs ON cs.uuid = s.shift_uuid
        WHERE cs.cashier_user_uuid = ?
          AND (? IS NULL OR date(sc.created_at) >= date(?))
          AND (? IS NULL OR date(sc.created_at) <= date(?))
      `, [user.uuid, filter?.from ?? null, filter?.from ?? null, filter?.to ?? null, filter?.to ?? null]);

      const expectedCash = this.totalWithParams(`
        SELECT COALESCE(SUM(CAST(st.expected_cash AS REAL)), 0) AS total
        FROM cashier_settlements st
        JOIN cashier_shifts cs ON cs.uuid = st.shift_uuid
        WHERE cs.cashier_user_uuid = ?
          AND (? IS NULL OR date(st.created_at) >= date(?))
          AND (? IS NULL OR date(st.created_at) <= date(?))
      `, [user.uuid, filter?.from ?? null, filter?.from ?? null, filter?.to ?? null, filter?.to ?? null]);

      const actualCash = this.totalWithParams(`
        SELECT COALESCE(SUM(CAST(st.actual_cash AS REAL)), 0) AS total
        FROM cashier_settlements st
        JOIN cashier_shifts cs ON cs.uuid = st.shift_uuid
        WHERE cs.cashier_user_uuid = ?
          AND (? IS NULL OR date(st.created_at) >= date(?))
          AND (? IS NULL OR date(st.created_at) <= date(?))
      `, [user.uuid, filter?.from ?? null, filter?.from ?? null, filter?.to ?? null, filter?.to ?? null]);

      const servicesAssigned = this.totalWithParams(`
        SELECT COUNT(*) AS total
        FROM service_staff_assignments ssa
        WHERE ssa.staff_user_uuid = ?
          AND (? IS NULL OR date(ssa.created_at) >= date(?))
          AND (? IS NULL OR date(ssa.created_at) <= date(?))
      `, params);

      const servicesCompleted = this.totalWithParams(`
        SELECT COUNT(*) AS total
        FROM service_staff_assignments ssa
        JOIN service_order_items soi ON soi.uuid = ssa.order_item_uuid
        JOIN service_orders so ON so.uuid = soi.order_uuid
        WHERE ssa.staff_user_uuid = ?
          AND so.status = 'completed'
          AND (? IS NULL OR date(so.completed_at) >= date(?))
          AND (? IS NULL OR date(so.completed_at) <= date(?))
      `, params);

      const servicesCancelled = this.totalWithParams(`
        SELECT COUNT(*) AS total
        FROM service_staff_assignments ssa
        JOIN service_order_items soi ON soi.uuid = ssa.order_item_uuid
        JOIN service_orders so ON so.uuid = soi.order_uuid
        WHERE ssa.staff_user_uuid = ?
          AND so.status IN ('cancelled', 'refunded')
          AND (? IS NULL OR date(so.created_at) >= date(?))
          AND (? IS NULL OR date(so.created_at) <= date(?))
      `, params);

      const serviceRevenue = this.totalWithParams(`
        SELECT COALESCE(SUM(CAST(soi.line_total AS REAL) * (CAST(ssa.contribution_percentage AS REAL) / 100.0)), 0) AS total
        FROM service_staff_assignments ssa
        JOIN service_order_items soi ON soi.uuid = ssa.order_item_uuid
        JOIN service_orders so ON so.uuid = soi.order_uuid
        WHERE ssa.staff_user_uuid = ?
          AND so.status IN ('completed', 'in_progress', 'assigned')
          AND (? IS NULL OR date(so.created_at) >= date(?))
          AND (? IS NULL OR date(so.created_at) <= date(?))
      `, params);

      const directExpenses = this.totalWithParams(`
        SELECT COALESCE(SUM(CAST(se.amount AS REAL) * (CAST(ssa.contribution_percentage AS REAL) / 100.0)), 0) AS total
        FROM service_staff_assignments ssa
        JOIN service_order_items soi ON soi.uuid = ssa.order_item_uuid
        JOIN service_expenses se ON se.order_uuid = soi.order_uuid
        WHERE ssa.staff_user_uuid = ?
          AND (? IS NULL OR date(se.created_at) >= date(?))
          AND (? IS NULL OR date(se.created_at) <= date(?))
      `, params);

      const staffShareEarned = this.totalWithParams(`
        SELECT COALESCE(SUM(CASE WHEN type IN ('service_share', 'bonus', 'adjustment') THEN CAST(amount AS REAL) ELSE 0 END), 0) AS total
        FROM staff_earning_transactions
        WHERE staff_user_uuid = ?
          AND (? IS NULL OR date(created_at) >= date(?))
          AND (? IS NULL OR date(created_at) <= date(?))
      `, params);

      const staffShareSettled = this.totalWithParams(`
        SELECT COALESCE(SUM(CAST(amount AS REAL)), 0) AS total
        FROM staff_settlements
        WHERE staff_user_uuid = ?
          AND status = 'completed'
          AND (? IS NULL OR date(created_at) >= date(?))
          AND (? IS NULL OR date(created_at) <= date(?))
      `, params);

      const outstandingEarnings = this.totalWithParams(
        'SELECT COALESCE(SUM(CAST(current_balance AS REAL)), 0) AS total FROM staff_earning_accounts WHERE staff_user_uuid = ?',
        [user.uuid],
      );

      const averageCompletionTimeMinutes = this.totalWithParams(`
        SELECT COALESCE(AVG((julianday(ssa.completed_at) - julianday(ssa.started_at)) * 24 * 60), 0) AS total
        FROM service_staff_assignments ssa
        WHERE ssa.staff_user_uuid = ?
          AND ssa.started_at IS NOT NULL
          AND ssa.completed_at IS NOT NULL
          AND (? IS NULL OR date(ssa.completed_at) >= date(?))
          AND (? IS NULL OR date(ssa.completed_at) <= date(?))
      `, params);

      const purchases = this.totalWithParams(`
        SELECT COUNT(*) AS total
        FROM audit_logs
        WHERE actor_user_uuid = ?
          AND action LIKE 'purchase.%'
          AND (? IS NULL OR date(created_at) >= date(?))
          AND (? IS NULL OR date(created_at) <= date(?))
      `, params);

      const stockCounts = this.totalWithParams(`
        SELECT COUNT(*) AS total
        FROM stock_count_sessions
        WHERE created_by_user_uuid = ?
          AND (? IS NULL OR date(created_at) >= date(?))
          AND (? IS NULL OR date(created_at) <= date(?))
      `, params);

      const stockAdjustments = this.totalWithParams(`
        SELECT COUNT(*) AS total
        FROM stock_adjustments
        WHERE created_by_user_uuid = ?
          AND (? IS NULL OR date(created_at) >= date(?))
          AND (? IS NULL OR date(created_at) <= date(?))
      `, params);

      const expenses = this.totalWithParams(`
        SELECT COUNT(*) AS total
        FROM expenses
        WHERE created_by_user_uuid = ?
          AND (? IS NULL OR date(created_at) >= date(?))
          AND (? IS NULL OR date(created_at) <= date(?))
      `, params);

      const approvals = this.totalWithParams(`
        SELECT
          COALESCE((SELECT COUNT(*) FROM expenses e WHERE e.approved_by_user_uuid = ? AND (? IS NULL OR date(e.created_at) >= date(?)) AND (? IS NULL OR date(e.created_at) <= date(?))), 0)
          + COALESCE((SELECT COUNT(*) FROM stock_adjustments sa WHERE sa.approved_by_user_uuid = ? AND (? IS NULL OR date(sa.created_at) >= date(?)) AND (? IS NULL OR date(sa.created_at) <= date(?))), 0)
          + COALESCE((SELECT COUNT(*) FROM stock_count_sessions scs WHERE scs.approved_by_user_uuid = ? AND (? IS NULL OR date(scs.created_at) >= date(?)) AND (? IS NULL OR date(scs.created_at) <= date(?))), 0)
          + COALESCE((SELECT COUNT(*) FROM service_orders so WHERE so.approved_by_user_uuid = ? AND (? IS NULL OR date(so.created_at) >= date(?)) AND (? IS NULL OR date(so.created_at) <= date(?))), 0)
          AS total
      `, [
        user.uuid, filter?.from ?? null, filter?.from ?? null, filter?.to ?? null, filter?.to ?? null,
        user.uuid, filter?.from ?? null, filter?.from ?? null, filter?.to ?? null, filter?.to ?? null,
        user.uuid, filter?.from ?? null, filter?.from ?? null, filter?.to ?? null, filter?.to ?? null,
        user.uuid, filter?.from ?? null, filter?.from ?? null, filter?.to ?? null, filter?.to ?? null,
      ]);

      const deviceActivity = this.totalWithParams(`
        SELECT COUNT(*) AS total
        FROM login_logs
        WHERE user_uuid = ?
          AND success = '1'
          AND (? IS NULL OR date(created_at) >= date(?))
          AND (? IS NULL OR date(created_at) <= date(?))
      `, params);

      const cashDelta = actualCash - expectedCash;
      const reviewLabels: string[] = [];
      if (Math.abs(cashDelta) > 0 || expenses > 10) reviewLabels.push('Requires review');
      if (salesCount >= 5 && cancellations / Math.max(salesCount, 1) >= 0.2) reviewLabels.push('Higher than usual');
      if (servicesAssigned >= 4 && servicesCompleted / Math.max(servicesAssigned, 1) < 0.5) reviewLabels.push('Lower than expected');
      if (Math.abs(cashDelta) > Math.max(100, salesValue * 0.05)) reviewLabels.push('Outside expected range');

      return {
        staffUserUuid: user.uuid,
        staffName: user.staffName,
        username: user.username,
        salesCount,
        salesValue,
        discounts,
        returns,
        cancellations,
        expectedCash,
        actualCash,
        cashDelta,
        servicesAssigned,
        servicesCompleted,
        servicesCancelled,
        serviceRevenue,
        directExpenses,
        staffShareEarned,
        staffShareSettled,
        outstandingEarnings,
        averageCompletionTimeMinutes,
        purchases,
        stockCounts,
        stockAdjustments,
        expenses,
        approvals,
        deviceActivity,
        reviewLabels: [...new Set(reviewLabels)],
      };
    });

    return {
      generatedAt: new Date().toISOString(),
      rows,
      labels: ['Requires review', 'Higher than usual', 'Lower than expected', 'Outside expected range'],
      disclaimer: 'These are neutral monitoring indicators only and must be reviewed with context before taking action.',
    };
  }

  listAssignedServices(staffUserUuid?: string) {
    this.assertEnabled();
    return this.sqlite.prepare(`
      SELECT
        ssa.uuid,
        ssa.order_item_uuid AS orderItemUuid,
        soi.order_uuid AS orderUuid,
        so.order_number AS orderNumber,
        s.name AS serviceName,
        ssa.staff_user_uuid AS staffUserUuid,
        u.display_name AS staffName,
        ssa.contribution_percentage AS contributionPercentage,
        ssa.assignment_role AS assignmentRole,
        ssa.started_at AS startedAt,
        ssa.completed_at AS completedAt,
        ssa.notes,
        so.status AS orderStatus,
        ssa.created_at AS createdAt
      FROM service_staff_assignments ssa
      JOIN service_order_items soi ON soi.uuid = ssa.order_item_uuid
      JOIN service_orders so ON so.uuid = soi.order_uuid
      JOIN services s ON s.uuid = soi.service_uuid
      JOIN users u ON u.uuid = ssa.staff_user_uuid
      WHERE (? IS NULL OR ssa.staff_user_uuid = ?)
      ORDER BY ssa.created_at DESC
    `).all(staffUserUuid ?? null, staffUserUuid ?? null);
  }

  listPayments(filter?: { orderUuid?: string; from?: string; to?: string }) {
    this.assertEnabled();
    return this.sqlite.prepare(`
      SELECT
        sp.uuid,
        sp.order_uuid AS orderUuid,
        so.order_number AS orderNumber,
        sp.amount,
        sp.payment_method AS paymentMethod,
        sp.reference,
        sp.created_at AS createdAt
      FROM service_payments sp
      JOIN service_orders so ON so.uuid = sp.order_uuid
      WHERE (? IS NULL OR sp.order_uuid = ?)
        AND (? IS NULL OR date(sp.created_at) >= date(?))
        AND (? IS NULL OR date(sp.created_at) <= date(?))
      ORDER BY sp.created_at DESC
    `).all(
      filter?.orderUuid ?? null,
      filter?.orderUuid ?? null,
      filter?.from ?? null,
      filter?.from ?? null,
      filter?.to ?? null,
      filter?.to ?? null,
    );
  }

  dashboard() {
    this.assertEnabled();
    const states = this.sqlite.prepare(`
      SELECT status, COUNT(*) AS count
      FROM service_orders
      GROUP BY status
    `).all() as Array<{ status: string; count: number }>;

    const byState = Object.fromEntries(states.map((row) => [row.status, row.count])) as Record<string, number>;
    return {
      pending: byState.pending ?? 0,
      assigned: byState.assigned ?? 0,
      inProgress: byState.in_progress ?? 0,
      completed: byState.completed ?? 0,
      cancelled: byState.cancelled ?? 0,
      refunded: byState.refunded ?? 0,
      grossRevenue: this.total("SELECT COALESCE(SUM(CAST(line_total AS REAL)), 0) AS total FROM service_order_items"),
      collectedPayments: this.total("SELECT COALESCE(SUM(CAST(amount AS REAL)), 0) AS total FROM service_payments"),
      directServiceExpenses: this.total("SELECT COALESCE(SUM(CAST(amount AS REAL)), 0) AS total FROM service_expenses WHERE expense_scope = 'direct_service'"),
      pendingAllocations: this.total("SELECT COALESCE(SUM(CAST(amount AS REAL)), 0) AS total FROM service_revenue_allocations WHERE status IN ('pending', 'pending_approval')"),
      approvedAllocations: this.total("SELECT COALESCE(SUM(CAST(amount AS REAL)), 0) AS total FROM service_revenue_allocations WHERE status = 'approved'"),
      outstandingStaffEarnings: this.total("SELECT COALESCE(SUM(CAST(current_balance AS REAL)), 0) AS total FROM staff_earning_accounts"),
    };
  }

  settleStaff(staffUserUuid: string, amount: string) {
    this.assertEnabled();
    const result = this.sqlite.transaction(() => {
      const account = this.account(staffUserUuid);
      if (this.num(amount) > this.num(account.currentBalance)) throw new Error('Settlement exceeds approved outstanding earnings');
      const settlement = this.insert('staff_settlements', { staffUserUuid, amount, status: 'completed' });
      const earning = this.postEarning(staffUserUuid, -this.num(amount), 'settlement_payment', settlement.uuid);
      this.insert('staff_settlement_items', { settlementUuid: settlement.uuid, earningTransactionUuid: earning.uuid, amount });
      return settlement;
    })();

    this.audit('staff.settled', 'staff_settlement', result.uuid);
    return result;
  }

  refundOrder(orderUuid: string, reason: string) {
    this.assertEnabled();
    const refund = this.sqlite.transaction(() => {
      const order = this.sqlite.prepare("SELECT uuid FROM service_orders WHERE uuid = ? AND status = 'completed'").get(orderUuid);
      if (!order) throw new Error('Completed service order not found');

      const allocations = this.sqlite.prepare(`
        SELECT uuid, staff_user_uuid AS staffUserUuid, amount
        FROM service_revenue_allocations
        WHERE order_uuid = ? AND status = 'approved' AND reversal_of_uuid IS NULL
      `).all(orderUuid) as Array<{ uuid: string; staffUserUuid: string | null; amount: string }>;

      const amount = allocations.reduce((total, allocation) => total + this.num(allocation.amount), 0);
      const refundUuid = randomUUID();
      for (const allocation of allocations) {
        const reversal = this.insert('service_revenue_allocations', {
          orderUuid,
          ruleUuid: 'refund-reversal',
          componentType: 'reversal',
          staffUserUuid: allocation.staffUserUuid,
          amount: this.format(-this.num(allocation.amount)),
          status: 'approved',
          reversalOfUuid: allocation.uuid,
        });
        if (allocation.staffUserUuid) {
          this.postEarning(allocation.staffUserUuid, -this.num(allocation.amount), 'reversal', reversal.uuid);
        }
      }

      const now = new Date().toISOString();
      this.sqlite.prepare("UPDATE service_orders SET status = 'refunded', refunded_at = ? WHERE uuid = ?").run(now, orderUuid);
      return { uuid: refundUuid, orderUuid, refundAmount: this.format(amount), reason, refundedAt: now };
    })();

    this.audit('service.order_refunded', 'service_order', orderUuid);
    return refund;
  }

  private computeAllocationPreview(orderUuid: string) {
    const context = this.revenueContext(orderUuid);
    const itemRows = context.items;
    if (itemRows.length === 0) throw new Error('Service order has no items');

    const aggregated = {
      grossAmount: context.grossAmount,
      discountAmount: context.discountAmount,
      directExpenses: context.directExpenses,
    };

    const itemPreviews = itemRows.map((item) => {
      const rule = this.resolveRule(item.serviceUuid, context.order.branchUuid);
      if (!rule) throw new Error('No applicable revenue share rule');

      const perItemDiscount = aggregated.discountAmount / itemRows.length;
      const perItemDirectExpense = aggregated.directExpenses / itemRows.length;
      const basisAmount = this.base(rule.basis, this.num(item.lineTotal), perItemDiscount, perItemDirectExpense, rule.fixedAmount);
      const components = this.sqlite.prepare('SELECT type, percentage FROM service_revenue_share_rule_components WHERE rule_uuid = ?').all(rule.uuid) as Array<{ type: RuleComponentType; percentage: string }>;
      const staff = this.sqlite.prepare(`
        SELECT staff_user_uuid AS staffUserUuid, contribution_percentage AS contributionPercentage
        FROM service_staff_assignments
        WHERE order_item_uuid = ?
      `).all(item.uuid) as Array<{ staffUserUuid: string; contributionPercentage: string }>;

      const componentPreview = components.map((component) => {
        const componentAmount = basisAmount * this.num(component.percentage) / 100;
        const staffSplit = component.type === 'staff'
          ? staff.map((person) => ({
              staffUserUuid: person.staffUserUuid,
              contributionPercentage: this.num(person.contributionPercentage),
              amount: componentAmount * this.num(person.contributionPercentage) / 100,
            }))
          : [];

        return {
          componentType: component.type,
          percentage: this.num(component.percentage),
          amount: componentAmount,
          staffSplit,
        };
      });

      return {
        itemUuid: item.uuid,
        serviceUuid: item.serviceUuid,
        serviceName: item.serviceName,
        lineTotal: this.num(item.lineTotal),
        rule,
        basisAmount,
        components: componentPreview,
      };
    });

    const firstRule = itemPreviews[0].rule;
    const mergedComponents = new Map<RuleComponentType, { componentType: RuleComponentType; amount: number; percentage: number; staffSplit: Array<{ staffUserUuid: string; contributionPercentage: number; amount: number }> }>();

    for (const item of itemPreviews) {
      for (const component of item.components) {
        const current = mergedComponents.get(component.componentType) ?? {
          componentType: component.componentType,
          amount: 0,
          percentage: component.percentage,
          staffSplit: [],
        };
        current.amount += component.amount;

        if (component.componentType === 'staff') {
          for (const split of component.staffSplit) {
            const existing = current.staffSplit.find((row) => row.staffUserUuid === split.staffUserUuid);
            if (!existing) {
              current.staffSplit.push({ ...split });
            } else {
              existing.amount += split.amount;
            }
          }
        }

        mergedComponents.set(component.componentType, current);
      }
    }

    const distributableAmount = this.base(
      firstRule.basis,
      aggregated.grossAmount,
      aggregated.discountAmount,
      aggregated.directExpenses,
      firstRule.fixedAmount,
    );

    return {
      orderUuid,
      grossAmount: aggregated.grossAmount,
      discountAmount: aggregated.discountAmount,
      directExpenses: aggregated.directExpenses,
      calculationBasis: firstRule.basis,
      distributableAmount,
      components: [...mergedComponents.values()],
      staffShare: [...mergedComponents.values()].find((component) => component.componentType === 'staff')?.amount ?? 0,
      businessShare: [...mergedComponents.values()].find((component) => component.componentType === 'business')?.amount ?? 0,
      expenseReserveShare: [...mergedComponents.values()].find((component) => component.componentType === 'expense_reserve')?.amount ?? 0,
      staffSplit: [...mergedComponents.values()].find((component) => component.componentType === 'staff')?.staffSplit ?? [],
      rule: firstRule,
      items: itemPreviews,
    };
  }

  private revenueContext(orderUuid: string) {
    const order = this.sqlite.prepare(`
      SELECT
        uuid,
        branch_uuid AS branchUuid,
        status,
        discount_amount AS discountAmount,
        payment_confirmed_at AS paymentConfirmedAt
      FROM service_orders
      WHERE uuid = ?
    `).get(orderUuid) as {
      uuid: string;
      branchUuid: string;
      status: ServiceOrderState;
      discountAmount: string;
      paymentConfirmedAt: string | null;
    } | undefined;

    if (!order) throw new Error('Service order not found');

    const items = this.sqlite.prepare(`
      SELECT soi.uuid, soi.service_uuid AS serviceUuid, s.name AS serviceName, soi.line_total AS lineTotal
      FROM service_order_items soi
      JOIN services s ON s.uuid = soi.service_uuid
      WHERE soi.order_uuid = ?
    `).all(orderUuid) as Array<{ uuid: string; serviceUuid: string; serviceName: string; lineTotal: string }>;

    const grossAmount = items.reduce((total, item) => total + this.num(item.lineTotal), 0);
    const discountAmount = this.num(order.discountAmount);
    const directExpenses = this.sum(
      'service_expenses',
      'amount',
      'order_uuid',
      orderUuid,
      "AND expense_scope = 'direct_service'",
    );
    const paidAmount = this.sum('service_payments', 'amount', 'order_uuid', orderUuid);

    return {
      order,
      items,
      grossAmount,
      discountAmount,
      directExpenses,
      paidAmount,
    };
  }

  private requireOrder(orderUuid: string) {
    const order = this.sqlite.prepare('SELECT uuid, status FROM service_orders WHERE uuid = ?').get(orderUuid) as { uuid: string; status: ServiceOrderState } | undefined;
    if (!order) throw new Error('Service order not found');
    return order;
  }

  private resolveRule(serviceUuid: string, branchUuid: string) {
    return this.sqlite.prepare(`
      SELECT uuid, basis, fixed_amount AS fixedAmount, requires_approval AS requiresApproval
      FROM service_revenue_share_rules
      WHERE active = '1'
        AND (
          (service_uuid = ? AND branch_uuid = ?)
          OR (service_uuid = ? AND branch_uuid IS NULL)
          OR (service_uuid IS NULL AND branch_uuid = ?)
          OR (service_uuid IS NULL AND branch_uuid IS NULL)
        )
      ORDER BY
        CASE
          WHEN service_uuid = ? AND branch_uuid = ? THEN 1
          WHEN service_uuid = ? THEN 2
          WHEN branch_uuid = ? THEN 3
          ELSE 4
        END
      LIMIT 1
    `).get(serviceUuid, branchUuid, serviceUuid, branchUuid, serviceUuid, branchUuid, serviceUuid, branchUuid) as {
      uuid: string;
      basis: RevenueRuleBasis;
      fixedAmount: string | null;
      requiresApproval: string;
    } | undefined;
  }

  private base(basis: RevenueRuleBasis, gross: number, discount: number, directExpenses: number, fixedAmount: string | null) {
    if (basis === 'fixed_amount') return this.num(fixedAmount ?? '0');
    if (basis === 'net_after_discount') return gross - discount;
    if (basis === 'net_after_direct_expenses') return gross - directExpenses;
    if (basis === 'net_after_discount_and_direct_expenses') return gross - discount - directExpenses;
    return gross;
  }

  private account(staffUserUuid: string) {
    let account = this.sqlite.prepare('SELECT uuid, current_balance AS currentBalance FROM staff_earning_accounts WHERE staff_user_uuid = ?').get(staffUserUuid) as { uuid: string; currentBalance: string } | undefined;
    if (!account) {
      const now = new Date().toISOString();
      account = { uuid: randomUUID(), currentBalance: '0' };
      this.sqlite.prepare('INSERT INTO staff_earning_accounts (uuid, staff_user_uuid, current_balance, created_at, updated_at) VALUES (?, ?, ?, ?, ?)').run(account.uuid, staffUserUuid, '0', now, now);
    }
    return account;
  }

  private postEarning(staffUserUuid: string, amount: number, type: StaffLedgerType, referenceUuid?: string | null) {
    const account = this.account(staffUserUuid);
    const now = new Date().toISOString();
    const transaction = this.insert('staff_earning_transactions', { staffUserUuid, type, amount: this.format(amount), referenceUuid: referenceUuid ?? null, reversalOfUuid: null });
    this.sqlite.prepare('UPDATE staff_earning_accounts SET current_balance = ?, updated_at = ? WHERE staff_user_uuid = ?').run(this.format(this.num(account.currentBalance) + amount), now, staffUserUuid);
    return transaction;
  }

  private totalWithParams(sql: string, params: unknown[]) {
    const row = this.sqlite.prepare(sql).get(...params) as { total: number };
    return Number(row?.total ?? 0);
  }

  private readInstallationFlags() {
    const installation = this.sqlite.prepare('SELECT plan_features_json AS planFeatures, enabled_modules_json AS enabledModules, branch_settings_json AS branchSettings FROM app_installation ORDER BY id DESC LIMIT 1').get() as { planFeatures: string | null; enabledModules: string | null; branchSettings: string | null } | undefined;
    return {
      plan: JSON.parse(installation?.planFeatures ?? '[]') as string[],
      modules: JSON.parse(installation?.enabledModules ?? '[]') as string[],
      settings: JSON.parse(installation?.branchSettings ?? '{}') as Record<string, unknown>,
    };
  }

  private assertEnabled() {
    const access = this.listModuleAccess('services');
    if (!access.enabled) {
      throw new Error('Services module is not enabled for this branch');
    }
  }

  private insert(table: string, values: Record<string, unknown>) {
    const now = new Date().toISOString();
    const value = { uuid: randomUUID(), ...values, createdAt: now, updatedAt: now };
    const withoutUpdatedAtTables = [
      'service_prices',
      'service_revenue_share_rules',
      'service_revenue_share_rule_components',
      'service_orders',
      'service_order_items',
      'service_staff_assignments',
      'service_payments',
      'service_expenses',
      'service_revenue_allocations',
      'staff_earning_transactions',
      'staff_settlements',
      'staff_settlement_items',
    ];
    const columns = Object.keys(value)
      .filter((key) => !(key === 'updatedAt' && withoutUpdatedAtTables.includes(table)))
      .map((key) => key.replace(/[A-Z]/g, (letter) => `_${letter.toLowerCase()}`));

    this.sqlite.prepare(`INSERT INTO ${table} (${columns.join(', ')}) VALUES (${columns.map(() => '?').join(', ')})`).run(
      ...columns.map((column) => value[column.replace(/_([a-z])/g, (_, letter) => letter.toUpperCase()) as keyof typeof value]),
    );

    return value as { uuid: string } & Record<string, unknown>;
  }

  private sum(table: string, column: string, where: string, value: string, extraWhere = '') {
    const row = this.sqlite.prepare(`SELECT COALESCE(SUM(CAST(${column} AS REAL)), 0) AS total FROM ${table} WHERE ${where} = ? ${extraWhere}`).get(value) as { total: number };
    return row.total;
  }

  private total(sql: string) {
    const row = this.sqlite.prepare(sql).get() as { total: number };
    return row.total;
  }

  private audit(action: string, subjectType: string, subjectUuid: string) {
    this.auditLogs.record({ action, subjectType, subjectUuid });
    this.syncQueue.enqueue({ aggregateType: subjectType, aggregateUuid: subjectUuid, payload: { action }, idempotencyKey: `${subjectType}:${subjectUuid}` });
  }

  private num(value: string) {
    return Number(value);
  }

  private format(value: number) {
    return String(value);
  }
}
