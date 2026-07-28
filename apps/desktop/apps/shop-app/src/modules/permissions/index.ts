import { createModuleShell } from '../module-shell';

export const permissionsModule = createModuleShell({
  name: 'Permissions',
  key: 'permissions',
  status: 'scaffolded',
  ownsTransactions: false,
  auditable: true,
  synchronized: true,
  localOnly: false,
});
