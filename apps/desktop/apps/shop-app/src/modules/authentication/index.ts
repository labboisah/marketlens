import { createModuleShell } from '../module-shell';

export const authenticationModule = createModuleShell({
  name: 'Authentication',
  key: 'authentication',
  status: 'scaffolded',
  ownsTransactions: false,
  auditable: true,
  synchronized: false,
  localOnly: true,
});
