import { createModuleShell } from '../module-shell';

export const purchasesModule = createModuleShell({
  name: 'Purchases',
  key: 'purchases',
  status: 'scaffolded',
  ownsTransactions: true,
  auditable: true,
  synchronized: true,
  localOnly: false,
});
