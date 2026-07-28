import { createModuleShell } from '../module-shell';

export const synchronizationModule = createModuleShell({
  name: 'Synchronization',
  key: 'synchronization',
  status: 'scaffolded',
  ownsTransactions: false,
  auditable: true,
  synchronized: false,
  localOnly: true,
});
