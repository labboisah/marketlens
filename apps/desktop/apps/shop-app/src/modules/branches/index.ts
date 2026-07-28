import { createModuleShell } from '../module-shell';

export const branchesModule = createModuleShell({
  name: 'Branches',
  key: 'branches',
  status: 'scaffolded',
  ownsTransactions: false,
  auditable: true,
  synchronized: true,
  localOnly: false,
});
