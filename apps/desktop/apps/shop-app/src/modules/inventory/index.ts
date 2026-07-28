import { createModuleShell } from '../module-shell';

export const inventoryModule = createModuleShell({
  name: 'Inventory',
  key: 'inventory',
  status: 'scaffolded',
  ownsTransactions: true,
  auditable: true,
  synchronized: true,
  localOnly: false,
});
