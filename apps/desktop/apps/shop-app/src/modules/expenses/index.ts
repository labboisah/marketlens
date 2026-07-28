import { createModuleShell } from '../module-shell';

export const expensesModule = createModuleShell({
  name: 'Expenses',
  key: 'expenses',
  status: 'scaffolded',
  ownsTransactions: true,
  auditable: true,
  synchronized: true,
  localOnly: false,
});
