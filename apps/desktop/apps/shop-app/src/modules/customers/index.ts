import { createModuleShell } from '../module-shell';

export const customersModule = createModuleShell({
  name: 'Customers',
  key: 'customers',
  status: 'scaffolded',
  ownsTransactions: false,
  auditable: true,
  synchronized: true,
  localOnly: false,
});
