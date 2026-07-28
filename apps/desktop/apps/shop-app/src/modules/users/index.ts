import { createModuleShell } from '../module-shell';

export const usersModule = createModuleShell({
  name: 'Users',
  key: 'users',
  status: 'scaffolded',
  ownsTransactions: false,
  auditable: true,
  synchronized: true,
  localOnly: false,
});
