import { createModuleShell } from '../module-shell';

export const suppliersModule = createModuleShell({
  name: 'Suppliers',
  key: 'suppliers',
  status: 'scaffolded',
  ownsTransactions: false,
  auditable: true,
  synchronized: true,
  localOnly: false,
});
