import type { ClientRole } from '../../state/client-ui-store';

export interface RoleShellItem {
  id: string;
  label: string;
  permission: string;
}

export interface RoleShell {
  role: ClientRole;
  label: string;
  items: RoleShellItem[];
}

export const roleShells: RoleShell[] = [
  {
    role: 'cashier',
    label: 'Cashier',
    items: [
      { id: 'cashier-pos', label: 'POS', permission: 'pos.access' },
      { id: 'cashier-cart', label: 'Cart', permission: 'pos.cart' },
      { id: 'cashier-customers', label: 'Customer selection', permission: 'customers.select' },
      { id: 'cashier-payment', label: 'Payment dialog', permission: 'payments.create' },
      { id: 'cashier-receipt', label: 'Receipt', permission: 'receipts.print' },
      { id: 'cashier-shifts', label: 'Shift opening and closing', permission: 'shifts.manage_own' },
    ],
  },
  {
    role: 'storekeeper',
    label: 'Storekeeper',
    items: [
      { id: 'storekeeper-products', label: 'Products', permission: 'products.view' },
      { id: 'storekeeper-stock', label: 'Stock', permission: 'stock.view' },
      { id: 'storekeeper-purchases', label: 'Purchases', permission: 'purchases.view' },
      { id: 'storekeeper-stock-counts', label: 'Stock counts', permission: 'stock_counts.manage' },
      { id: 'storekeeper-damaged-goods', label: 'Damaged goods', permission: 'damaged_goods.record' },
    ],
  },
  {
    role: 'manager',
    label: 'Manager',
    items: [
      { id: 'manager-approvals', label: 'Approvals', permission: 'approvals.manage' },
      { id: 'manager-returns', label: 'Returns', permission: 'returns.manage' },
      { id: 'manager-expenses', label: 'Expenses', permission: 'expenses.manage' },
      { id: 'manager-cashier-settlement', label: 'Cashier settlement', permission: 'cashier_settlements.manage' },
      { id: 'manager-reports', label: 'Reports', permission: 'reports.view' },
    ],
  },
  {
    role: 'accountant',
    label: 'Accountant',
    items: [
      { id: 'accountant-customer-debts', label: 'Customer debts', permission: 'customer_debts.view' },
      { id: 'accountant-supplier-balances', label: 'Supplier balances', permission: 'supplier_balances.view' },
      { id: 'accountant-expenses', label: 'Expenses', permission: 'expenses.view' },
      { id: 'accountant-reconciliation', label: 'Reconciliation', permission: 'reconciliation.manage' },
      { id: 'accountant-profit-reports', label: 'Profit reports', permission: 'profit_reports.view' },
    ],
  },
];

export function canSeeItem(permissions: string[], item: RoleShellItem): boolean {
  return permissions.includes(item.permission) || permissions.includes('*');
}
