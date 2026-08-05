import type { ClientRole, ShiftStatus } from '../../state/client-ui-store';
import type { ShopSystemStatus } from '@marketlens/api-contracts';

export interface StartingRolePreset {
  value: ClientRole;
  label: string;
}

export interface ShellAction {
  id: string;
  label: string;
  requiredPermissions: string[];
}

export interface ShellNavigationItem {
  id: string;
  label: string;
  group: ShellNavigationGroupId;
  description: string;
  moduleKeys?: string[];
  requiredPermissions: string[];
  actions: ShellAction[];
}

export interface VisibleShellAction extends ShellAction {
  visible: boolean;
}

export interface VisibleShellNavigationItem extends ShellNavigationItem {
  visible: boolean;
  visibleActions: VisibleShellAction[];
}

export type ShellNavigationGroupId =
  | 'dashboard'
  | 'sales'
  | 'products'
  | 'inventory'
  | 'purchases'
  | 'customers'
  | 'suppliers'
  | 'expenses'
  | 'services'
  | 'staff_earnings'
  | 'cashier_shifts'
  | 'approvals'
  | 'reports'
  | 'settings'
  | 'connection';

export const shellNavigationGroupLabels: Record<ShellNavigationGroupId, string> = {
  dashboard: 'Dashboard',
  sales: 'Sales',
  products: 'Products',
  inventory: 'Inventory',
  purchases: 'Purchases',
  customers: 'Customers',
  suppliers: 'Suppliers',
  expenses: 'Expenses',
  services: 'Services',
  staff_earnings: 'Staff Earnings',
  cashier_shifts: 'Cashier Shifts',
  approvals: 'Approvals',
  reports: 'Reports',
  settings: 'Settings',
  connection: 'Connection',
};

export const startingRolePresets: StartingRolePreset[] = [
  { value: 'owner', label: 'Owner' },
  { value: 'administrator', label: 'Administrator' },
  { value: 'manager', label: 'Manager' },
  { value: 'cashier', label: 'Cashier' },
  { value: 'storekeeper', label: 'Storekeeper' },
  { value: 'accountant', label: 'Accountant' },
  { value: 'service_staff', label: 'Service Staff' },
  { value: 'supervisor', label: 'Supervisor' },
  { value: 'auditor', label: 'Read-Only Auditor' },
];

export const shellNavigationItems: ShellNavigationItem[] = [
  {
    id: 'dashboard-home',
    label: 'Operations Dashboard',
    group: 'dashboard',
    description: 'High-level workstation summary, counters, and alerts.',
    requiredPermissions: ['dashboard.view', 'dashboard.read', 'view-dashboard'],
    actions: [
      { id: 'dashboard-refresh', label: 'Refresh KPI snapshot', requiredPermissions: ['dashboard.view', 'view-dashboard'] },
      { id: 'dashboard-export', label: 'Export dashboard summary', requiredPermissions: ['reports.export', 'export-reports'] },
    ],
  },
  {
    id: 'sales-operations',
    label: 'Sales Operations',
    group: 'sales',
    description: 'Point-of-sale and sale lifecycle operations.',
    moduleKeys: ['sales'],
    requiredPermissions: ['sales.create_sale', 'sales.view', 'sales.process', 'view-sales'],
    actions: [
      { id: 'sales-create', label: 'Create sale', requiredPermissions: ['sales.create_sale', 'sales.process'] },
      { id: 'sales-cancel', label: 'Cancel sale', requiredPermissions: ['sales.cancel_sale'] },
    ],
  },
  {
    id: 'products-catalog',
    label: 'Product Catalog',
    group: 'products',
    description: 'Product definitions, pricing, and catalog controls.',
    moduleKeys: ['inventory', 'sales'],
    requiredPermissions: ['products.view', 'products.manage', 'view-inventory'],
    actions: [
      { id: 'products-add', label: 'Add product', requiredPermissions: ['products.manage'] },
      { id: 'products-pricing', label: 'Update pricing', requiredPermissions: ['products.manage', 'sales.approve_discount'] },
    ],
  },
  {
    id: 'inventory-stock',
    label: 'Inventory Control',
    group: 'inventory',
    description: 'Stock movements, counts, and availability tracking.',
    moduleKeys: ['inventory'],
    requiredPermissions: ['inventory.view', 'stock.view', 'view-inventory'],
    actions: [
      { id: 'inventory-adjust', label: 'Adjust stock', requiredPermissions: ['inventory.adjust_stock', 'stock.adjust'] },
      { id: 'inventory-count', label: 'Run stock count', requiredPermissions: ['inventory.stock_count', 'stock_counts.manage'] },
    ],
  },
  {
    id: 'purchases-procurement',
    label: 'Procurement',
    group: 'purchases',
    description: 'Purchase orders, receiving, and procurement follow-up.',
    moduleKeys: ['purchases'],
    requiredPermissions: ['purchases.view', 'purchases.create', 'view-suppliers'],
    actions: [
      { id: 'purchases-create', label: 'Create purchase', requiredPermissions: ['purchases.create'] },
      { id: 'purchases-receive', label: 'Receive goods', requiredPermissions: ['purchases.receive'] },
    ],
  },
  {
    id: 'customers-accounts',
    label: 'Customers',
    group: 'customers',
    description: 'Customer profiles, debt ledgers, and statements.',
    moduleKeys: ['customers', 'sales'],
    requiredPermissions: ['customers.view', 'customers.manage', 'view-customer-debts'],
    actions: [
      { id: 'customers-create', label: 'Register customer', requiredPermissions: ['customers.manage'] },
      { id: 'customers-ledger', label: 'Review debt ledger', requiredPermissions: ['customer_debts.view', 'view-customer-debts'] },
    ],
  },
  {
    id: 'suppliers-partners',
    label: 'Suppliers',
    group: 'suppliers',
    description: 'Supplier records, balances, and payment workflows.',
    moduleKeys: ['suppliers', 'purchases'],
    requiredPermissions: ['suppliers.view', 'suppliers.manage', 'view-suppliers'],
    actions: [
      { id: 'suppliers-create', label: 'Add supplier', requiredPermissions: ['suppliers.manage'] },
      { id: 'suppliers-balances', label: 'View supplier balances', requiredPermissions: ['supplier_balances.view', 'view-suppliers'] },
    ],
  },
  {
    id: 'expenses-tracking',
    label: 'Expenses',
    group: 'expenses',
    description: 'Operational expenses, approvals, and accounting impact.',
    moduleKeys: ['expenses'],
    requiredPermissions: ['expenses.view', 'expenses.manage', 'view-expenses'],
    actions: [
      { id: 'expenses-record', label: 'Record expense', requiredPermissions: ['expenses.manage'] },
      { id: 'expenses-approve', label: 'Approve expense', requiredPermissions: ['expenses.approve'] },
    ],
  },
  {
    id: 'services-workflow',
    label: 'Service Operations',
    group: 'services',
    description: 'Service jobs, assignments, and revenue capture.',
    moduleKeys: ['services'],
    requiredPermissions: ['services.view', 'services.manage'],
    actions: [
      { id: 'services-create', label: 'Create service order', requiredPermissions: ['services.manage'] },
      { id: 'services-complete', label: 'Complete service order', requiredPermissions: ['services.complete'] },
    ],
  },
  {
    id: 'staff-earnings-ledger',
    label: 'Staff Earnings Ledger',
    group: 'staff_earnings',
    description: 'Track staff commissions, earnings, and settlements.',
    moduleKeys: ['staff_earnings', 'services'],
    requiredPermissions: ['staff_earnings.view', 'staff.performance.view', 'view-cashiers'],
    actions: [
      { id: 'staff-earnings-adjust', label: 'Adjust staff earning', requiredPermissions: ['staff_earnings.manage'] },
      { id: 'staff-earnings-settle', label: 'Settle staff earning', requiredPermissions: ['staff_earnings.settle'] },
    ],
  },
  {
    id: 'cashier-shifts-control',
    label: 'Cashier Shifts',
    group: 'cashier_shifts',
    description: 'Open, monitor, and close cashier shifts.',
    moduleKeys: ['cashier_shifts', 'sales'],
    requiredPermissions: ['cashier_shifts.view', 'shift.manage', 'view-cashiers'],
    actions: [
      { id: 'cashier-shifts-open', label: 'Open shift', requiredPermissions: ['cashier_shifts.open', 'shift.manage'] },
      { id: 'cashier-shifts-close', label: 'Close shift', requiredPermissions: ['cashier_shifts.close', 'shift.manage'] },
    ],
  },
  {
    id: 'approvals-queue',
    label: 'Approvals Queue',
    group: 'approvals',
    description: 'Approve sensitive actions before they execute.',
    moduleKeys: ['approvals', 'sales', 'expenses'],
    requiredPermissions: ['approvals.view', 'approvals.manage', 'approvals.approve'],
    actions: [
      { id: 'approvals-approve', label: 'Approve pending action', requiredPermissions: ['approvals.approve', 'approvals.manage'] },
      { id: 'approvals-reject', label: 'Reject pending action', requiredPermissions: ['approvals.manage'] },
    ],
  },
  {
    id: 'reports-analytics',
    label: 'Reports',
    group: 'reports',
    description: 'Operational reports, exports, and analysis jobs.',
    moduleKeys: ['reporting'],
    requiredPermissions: ['reports.view', 'reports.export', 'export-reports'],
    actions: [
      { id: 'reports-run', label: 'Run report', requiredPermissions: ['reports.view'] },
      { id: 'reports-export', label: 'Export report', requiredPermissions: ['reports.export', 'export-reports'] },
    ],
  },
  {
    id: 'settings-workstation',
    label: 'Settings',
    group: 'settings',
    description: 'Workstation preferences and controlled configuration.',
    requiredPermissions: ['settings.view', 'settings.manage', 'admin.manage_config', 'manage-business-settings'],
    actions: [
      { id: 'settings-theme', label: 'Change theme', requiredPermissions: ['settings.view', 'settings.manage'] },
      { id: 'settings-devices', label: 'Manage paired devices', requiredPermissions: ['admin.authorize_devices', 'admin.manage_users'] },
    ],
  },
  {
    id: 'connection-management',
    label: 'Connection',
    group: 'connection',
    description: 'Server discovery, pairing, and connection diagnostics.',
    requiredPermissions: [],
    actions: [
      { id: 'connection-open', label: 'Open connection manager', requiredPermissions: [] },
      { id: 'connection-test', label: 'Run connection test', requiredPermissions: [] },
    ],
  },
];

const activeSubscriptionStates = new Set(['active', 'trial', 'grace', 'online']);

export function roleLabel(role: ClientRole): string {
  return startingRolePresets.find((preset) => preset.value === role)?.label ?? role;
}

export function hasPermission(grantedPermissions: string[], requiredPermissions: string[]): boolean {
  if (requiredPermissions.length === 0) {
    return true;
  }

  if (grantedPermissions.includes('*')) {
    return true;
  }

  return requiredPermissions.some((requiredPermission) => grantedPermissions.includes(requiredPermission));
}

function hasModuleAccess(shopStatus: ShopSystemStatus | undefined, moduleKeys: string[] | undefined): boolean {
  if (!moduleKeys || moduleKeys.length === 0) {
    return true;
  }

  if (!shopStatus) {
    return true;
  }

  const enabledModules = new Set(shopStatus.enabledModules);
  return moduleKeys.some((moduleKey) => enabledModules.has(moduleKey));
}

function hasOperationalShopAccess(shopStatus: ShopSystemStatus | undefined): boolean {
  if (!shopStatus) {
    return true;
  }

  const activeShop = shopStatus.shopActivationStatus.toLowerCase() === 'active';
  const activeSubscription = activeSubscriptionStates.has(shopStatus.subscriptionStatus.toLowerCase());
  return activeShop && activeSubscription;
}

export function resolveVisibleShellNavigation(input: {
  permissions: string[];
  shopStatus?: ShopSystemStatus;
  shiftStatus: ShiftStatus;
}): VisibleShellNavigationItem[] {
  const canOperate = hasOperationalShopAccess(input.shopStatus);

  return shellNavigationItems.map((item) => {
    const itemVisible =
      (item.group === 'connection' || canOperate) &&
      hasPermission(input.permissions, item.requiredPermissions) &&
      hasModuleAccess(input.shopStatus, item.moduleKeys);

    const visibleActions = item.actions.map((action) => ({
      ...action,
      visible: itemVisible && hasPermission(input.permissions, action.requiredPermissions),
    }));

    return {
      ...item,
      visible: itemVisible,
      visibleActions,
    };
  });
}

export function resolveSyncState(connectionState: 'connected' | 'connecting' | 'disconnected', shopStatus?: ShopSystemStatus) {
  if (connectionState === 'connecting') {
    return 'syncing' as const;
  }

  if (connectionState !== 'connected') {
    return 'failed' as const;
  }

  if (!shopStatus) {
    return 'delayed' as const;
  }

  return shopStatus.platformConnectivity.toLowerCase() === 'online' ? 'synced' as const : 'delayed' as const;
}