import { createModuleShell } from '../module-shell';

export const salesModule = createModuleShell({
  name: 'Sales',
  key: 'sales',
  status: 'scaffolded',
  ownsTransactions: true,
  auditable: true,
  synchronized: true,
  localOnly: false,
});
