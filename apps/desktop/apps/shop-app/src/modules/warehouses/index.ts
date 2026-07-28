import { createModuleShell } from '../module-shell';

export const warehousesModule = createModuleShell({
  name: 'Warehouses',
  key: 'warehouses',
  status: 'scaffolded',
  ownsTransactions: false,
  auditable: true,
  synchronized: true,
  localOnly: false,
});
