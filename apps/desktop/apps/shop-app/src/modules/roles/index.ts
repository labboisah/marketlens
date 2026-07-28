import { createModuleShell } from '../module-shell';

export const rolesModule = createModuleShell({
  name: 'Roles',
  key: 'roles',
  status: 'scaffolded',
  ownsTransactions: false,
  auditable: true,
  synchronized: true,
  localOnly: false,
});
