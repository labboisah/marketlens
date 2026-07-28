import { createModuleShell } from '../module-shell';

export const backupModule = createModuleShell({
  name: 'Backup',
  key: 'backup',
  status: 'scaffolded',
  ownsTransactions: false,
  auditable: true,
  synchronized: false,
  localOnly: true,
});
