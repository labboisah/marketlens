export const permissions = {
  viewDashboard: 'dashboard.view',
  manageShift: 'shift.manage',
  viewProducts: 'products.view',
  processSale: 'sales.process',
} as const;

export type Permission = (typeof permissions)[keyof typeof permissions];

export function hasPermission(granted: readonly Permission[], permission: Permission): boolean {
  return granted.includes(permission);
}
